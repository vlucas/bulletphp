<?php
namespace Bullet;

use Pimple\Container;

class App extends Container
{
    protected $_request;
    protected $_response;

    protected $_paths = array();
    protected static $_pathLevel = 0;
    protected $_requestMethod;
    protected $_requestPath;
    protected $_currentPath;
    protected $_paramTypes = array();
    protected $_callbacks = array(
        'path' => array(),
        'param' => array(),
        'method' => array(),
        'subdomain' => array(),
        'domain' => array(),
        'format' => array(),
        'custom' => array()
    );
    protected $_helpers = array();
    protected $_responseHandlers = array();

    /**
     * New App instance
     *
     * @param array $values Array of config settings and objects to pass into Pimple container
     */
    public function __construct(array $values = array())
    {
        $this->registerParamType('int', function($value) {
            return filter_var($value, FILTER_VALIDATE_INT);
        });
        $this->registerParamType('float', function($value) {
            return filter_var($value, FILTER_VALIDATE_FLOAT);
        });
        // True = "1", "true", "on", "yes"
        // False = "0", "false", "off", "no"
        $this->registerParamType('boolean', function($value) {
            $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            return (!empty($filtered) && $filtered !== null);
        });
        $this->registerParamType('slug', function($value) {
            return (preg_match("/[a-zA-Z0-9-_]/", $value) > 0);
        });
        $this->registerParamType('email', function($value) {
            return filter_var($value, FILTER_VALIDATE_EMAIL);
        });

        $this->registerResponseHandler(
            function($response) {
                return is_array($response->content());
            },
            function($response) {
                $response->contentType('application/json');
                $response->content(json_encode($response->content()));
            }
        );

        // Pimple constructor
        parent::__construct($values);

        // Template configuration settings if given
        if(isset($this['template.cfg'])) {
            $this['template'] = $this['template.cfg'];
        }
        if(isset($this['template'])) {
            View\Template::config($this['template']);
        }

        // Get callback stacks ready
        self::$_pathLevel = 0;
        $this->resetCallbacks();
    }

    /**
     * Reset/clear all callbacks to their initial state
     */
    public function resetCallbacks($types = null)
    {
        if(is_string($types)) {
            $types = (array) $types;
        }
        if(is_array($types)) {
            foreach($this->_callbacks as $type => $stack) {
                if(in_array($type, $types, true)) {
                    $this->_callbacks[$type][self::$_pathLevel] = array();
                }
            }
            return true;
        }

        $this->_callbacks = array(
            'path' => array(),
            'param' => array(),
            'method' => array(),
            'subdomain' => array(),
            'domain' => array(),
            'format' => array(),
            'custom' => array()
        );
    }

    public function path($path, \Closure $callback)
    {
        foreach((array) $path as $p) {
            $p = trim($p, '/');
            $this->_callbacks['path'][self::$_pathLevel][$p] = $this->_prepClosure($callback);
        }
        return $this;
    }
    // Alias for 'path'
    public function resource($path, \Closure $callback)
    {
        return $this->path($path, $callback);
    }

    public function param($param, \Closure $callback)
    {
        $this->_callbacks['param'][self::$_pathLevel][$param] = $this->_prepClosure($callback);
        return $this;
    }

    public function registerParamType($type, $callback)
    {
        if(!is_callable($callback)) {
            throw new \InvalidArgumentException("Second argument must be a valid callback. Given argument was not callable.");
        }
        $this->_paramTypes[$type] = $callback;
        return $this;
    }

    /**
     * Prep closure callback by binding context in PHP >= 5.4
     */
    protected function _prepClosure(\Closure $closure)
    {
        // Bind local context for PHP >= 5.4
        if (version_compare(PHP_VERSION, '5.4.0') >= 0 && !defined('HHVM_VERSION')) {
            $closure = $closure->bindTo($this);
        }
        return $closure;
    }

