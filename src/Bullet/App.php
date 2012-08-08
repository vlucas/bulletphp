<?php
namespace Bullet;

class App
{
    protected $_paths = array();
    protected $_requestMethod;
    protected $_requestPath;
    protected $_curentPath;
    protected $_callbacks = array(
      'path' => array(),
      'param' => array(),
      'param_type' => array(),
      'method' => array(),
      'exception' => array(),
      'custom' => array()
    );

    public function __construct()
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
    }

    public function path($path, \Closure $callback)
    {
        $path = trim($path, '/');
        $this->_callbacks['path'][$path] = $this->_prepClosure($callback);
        return $this;
    }

    public function param($param, \Closure $callback)
    {
        $this->_callbacks['param'][$param] = $this->_prepClosure($callback);
        return $this;
    }

    public function registerParamType($type, $callback)
    {
        if(!is_callable($callback)) {
            throw new \InvalidArgumentException("Second argument must be a valid callback. Given argument was not callable.");
        }
        $this->_callbacks['param_type'][$type] = $callback;
        return $this;
    }

    /**
     * Prep closure callback by binding context in PHP >= 5.4
     */
    protected function _prepClosure(\Closure $closure)
    {
        // Bind local context for PHP >= 5.4
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            $closure->bindTo($this);
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

        if($method instanceof \Bullet\Request) {
            $request = $method;
            $this->_request = $request;
            $this->_requestMethod = strtoupper($request->method());
            $this->_requestPath = $request->url();
        } else {
            $this->_request = new \Bullet\Request();
            $this->_requestMethod = strtoupper($method);
            $this->_requestPath = $uri;
        }

        // Normalize request path
        $this->_requestPath = trim($this->_requestPath, '/');

        // Explode by path without leading or trailing slashes
        $paths = explode('/', $this->_requestPath);
        foreach($paths as $pos => $path) {
            $this->_currentPath = implode('/', array_slice($paths, 0, $pos+1));

            // Run and get result
            try {
                $response = $this->_runPath($this->_requestMethod, $path);
            } catch(\Exception $e) {
                $response = $this->response($this->handleException($e), 500);
            }
        }

        // Ensure response is always a Bullet\Response
        if($response === false) {
            // Boolean false result generates a 404
            $response = $this->response(null, 404);
        } elseif(is_int($response)) {
            // Assume int response is desired HTTP status code
            $response = $this->response(null, $response);
        } else {
            // Convert response to Bullet\Response object if not one already
            if(!($response instanceof \Bullet\Response)) {
                $response = $this->response($response);
            }
        }

        return $response;
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
    public function response($content = null, $statusCode = 200)
    {
        $res = new \Bullet\Response($content, $statusCode);

        // If content not set, use default HTTP
        if($content === null) {
            $res->content($res->statusText($statusCode));
        }
        return $res;
    }

    /**
     * Execute callbacks that match particular path segment
     */
    protected function _runPath($method, $path, \Closure $callback = null)
    {
        // Use $callback param if set (always overrides)
        if($callback !== null) {
            $res = call_user_func($callback, $this->request());
            return $res;
        }

        // Default response is boolean false (produces 404 Not Found)
        $res = false;

        // Run 'path' callbacks
        if(isset($this->_callbacks['path'][$path])) {
            $cb = $this->_callbacks['path'][$path];
            $res = call_user_func($cb, $this->request());
        }

        // Run 'param' callbacks
        if(count($this->_callbacks['param']) > 0) {
            foreach($this->_callbacks['param'] as $filter => $cb) {
                // Use matching registered filter type callback if given a non-callable string
                if(is_string($filter) && !is_callable($filter) && isset($this->_callbacks['param_type'][$filter])) {
                    $filter = $this->_callbacks['param_type'][$filter];
                }
                $param = call_user_func($filter, $path);

                // Skip to next callback in same path if boolean false returned
                if($param === false) {
                    continue;
                } elseif(!is_bool($param)) {
                    // Pass callback test function return value if not boolean
                    $path = $param;
                }
                $res = call_user_func($cb, $this->request(), $path);
                break;
            }
        }

        // Run 'method' callbacks if the path is the full requested one
        if($this->isRequestPath()) {
            // If there are ANY method callbacks, use if matches method, return 405 if not
            // If NO method callbacks are present, path return value will be used, or 404
            if(count($this->_callbacks['method']) > 0) {
                if(isset($this->_callbacks['method'][$method])) {
                    $cb = $this->_callbacks['method'][$method];
                    $res = call_user_func($cb, $this->request());
                } else {
                    $res = $this->response(null, 405);
                }
            }
        } else {
            // Empty out collected method callbacks
            $this->_callbacks['method'] = array();
        }

        return $res;
    }

    /**
     * Get current request object (do nothing for now)
     */
    public function request()
    {
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
     * Handle HTTP method
     *
     * @param string $method HTTP method to handle for
     * @param \Closure $callback Closure to execute to handle specified HTTP method
     */
    public function method($method, \Closure $callback)
    {
        $this->_callbacks['method'][strtoupper($method)] = $this->_prepClosure($callback);
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
            $endsWithPath = substr_compare($currentPath, $path, -$pathLen, $pathLen) === 0;

            // Don't double-stack path if it's the same as the current path
            if($path != $currentPath && !$endsWithPath) {
                $path = $currentPath . '/' . trim($path, '/');
            // Don't append another segment to the path that matches the end of the current path already
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
     * Add a custom exception handler to handle any exceptions and return an HTTP response
     *
     * @param callback $callback Callback or closure that will be executed when missing method call matching $method is made
     * @throws InvalidArgumentException
     */
    public function exceptionHandler($callback)
    {
        if(!is_callable($callback)) {
            throw new \InvalidArgumentException("First argument is expected to be a valid callback or closure. Got: " . gettype($callback));	
        }
        $this->_callbacks['exception'][] = $callback;
    }

    /**
     * Handle exception using exception handling callbacks, if any
     */
    public function handleException(\Exception $e)
    {
        foreach($this->_callbacks['exception'] as $handler) {
            $res = call_user_func($handler, $e);
            if($res !== null) {
                return $res;
            }
        }

        // Re-throw exception if there are no registered exception handlers
        throw $e;
    }

    /**
     * Implementing for Rackem\Rack (PHP implementation of Rack)
     */
    public function call($env)
    {
        $response = $this->run($env['REQUEST_METHOD'], $env['PATH_INFO']);
        return array($response->status(), $response->headers(), $response->content());
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
     * Prevent PHP from trying to serialize cached object instances on Kernel
     */
    public function __sleep()
    {
        return array();
    }
}
