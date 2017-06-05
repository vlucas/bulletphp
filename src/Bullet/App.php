<?php

namespace Bullet;

use Pimple\Container;

class App extends Container
{
    protected $rootCallbacks;
    protected $currentCallbacks;
    protected $exceptionHandler;

    public function __construct()
    {
        $this->rootCallbacks = [];
        $this->currentCallbacks = &$this->rootCallbacks;
    }

    protected function executeCallback(\Closure $c, array $params = [])
    {
        $c = \Closure::bind($c, $this);
		$response = call_user_func_array($c, $params);

        if ($response === null || $response instanceOf Response) {
            return $response;
        }

        if (is_string($response)) {
            return new Response($response, 200);
        }

        if (is_int($response)) {
            return new Response(null, $response);
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

        $response = null;
        try {
            // Remove empty path elements
            $uri = $request->url();
            $parts = [''];
            foreach (explode('/', $uri) as $part) {
                if ($part != '') {
                    $parts[] = $part;
                }
            }

            // TODO: detect extension

            // TODO: run before filter

            // Walk through the URI and execute path callbacks
            foreach ($parts as $part) {
				//print "$part ";
				// Try to find a callback array for the current URI part
                if (array_key_exists('path', $this->currentCallbacks) && array_key_exists($part, $this->currentCallbacks['path'])) {
					//print "PATH\n";
					// Let $c be the callback that has to be run now.
					$c = $this->currentCallbacks['path'][$part];

					// Reset the current callback array, so the path callbacks can get a clean slate
					$this->currentCallbacks = [];

					// Execute path callback
					$response = $this->executeCallback($c, [$request]);

					// If there's already a response, return it and finish parsing the URL
					if ($response instanceOf Response) {
						return $response;
					}
                }
				// Try to find a param match
                elseif (array_key_exists('param', $this->currentCallbacks)) {
					//print "PARAM \n";
					// Let $c be the callback that has to be run now.
					// This needs a linear search trhough the param filters
					$c = null;
					foreach ($this->currentCallbacks['param'] as $filterCallbackTuple) {
						if ($filterCallbackTuple[0]($part)) {
							$c = $filterCallbackTuple[1];
							break;
						}
					}
					if ($c instanceOf \Closure) {
						// Reset the current callback array, so the path callbacks can get a clean slate
						$this->currentCallbacks = [];

						// Execute callback
						$response = $this->executeCallback($c, [$request, $part]);

						// If there's already a response, return it and finish parsing the URL
						if ($response instanceOf Response) {
							return $response;
						}
					} else {
						return new Response(null, 404);
					}
                } else {
					//print "404\n";
					return new Response(null, 404);
				}

			}

            $method = $request->method();

            // The URI has been processed. Call the appropriate method callback
            if (!array_key_exists($method, $this->currentCallbacks)) {
                // Nope, we can't serve this URI, 405 Not Allowed
                return new Response(null, 405, ['Allow' => implode(',', array_keys($this->currentCallbacks))]);
            }

            // There indeed is a method callback, so let's call it!
            $response = $this->executeCallback($this->currentCallbacks[$method], [$request]);

            // If there's a response, we can return it
            if ($response instanceOf Response) {
                return $response;
            }
            //print "DONE";

            // TODO: formats?
            //return new Response(406); // Not acceptable format

            return new Response(null, 501); // Got no error, but got no response either. This is "Not Implemented".
        } catch (\Exception $e) {
			//var_dump($e->getMessage());
            if ($response instanceOf \Bullet\Response) {
                $response->status(500);
            } else {
                $response = new \Bullet\Response(null, 500);
            }
            if (is_callable($this->exceptionHandler)) {
                $eh = $this->exceptionHandler;
                $eh($request, $response, $e);
            }
            return $response;
        } finally {
            $this->currentCallbacks = &$currentCallbacks;
        }
    }

    public function resource(string $part, \Closure $callback)
    {
        $this->currentCallbacks['path'][$part] = $callback;
    }

    public function path($part, \Closure $callback)
    {
        $this->currentCallbacks['path'][$part] = $callback;
    }

	/**
	 * Param match has lower priority than path match
	 * 
	 * e.g. if a path section matches, then the search concludes
	 * the current segment and params won't even be searched for a
	 * match.
	 */
    public function param(\Closure $filter, \Closure $callback)
    {
        $this->currentCallbacks['param'][] = [$filter, $callback];
    }

    public function get(\Closure $callback)
    {
        $this->currentCallbacks['GET'] = $callback;
    }

    public function head(\Closure $callback)
    {
        $this->currentCallbacks['HEAD'] = $callback;
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

    public function exception(\Closure $callback)
    {
        $this->exceptionHandler = $callback;
    }

    public function on($event, \Closure $callback)
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

    /**
     * Handle HTTP content type as output format
     *
     * @param string $format HTTP content type format to handle for
     * @param \Closure $callback Closure to execute to handle specified format
     */
    public function format($formats, \Closure $callback)
    {
        return $this;
    }

    /**
     * Build URL for path
     */
    public function url($path = null)
    {
        // TODO: is this really necessary?
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

    public static function paramInt()
    {
		return function($value) {
            return filter_var($value, FILTER_VALIDATE_INT);
        };
    }

    public static function paramFloat()
	{
		return function($value) {
            return filter_var($value, FILTER_VALIDATE_FLOAT);
        };
    }

	/**
	 * 
     * True = "1", "true", "on", "yes"
     * False = "0", "false", "off", "no"
	 */
    public static function paramBoolean()
	{
		return function($value) {
            $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            return (!empty($filtered) && $filtered !== null);
        };
    }

    public static function paramSlug()
	{
		return function($value) {
            return (preg_match("/[a-zA-Z0-9-_]/", $value) > 0);
        };
    }

    public static function paramEmail()
	{
		return function($value) {
            return filter_var($value, FILTER_VALIDATE_EMAIL);
        };
    }
}