    /**
     * Run app with given REQUEST_METHOD and REQUEST_URI
     *
     * @param string|object $method HTTP request method string or \Bullet\Request object
     * @param string optional $uri URI/path to run
     * @return \Bullet\Response
     */
    public function run($method, $uri = null)
    {
        $response = false;
        self::$_pathLevel = 0;

        // If Request instance was passed in as the first parameter
        if($method instanceof \Bullet\Request) {
            $request = $method;
            $this->_request = $request;
        // Create new Request object from passed method and URI
        } else {
            $this->_request = new \Bullet\Request($method, $uri);
        }
        $this->_requestMethod = strtoupper($this->_request->method());
        $this->_requestPath = $this->_request->url();

        // Detect extension and assign it as the requested format (default is 'html')
        $dotPos = strpos($this->_requestPath, '.');
        if($dotPos !== false) {
            $ext = substr($this->_requestPath, $dotPos+1);
            $this->_request->format($ext);
            // Remove extension from path for path execution
            $this->_requestPath = substr($this->_requestPath, 0, -(strlen($this->_request->format())+1));
        }

        // Normalize request path
        $this->_requestPath = trim($this->_requestPath, '/');

        // Run before filter
        $this->filter('before');

        // Explode by path without leading or trailing slashes
        $paths = explode('/', $this->_requestPath);
        foreach($paths as $pos => $path) {
            $this->_currentPath = implode('/', array_slice($paths, 0, $pos+1));

            // Run and get result
            try {
                $response = $this->_runPath($this->_requestMethod, $path);
            } catch(\Exception $e) {
                // Always trigger base 'Exception', plus actual exception class
                $events = array_unique(array('Exception', get_class($e)));

                // Default status is 500 and content is Exception object
                $this->response()->status(500)->content($e);

                // Run filters and assign response
                $this->filter($events, array($e));
                $response = $this->response();
                break;
            }
        }

        // Perform last minute operations on our response
        $this->filter('beforeResponseHandler', array($response));
        $response = $this->_handleResponse($response);

        // Set current outgoing response
        $this->response($response);

        // Trigger events based on HTTP request format and HTTP response code
        $this->filter(array_filter(array($this->_request->format(), $response->status(), 'after')));

        return $response;
    }

