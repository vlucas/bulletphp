<?php

namespace Bullet;

use Pimple\Container;

class App extends Container
{
    protected $callbacks;
    protected $currentCallbacks;

    public function __construct()
    {
        $this->callbacks = [];
        $this->currentCallbacks = &$this->callbacks;
    }

    /**
     * Run app with given Request
     *
     * @param \Bullet\Request \Bullet\Request object
     * @return \Bullet\Response
     */
    public function run(Request $request)
    {
        // Save the app's URL parser state (e.g. the current callback map)
        $currentCallbacks = &$this->currentCallbacks;
        $this->currentCallbacks = &$this->callbacks;

        try {
            $rsp = new Response();

            // Remove empty path elements
            $uri = $request->uri();
            $parts = [];
            foreach (explode('/', $uri) as $part) {
                if ($part != '') {
                    $parts[] = $part;
                }
            }

            // TODO: detect extension

            // TODO: run before filter

            $level = 0;
            foreach ($parts as $part) {
                if (!array_key_exists($part, $this->currentCallbacks)) {
                    print "404\n";
                    print implode('/', array_slice($parts, 0, $level+1));
                    throw new NotFoundException();
                    return $rsp;
                }
                if (array_key_exists('path', $this->currentCallbacks[$part])) {
                    $cb = Closure::bind($this->currentCallbacks[$part]['path'], $this);
                    $cb();
                }
                ++$level;
            }

            return $rsp;
        } finally {
            $this->currentCallbacks = &$currentCallbacks;
        }
    }

    public function resource($part, $callback)
    {
        $this->currentCallbacks[$part]['path'] = $callback;
    }

    public function path($part, $callback)
    {
        $this->currentCallbacks[$part]['path'] = $callback;
    }

    public function param($part, $callback)
    {
        $this->currentCallbacks[$part]['path'] = $callback;
    }

    public function get($part, $callback)
    {
        $this->currentCallbacks[$part]['get'] = $callback;
    }

    public function post($part, $callback)
    {
        $this->currentCallbacks[$part]['post'] = $callback;
    }

    public function put($part, $callback)
    {
        $this->currentCallbacks[$part]['put'] = $callback;
    }

    public function delete($part, $callback)
    {
        $this->currentCallbacks[$part]['delete'] = $callback;
    }

    public function patch($part, $callback)
    {
        $this->currentCallbacks[$part]['patch'] = $callback;
    }

    public function options($part, $callback)
    {
        $this->currentCallbacks[$part]['options'] = $callback;
    }

    public function domain()
    {
    }

    public function subdomain()
    {
    }

    public function on()
    {
    }

    public function helper($name, $className = null)
    {
        if($className === null) {
            // Ensure helper exists
            if(!isset($this->_helpers[$name])) {
                throw new \InvalidArgumentException("Requested helper '" . $name ."' not registered.");
            }

            // Instantiate helper if not done already
            if(!is_object($this->_helpers[$name])) {
                $this->_helpers[$name] = new $this->_helpers[$name];
            }

            return $this->_helpers[$name];
        } else {
            $this->_helpers[$name] = $className;
        }
    }

    public function registerResponseHandler()
    {
    }

    public function removeResponseHandler()
    {
    }

    // TODO: format
}
