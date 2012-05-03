<?php
namespace Bullet;

class App
{
    protected $_paths = array();
    protected $_requestMethod;
    protected $_curentPath;
    protected $_callbacks = array();


    public function path($path, \Closure $callback)
    {
        $this->_callbacks['path'][$path] = $callback;
    }

    public function param($param, \Closure $callback)
    {
        $this->_callbacks['param'][$param] = $callback;
    }

    /** 
     * Run app with given HTTP_METHOD and REQUEST_URI
     */
    public function run($method, $uri)
    {
        $this->_requestMethod = strtoupper($method);
        $this->_currentPath = $uri;

        // Explode by path without leading or trailing slashes
        $paths = explode('/', trim($uri, '/'));
        $lastPath = '';
        foreach($paths as $path) {
            $lastPath .= '/' . $path;
            $this->_paths[] = $lastPath;

            // Run 
            $this->_runPath($method, $path);
        }
    }

    /**
     * Execute callbacks that match particular path segment
     */
    protected function _runPath($method, $path, \Closure $callback = null)
    {
        // Set current path as one about to run
        $this->_currentPath = $path;

        // Use $callback param if set (always overrides)
        if($callback !== null) {
            $res = call_user_func($callback, $this->request());
            return $res;
        }

        // Run 'path' callbacks
        if(isset($this->_callbacks['path'][$path])) {
            $cb = $this->_callbacks['path'][$path];
            $res = call_user_func($cb, $this->request());
            return $res;
        }

        // Run 'param' callbacks
        if(count($this->_callbacks['param']) > 0) {
            $filter = key($this->_callbacks['param']);
            $cb = array_shift($this->_callbacks['param']);
            $param = call_user_func($filter, $path);
            if(false === $param) {
                return $this->_runPath($method, $path);
            }
            $res = call_user_func($cb, $this->request(), $param);
            return $res;
        }
    }

    /**
     * Get current request object (do nothing for now)
     */
    public function request()
    {
        return array();
    }

    /**
     *
     */
    public function currentPath()
    {
        return $this->_currentPath;
    }

    /**
     *
     */
    public function get(\Closure $callback)
    {
        if($this->_requestMethod === 'GET') {
            return $this->_runPath($this->_requestMethod, $this->currentPath(), $callback);
        }
    }

    /**
     *
     */
    public function post(\Closure $callback)
    {
        if($this->_requestMethod === 'POST') {
            return $this->_runPath($this->_requestMethod, $this->currentPath(), $callback);
        }
    }
}