    /**
     * Execute callbacks that match particular path segment
     */
    protected function _runPath($method, $path, \Closure $callback = null)
    {
        $request = $this->request();

        // Use $callback param if set (always overrides)
        if($callback !== null) {
            $res = call_user_func($callback, $request);
            return $res;
        }

        // Default response is boolean false (produces 404 Not Found)
        $res = false;
        $pathMatched = false;

        // Run 'subdomain' callbacks
        $subdomain = strtolower($request->subdomain());
        if(isset($this->_callbacks['subdomain'][self::$_pathLevel][$subdomain])) {
            $cb = $this->_callbacks['subdomain'][self::$_pathLevel][$subdomain];
            self::$_pathLevel++;
            $res = call_user_func($cb, $request);
        }

        // Run 'domain' callbacks
        $domain = preg_replace('~^www\.~', '', strtolower($request->host()));
        if(isset($this->_callbacks['domain'][self::$_pathLevel][$domain])) {
            $cb = $this->_callbacks['domain'][self::$_pathLevel][$domain];
            self::$_pathLevel++;
            $res = call_user_func($cb, $request);
        }

        // Return false for subdomain and domain by default
        if($res === null) {
            $res = false;
        }

        // Run 'path' callbacks
        if(isset($this->_callbacks['path'][self::$_pathLevel][$path])) {
            $cb = $this->_callbacks['path'][self::$_pathLevel][$path];
            self::$_pathLevel++;
            $res = call_user_func($cb, $request);
            $pathMatched = true;
        }

        // Run 'param' callbacks
        if(!$pathMatched && isset($this->_callbacks['param'][self::$_pathLevel]) && count($this->_callbacks['param'][self::$_pathLevel]) > 0) {
            foreach($this->_callbacks['param'][self::$_pathLevel] as $filter => $cb) {
                // Use matching registered filter type callback if given a non-callable string
                if(is_string($filter) && !is_callable($filter) && isset($this->_paramTypes[$filter])) {
                    $filter = $this->_paramTypes[$filter];
                }
                $param = call_user_func($filter, $path);

                // Skip to next callback in same path if boolean false returned
                if($param === false) {
                    continue;
                } elseif(!is_bool($param)) {
                    // Pass callback test function return value if not boolean
                    $path = $param;
                }
                self::$_pathLevel++;
                $res = call_user_func($cb, $request, $path);
                break;
            }
        }

        // OPTIONS request with no custom options handler should return 200 OK with 'Accept' header
        if($pathMatched && $method === 'OPTIONS' && !isset($this->_callbacks['method'][self::$_pathLevel]['OPTIONS'])) {
            $acceptMethods = array_keys($this->_callbacks['method'][self::$_pathLevel]);
            $acceptMethodsString = implode(',', array_merge($acceptMethods, array('OPTIONS')));
            $res = $this->response(200)->header('Allow', $acceptMethodsString);
            return $res;
        }

        // Run 'method' callbacks if the path is the full requested one
        if($this->isRequestPath() && isset($this->_callbacks['method'][self::$_pathLevel]) && count($this->_callbacks['method'][self::$_pathLevel]) > 0) {
            // If this is a HEAD request and there is no HEAD handler, but there is a GET one, run as GET
            if($method === 'HEAD' && !isset($this->_callbacks['method'][self::$_pathLevel]['HEAD']) && isset($this->_callbacks['method'][self::$_pathLevel]['GET'])) {
                $method = 'GET';
            }

            // If there are ANY method callbacks, use if matches method, return 405 if not
            // If NO method callbacks are present, path return value will be used, or 404
            if(isset($this->_callbacks['method'][self::$_pathLevel][$method])) {
                $cb = $this->_callbacks['method'][self::$_pathLevel][$method];
                self::$_pathLevel++;
                $res = call_user_func($cb, $request);
            } else {
                $acceptMethods = array_keys($this->_callbacks['method'][self::$_pathLevel]);
                $res = $this->response(405)->header('Allow', implode(',', $acceptMethods));
            }
        } else {
            // Empty out collected method callbacks
            $this->resetCallbacks('method');
        }

        // Run 'format' callbacks if the path is the full one AND the requested format matches a callback
        $format = $this->_request->format();
        $accept = $this->_request->accept();
        $acceptAny = is_array($accept) ? count(array_intersect($accept, array(null, '*/*', '', '*'))) !== 0 : false;
        if($this->isRequestPath() && isset($this->_callbacks['format'][self::$_pathLevel]) && count($this->_callbacks['format'][self::$_pathLevel]) > 0) {
            // If there are ANY format callbacks, use if matches format, return 406 if not
            // If NO method callbacks are present, path return value will be used, or 404
            $cb = null;
            if (isset($this->_callbacks['format'][self::$_pathLevel][$format])) {
                $cb = $this->_callbacks['format'][self::$_pathLevel][$format];
            } elseif (!empty($format)) {
                return $res = $this->response(406);
            }

            // Use first defined format if Accept is not present or is wildcard (any/all)
            if ($cb === null && (empty($accept) || $acceptAny)) {
                $cb = current($this->_callbacks['format'][self::$_pathLevel]);
            }

            // Response
            if ($cb !== null) {
                self::$_pathLevel++;
                $res = call_user_func($cb, $request);
            } else {
                $res = $this->response(406);
            }
        } else {
            // Empty out collected format callbacks
            $this->resetCallbacks('format');
        }

        return $res;
    }

    /**
     * Determine if the currently executing path is the full requested one
     */
    public function isRequestPath()
    {
        return $this->_currentPath === $this->_requestPath;
    }

