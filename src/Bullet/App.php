<?php

namespace Bullet;

use Pimple\Container;

class App extends Container
{
    protected $rootCallbacks;
    protected $currentCallbacks;

    public function __construct()
    {
        $this->rootCallbacks = [];
        $this->currentCallbacks = &$this->rootCallbacks;
    }

    protected function executeCallback(\Closure $c, $request)
    {
        $c = \Closure::bind($c, $this);
        $response = $c($request);

        if ($response === null || $response instanceOf Response) {
            return $response;
        }
        if (is_string($response)) {
            return new Response($response, 200);
        }
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
        $currentCallbacks = $this->currentCallbacks;
        $this->currentCallbacks = $this->rootCallbacks;

        try {
            // Remove empty path elements
            $uri = $request->url();
            $parts = [];
            foreach (explode('/', $uri) as $part) {
                if ($part != '') {
                    $parts[] = $part;
                }
            }

            // TODO: detect extension

            // TODO: run before filter

            // Walk through the URI and execute path callbacks
            foreach ($parts as $part) {
                // Try to find a callback array for the current URI part
                if (!array_key_exists('path', $this->currentCallbacks) || !array_key_exists($part, $this->currentCallbacks['path'])) {
                    // TODO: generate an exeception, and catch that.
                    return new Response(null, 404);
                }

                $c = $this->currentCallbacks['path'][$part];

                // Reset the current callback array, so the path callback 
                $this->currentCallbacks = [];

                // Execute path callback
                $rsp = $this->executeCallback($c, $request);

                // If there's already a response, return it and finish parsing the URL
                if ($rsp instanceOf Response) {
                    return $rsp;
                }
            }

            $method = $request->method();

            // The URI has been processed. Call the appropriate method callback
            if (!array_key_exists($method, $this->currentCallbacks)) {
                // Nope, we can't serve this URI, 405 Not Allowed
                return new Response(null, 405);
            }

            // There indeed is a method callback, so let's call it!
            $rsp = $this->executeCallback($this->currentCallbacks[$method], $request);

            // If there's a response, we can return it
            if ($rsp instanceOf Response) {
                return $rsp;
            }

            // TODO: formats?
            //return new Response(406); // Not acceptable format

            return new Response(null, 501); // Got no error, but got no response either. This is "Not Implemented".
        } finally {
            $this->currentCallbacks = &$currentCallbacks;
        }
    }

    public function resource($part, \Closure $callback)
    {
        $this->currentCallbacks['path'][$part] = $callback;
    }

    public function path($part, \Closure $callback)
    {
        $this->currentCallbacks['path'][$part] = $callback;
    }

    public function param($filter, \Closure $callback)
    {
        $this->currentCallbacks['param'][] = [$filter, $callback];
    }

    public function get(\Closure $callback)
    {
        $this->currentCallbacks['GET'] = $callback;
    }

    public function post(\Closure $callback)
    {
        $this->currentCallbacks['POST'] = $callback;
    }

    public function put(\Closure $callback)
    {
        $this->currentCallbacks['PUT'] = $callback;
    }

    public function delete(\Closure $callback)
    {
        $this->currentCallbacks['DELETE'] = $callback;
    }

    public function patch(\Closure $callback)
    {
        $this->currentCallbacks['PATCH'] = $callback;
    }

    public function options(\Closure $callback)
    {
        $this->currentCallbacks['OPTIONS'] = $callback;
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
