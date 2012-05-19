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
      'method' => array()
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
     */
    public function run($method, $uri = null)
    {
        $response = false;

        if($method instanceof \Bullet\Request) {
            $request = $method;
            $this->_requestMethod = strtoupper($request->method());
            $this->_requestPath = $request->url();
        } else {
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
            $res = $this->_runPath($this->_requestMethod, $path);
            $response = $res;
        }

        // Ensure response is always a Bullet\Response
        if($response === false) {
            $response = $this->response(null, 404);
        } else {
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
        return array();
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
     * Implementing for Rackem\Rack (PHP implementation of Rack)
     */
    public function call($env)
    {
        $response = $this->run($env['REQUEST_METHOD'], $env['PATH_INFO']);
        return array($response->status(), $response->headers(), $response->content());
    }
}