    /**
     * Send HTTP response with status code and content
     */
    public function response($statusCode = null, $content = null)
    {
        $res = null;

        // Get current response (passed nothing)
        if($statusCode === null) {
            $res = $this->_response;

        // Set response
        } elseif($statusCode instanceof \Bullet\Response) {
            $res = $this->_response = $statusCode;
        }

        // Create new response if none is going to be returned
        if($res === null) {
            $res = new \Bullet\Response($content, $statusCode);

            // If content not set, use default HTTP
            if($content === null) {
                $res->content($res->statusText($statusCode));
            }
        }

        // Ensure no response body is sent for special status codes or for HEAD requests
        if(in_array($res->status(), array(204, 205, 304)) || $this->request()->method() === 'HEAD') {
            $res->content('');
        }

        // If this is the first response sent, store it
        if($this->_response === null) {
            $this->_response = $res;
        }

        return $res;
    }

    /**
     * Get current request object
     *
     * @return \Bullet\Request
     */
    public function request()
    {
        if($this->_request === null) {
            $this->_request = new \Bullet\Request();
        }
        return $this->_request;
    }

    /**
     * Getter for current path
     */
    public function currentPath()
    {
        return $this->_currentPath;
    }

    /**
     * Handle GET method
     */
    public function get(\Closure $callback)
    {
        return $this->method('GET', $callback);
    }

    /**
     * Handle POST method
     */
    public function post(\Closure $callback)
    {
        return $this->method('POST', $callback);
    }

    /**
     * Handle PUT method
     */
    public function put(\Closure $callback)
    {
        return $this->method('PUT', $callback);
    }

    /**
     * Handle DELETE method
     */
    public function delete(\Closure $callback)
    {
        return $this->method('DELETE', $callback);
    }

    /**
     * Handle PATCH method
     */
    public function patch(\Closure $callback)
    {
        return $this->method('PATCH', $callback);
    }

    /**
     * Handle HEAD method
     */
    public function head(\Closure $callback)
    {
        return $this->method('HEAD', $callback);
    }

    /**
     * Handle OPTIONS method
     */
    public function options(\Closure $callback)
    {
        return $this->method('OPTIONS', $callback);
    }

    /**
     * Handle HTTP method
     *
     * @param string|array $method HTTP method to handle for
     * @param \Closure $callback Closure to execute to handle specified HTTP method
     */
    public function method($methods, \Closure $callback)
    {
        foreach((array) $methods as $method) {
            $this->_callbacks['method'][self::$_pathLevel][strtoupper($method)] = $this->_prepClosure($callback);
        }
        return $this;
    }

    /**
     * Handle specific subdomain
     *
     * @param string|array $subdomain Name of subdomain to use
     * @param \Closure $callback Closure to execute to handle specified subdomain path
     */
    public function subdomain($subdomains, \Closure $callback)
    {
        foreach((array) $subdomains as $subdomain) {
            $this->_callbacks['subdomain'][self::$_pathLevel][strtolower($subdomain)] = $this->_prepClosure($callback);
        }
        return $this;
    }

    /**
     * Handle specific domain
     *
     * @param string|array $domain Name of domain to use
     * @param \Closure $callback Closure to execute to handle specified domain path
     */
    public function domain($domain, \Closure $callback)
    {
        foreach((array) $domain as $domain) {
            $this->_callbacks['domain'][self::$_pathLevel][strtolower($domain)] = $this->_prepClosure($callback);
        }
        return $this;
    }

    /**
     * Handle HTTP content type as output format
     *
     * @param string|array $format HTTP content type format to handle for
     * @param \Closure $callback Closure to execute to handle specified format
     */
    public function format($formats, \Closure $callback)
    {
        foreach((array) $formats as $format) {
            $this->_callbacks['format'][self::$_pathLevel][strtolower($format)] = $this->_prepClosure($callback);
        }
        return $this;
    }

    /**
     * Build URL path for
     */
    public function url($path = null)
    {
        $request = $this->request();

        // Subdirectory, if any
        $subdir = trim(mb_substr($request->uri(), 0, mb_strrpos($request->uri(), $request->url())), '/');

        // Assemble full url
        $url = $request->scheme() . '://' . $request->host() . '/' . $subdir;

        // Allow for './' current path shortcut (append given path to current one)
        if(strpos($path, './') === 0) {
            $path = substr($path, 2);
            $currentPath = $this->currentPath();
            $pathLen = strlen($path);
            $startsWithPath = strpos($path, $currentPath) === 0;
            $endsWithPath = substr_compare($currentPath, $path, -$pathLen, $pathLen) === 0;

            // Don't double-stack path if it's the same as the current path
            if($path != $currentPath && !$startsWithPath && !$endsWithPath) {
                $path = $currentPath . '/' . trim($path, '/');
            // Don't append another segment to the path that matches the end of the current path already
            } elseif($startsWithPath) {
                // Do nothing
            } elseif($endsWithPath) {
                $path = $currentPath;
            }
        }

        if($path === null) {
            $path = $this->currentPath();
        }

        // url + path
        $url = rtrim($url, '/') . '/' . ltrim($path, '/');

        return $url;
    }

    /**
     * Return instance of Bullet\View\Template
     *
     * @param string $name Template name
     * @param array $params Array of params to set
     */
    public function template($name, array $params = array())
    {
        $tpl = new View\Template($name);
        $tpl->set($params);
        return $tpl;
    }

    /**
     * Load and return or register helper class
     *
     * @param string $name helper name to register
     * @param string $class Class name of helper to load
     */
    public function helper($name, $class = null)
    {
        if($class !== null) {
            $this->_helpers[$name] = $class;
            return;
        }

        // Ensure helper exists
        if(!isset($this->_helpers[$name])) {
            throw new \InvalidArgumentException("Requested helper '" . $name ."' not registered.");
        }

        // Instantiate helper if not done already
        if(!is_object($this->_helpers[$name])) {
            $this->_helpers[$name] = new $this->_helpers[$name];
        }

        return $this->_helpers[$name];
    }

    /**
     * Add event handler for named event
     *
     * @param mixed $event Name of the event to be handled
     * @param callback $callback Callback or closure that will be executed when event is triggered
     * @throws InvalidArgumentException
     */
    public function on($event, $callback)
    {
        if(!is_callable($callback)) {
            throw new \InvalidArgumentException("Second argument is expected to be a valid callback or closure. Got: " . gettype($callback));
        }

        // Allow for an array of events to be passed in
        foreach((array) $event as $eventName) {
            $eventName = $this->eventName($eventName);
            $this->_callbacks['events'][$eventName][] = $callback;
        }
    }

    /**
     * Remove event handlers for given event name
     *
     * @param mixed $event Name of the event to be handled
     * @return boolean Boolean true on successful event handler removal, false on failure or non-existent event
     */
    public function off($event)
    {
        // Allow for an array of events to be passed in
        foreach((array) $event as $eventName) {
            $eventName = $this->eventName($eventName);
            if(isset($this->_callbacks['events'][$eventName])) {
                unset($this->_callbacks['events'][$eventName]);
                return true;
            }
        }
        return false;
    }

    /**
     * Trigger event by running all filters for it
     *
     * @param string|array $event Name of the event or array of events to be triggered
     * @param array $args Extra arguments to pass to filters listening for event
     * @return boolean Boolean true on successful event trigger, false on failure or non-existent event
     */
    public function filter($event, array $args = array())
    {
        $request = $this->request();
        $response = $this->response();

        // Allow for an array of events to be passed in
        foreach((array) $event as $eventName) {
            $eventName = $this->eventName($eventName);
            if(isset($this->_callbacks['events'][$eventName])) {
                foreach($this->_callbacks['events'][$eventName] as $handler) {
                    call_user_func_array($handler, array_merge(array($request, $response), $args));
                }
            }
        }
    }

    /**
     * Normalize event name
     *
     * @param mixed $event Name of the event to be handled
     * @return string Normalized name of the event
     */
    public function eventName($eventName)
    {
        // Event is class name if class is passed
        if(is_object($eventName)) {
            $eventName = get_class($eventName);
        }
        if(!is_scalar($eventName)) {
            throw new \InvalidArgumentException("Event name is expected to be a scalar value (integer, float, string, or boolean). Got: " . gettype($eventName) . " (" . var_export($eventName, true) . ")");
        }
        return (string) $eventName;
    }

    /**
     * Print out an array or object contents in preformatted text
     * Useful for debugging and quickly determining contents of variables
     */
    public function dump()
    {
        $objects = func_get_args();
        $content = "\n<pre>\n";
        foreach($objects as $object) {
            $content .= print_r($object, true);
        }
        return $content . "\n</pre>\n";
    }

    /**
     * Add a custom user method via closure or PHP callback
     *
     * @param string $method Method name to add
     * @param callback $callback Callback or closure that will be executed when missing method call matching $method is made
     * @throws InvalidArgumentException
     */
    public function addMethod($method, $callback)
    {
        if(!is_callable($callback)) {
            throw new \InvalidArgumentException("Second argument is expected to be a valid callback or closure.");
        }
        if(method_exists($this, $method)) {
            throw new \InvalidArgumentException("Method '" . $method . "' already exists on " . __CLASS__);
        }
        $this->_callbacks['custom'][$method] = $callback;
    }

    /**
     * Run user-added callback
     *
     * @param string $method Method name called
     * @param array $args Array of arguments used in missing method call
     * @throws BadMethodCallException
     */
    public function __call($method, $args)
    {
        if(isset($this->_callbacks['custom'][$method]) && is_callable($this->_callbacks['custom'][$method])) {
            $callback = $this->_callbacks['custom'][$method];
            return call_user_func_array($callback, $args);
        } else {
            throw new \BadMethodCallException("Method '" . __CLASS__ . "::" . $method . "' not found");
        }
    }

    /**
     * Prevent PHP from trying to serialize cached object instances
     */
    public function __sleep()
    {
        return array();
    }

    /**
     * Register a response handler to potentially be applied to responses
     * returned by \Bullet\App::run. Each callback is given the
     * \Bullet\Response object as a parameter.
     *
     * @param callable $condtion Function name or closure to test against response
     * @param callable $handler Function name or closure to modify response
     *
     * @returns \Bullet\App
     */
    public function registerResponseHandler($condition, $handler)
    {
        if(null !== $condition && !is_callable($condition)) {
            throw new \InvalidArgumentException("First argument to " . __METHOD__ . " must be a valid callback or NULL. Given argument was neither.");
        }
        if(!is_callable($handler)) {
            throw new \InvalidArgumentException("Second argument to " . __METHOD__ . " must be a valid callback. Given argument was not callable.");
        }

        $this->_responseHandlers[] = array(
            'condition' => $condition,
            'handler'   => $handler
        );

        return $this;
    }

    /**
     * Modify response to prepare it for returning.
     *
     * Applies special logic for particular response types and ensure response
     * is a \Bullet\Response object.
     *
     * Loops through registered response handlers and applies any with a null
     * condition or whose condition evaluates to true.
     *
     * @param mixed $response The response to act upon.
     */
    protected function _handleResponse($response)
    {
        // Ensure response is always a Bullet\Response
        if($response === false) {
            // Boolean false result generates a 404
            $response = $this->response(404);
        } elseif(is_int($response)) {
            // Assume int response is desired HTTP status code
            $response = $this->response($response);
        } else {
            // Convert response to Bullet\Response object if not one already
            if(!($response instanceof \Bullet\Response)) {
                $response = $this->response(200, $response);
            }
        }

        // Apply user defined response handlers
        foreach($this->_responseHandlers as $handler) {
            if(null === $handler['condition'] || call_user_func($handler['condition'], $response)) {
                call_user_func($handler['handler'], $response);
            }
        }

        return $response;
    }
}
