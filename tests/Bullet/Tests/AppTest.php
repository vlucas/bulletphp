<?php
namespace Bullet\Tests;
use Bullet;
use Bullet\Request;
use Bullet\Response;

/**
 * @backupGlobals disabled
 */
class AppTest extends \PHPUnit_Framework_TestCase
{
    protected $backupGlobalsBlacklist = array('app');

    public function testSinglePathGet()
    {
        $collect = [];

        $app = new Bullet\App();
        $app->path('', function() use(&$collect) {
            $this->path('test', function() use(&$collect) {
                $collect[] = 'test';
            });
        });

        $app->run(new Bullet\Request('GET', '/test/'));

        $expect = ['test'];
        $this->assertEquals($expect, $collect);
    }

    public function testSingleResourceGet()
    {
        $app = new Bullet\App();
        $app->path('', function() {
            $this->resource('test', function() {
                return 'resource';
            });
        });

        $res = $app->run(new Bullet\Request('GET', '/test/'));
        $this->assertEquals('resource', $res->content());
    }

    public function testDoublePathGetWithBranch()
    {
        $collect = array();

        $app = new Bullet\App();
        $app->path('', function() use($app, &$collect) {
            $app->path('test', function($request) use($app, &$collect) {
                $collect[] = 'test';
                $app->path('foo', function() use(&$collect) {
                    $collect[] = 'foo';
                });
                $app->path('foo2', function() use(&$collect) {
                    $collect[] = 'foo2';
                });
            });
        });

        $app->run(new Bullet\Request('GET', '/test/foo/bar/'));

        $expect = array('test', 'foo');
        $this->assertEquals($expect, $collect);
    }

    public function testDoublePathGetReturnValues()
    {
        $collect = array();

        $app = new Bullet\App();
        $app->path('', function() use($app, &$collect) {
            $app->path('test', function($request) use($app, &$collect) {
                $app->path('foo', function() use(&$collect) {
                    return 'foo';
                });
                $app->path('foo2', function() use(&$collect) {
                    return 'foo2';
                });
            });
        });

        $collect = $app->run(new Bullet\Request('GET', '/test/foo/'))->content();

        $expect = 'foo';
        $this->assertEquals($expect, $collect);
    }

    public function testNonmatchingPathRunReturns404()
    {
        $collect = array();

        $app = new Bullet\App();
        $app->path('', function() use($app, &$collect) {
            $app->path('test', function($request) use($app, &$collect) {
                $app->path('foo', function() use(&$collect) {
                });
                $app->path('foo2', function() use(&$collect) {
                });
            });
        });

        $actual = $app->run(new Bullet\Request('GET', '/test/foo/bar/'));
        $expected = 404;
        $this->assertEquals($expected, $actual->status());
    }

    public function test204HasNoBodyContent()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app, &$collect) {
            $app->path('test-no-content', function($request) use($app) {
                $app->get(function($request) {
                    return 204;
                });
            });
        });

        $res = $app->run(new Bullet\Request('GET', '/test-no-content'));
        $this->assertEquals('', $res->content());
        $this->assertEquals(204, $res->status());
    }

    public function testDoublePathPostOnly()
    {
        $collect = array();

        $app = new Bullet\App();
        $app->path('', function() use($app, &$collect) {
            $app->path('test', function($request) use($app, &$collect) {
                $collect[] = 'test';
                $app->path('foo', function() use($app, &$collect) {
                    $collect[] = 'foo';
                    $app->get(function() use(&$collect) {
                        $collect[] = 'get';
                    });
                    $app->post(function() use(&$collect) {
                        $collect[] = 'post';
                    });
                });
            });
        });

        $app->run(new Bullet\Request('POST', '/test/foo/'));

        $expected = array('test', 'foo', 'post');
        $this->assertEquals($expected, $collect);
    }

    public function testRootPathGet()
    {
        $collect = array();

        $app = new Bullet\App();
        $app->path('', function($request) use($app, &$collect) {
            $collect[] = 'root';
        });
        $app->path('notmatched', function($request) use($app, &$collect) {
            $collect[] = 'notmatched';
        });

        $app->run(new Bullet\Request('GET', '/'));

        $expected = array('root');
        $this->assertEquals($expected, $collect);
    }

    public function testExactPathMatchNotInParamCapture()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
            $app->path('ping', function($request) use($app) {
                $app->param($app::paramSlug(), function($request, $slug) use($app) {
                    return $slug;
                });

                return "pong";
            });
        });

        $response = $app->run(new Bullet\Request('GET', '/ping'));
        $this->assertEquals("pong", $response->content());
    }

    public function testRepeatPathnameNested()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('about', function($request) use ($app) {
				// return data for my "about" path
				return 'about';
			});

			$app->path('rels', function($request) use($app) {
				$app->param($app::paramSlug(), function($request, $rel) use($app) {
					// return the documentation page for /rels/{rel} such as /rels/about
					return 'rel/' . $rel;
				});
			});
		});

        $response = $app->run(new Bullet\Request('GET', '/rels/about/'));
        $this->assertEquals("rel/about", $response->content());
    }

    public function testRepeatPathnameNestedPathInsideParam()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('about', function($request) use ($app) {
				// return data for my "about" path
				return 'about';
			});

			$app->path('rels', function($request) use($app) {
				$app->param($app::paramSlug(), function($request, $rel) use($app) {
					$app->path('test', function($request) use ($app, $rel) {
						return 'rel/' . $rel . '/test';
					});
				});
			});
		});

        $response = $app->run(new Bullet\Request('GET', '/rels/about/test'));
        $this->assertEquals("rel/about/test", $response->content());
    }

    public function testGetHandlerBeforeParamCapture()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('ping', function($request) use($app) {
				$app->get(function($request) use($app) {
					return "pong";
				});

				$app->param($app::paramSlug(), function($request, $slug) use($app) {
					$app->get(function($request) use($slug) {
						return $slug;
					});
				});
			});
		});

        $response = $app->run(new Bullet\Request('GET', '/ping'));
        $this->assertEquals("pong", $response->content());
    }

    public function testParamCallbacksGetClearedAfterMatch()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('about', function() use ($app){
				$page = 'about';
				$app->get(function() use ($app,$page){
					return 'about';
				});

				$app->param($app::paramSlug(),function($request,$slug) use ($app,$page){
					$app->get(function() use ($app,$page,$slug){
						return $page.'-'.$slug;
					});
				});
			});
		});

        $response1 = $app->run(new Bullet\Request('GET', 'about/slug'));
        $this->assertEquals(200, $response1->status());
        $this->assertEquals('about-slug', $response1->content());
        $response2 = $app->run(new Bullet\Request('GET', 'about/slug/foo/bar'));
        $this->assertEquals(404, $response2->status());
        $this->assertEquals(null, $response2->content());
    }

    public function testDoubleSlugMethodHandler()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('ping', function($request) use($app) {
				$app->get(function($request) use($app) {
					return "pong";
				});

				$app->param($app::paramSlug(), function($request, $slug1) use($app) {
					$app->get(function($request) use($slug1) {
						return $slug1;
					});

					$app->param($app::paramSlug(), function($request, $slug2) use($app, $slug1) {
						$app->get(function($request) use($slug1, $slug2) {
							return $slug1 . "/" . $slug2;
						});
					});
				});
			});
		});

        $response = $app->run(new Bullet\Request('GET', '/ping/slug1'));
        $this->assertEquals("slug1", $response->content());
    }

    public function testStringReturnsBulletResponseInstanceWith200StatusCodeAndCorrectContent()
    {
        $collect = [];

        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('test', function($request) use($app, &$collect) {
				return 'test';
			});
		});

        $actual = $app->run(new Bullet\Request('GET', '/test/'));
        $this->assertInstanceOf('\Bullet\Response', $actual);
        $this->assertEquals(200, $actual->status());
        $this->assertEquals('test', $actual->content());
    }

    public function testReturnSHortCircuitsRequest()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('test', function($request) use($app) {
				$app->path('teapot', function($request) use($app) {
					// GET
					$app->get(function($request) use($app) {
						// Should not be returned
						return new \Bullet\Response(418, "Teapot");
					});

					// Should be returned
					return 'Nothing to see here...';
				});
			});
		});

        $actual = $app->run(new Bullet\Request('GET', '/test/teapot'));
        $this->assertInstanceOf('\Bullet\Response', $actual);
        $this->assertEquals('Nothing to see here...', $actual->content());
        $this->assertEquals(200, $actual->status());
    }

    public function testMethodHandlersNotInFullPathDoNotGetExecuted()
    {
        $actual = [];

        $app = new Bullet\App();
        $app->path('', function() use($app, &$actual) {
			$app->path('test', function($request) use($app, &$actual) {
				$app->path('teapot', function($request) use($app, &$actual) {
					// Should be executed
					$app->get(function($request) use($app, &$actual) {
						$actual[] = 'teapot';
					});
				});

				// Should NOT be executed (not FULL path)
				$app->get(function($request) use($app, &$actual) {
					$actual[] = 'notateapot';
				});
			});
		});

        $app->run(new Bullet\Request('GET', '/test/teapot'));
        $this->assertEquals(['teapot'], $actual);
    }

    public function testPathMethodCallbacksDoNotCarryOverIntoNextPath()
    {
        $actual = [];

        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('test', function($request) use($app, &$actual) {
				$app->path('method', function($request) use($app, &$actual) {
					// Should not be executed (not POST)
					$app->get(function($request) use($app, &$actual) {
						$actual[] = 'get';
					});
				});

				// Should NOT be executed (not FULL path)
				$app->post(function($request) use($app, &$actual) {
					$actual[] = 'post';
				});
			});
		});

        $response = $app->run(new Bullet\Request('post', 'test/method'));
        $this->assertEquals([], $actual);
    }

    public function testIfPathExistsAndMethodCallbackDoesNotResponseIs405()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('methodnotallowed', function($request) use($app) {
				// GET
				$app->get(function($request) use($app) {
					return 'get';
				});
				// POST
				$app->post(function($request) use($app) {
					return 'post';
				});
			});
		});

        $response = $app->run(new Bullet\Request('PUT', 'methodnotallowed'));
        $this->assertEquals(405, $response->status());
        $this->assertEquals('GET,POST', $response->header('Allow'));
    }

    public function testPathParamCaptureFirst()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('paramtest', function($request) use($app) {
				// Digit
				$app->param(function ($value) { return ctype_digit($value);}, function($request, $id) use($app) {
					return $id;
				});
				// Alphanumeric
				$app->param(function ($value) { return ctype_alnum($value);}, function($request, $slug) use($app) {
					return $slug;
				});
			});
		});

        $response = $app->run(new Bullet\Request('GET', 'paramtest/42'));
        $this->assertEquals(200, $response->status());
        $this->assertEquals('42', $response->content());
    }

    public function testPathParamCaptureSecond()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('paramtest', function($request) use($app) {
				// Digit
				$app->param(function ($value) { return ctype_digit($value);}, function($request, $id) use($app) {
					return $id;
				});
				// All printable characters except space
				$app->param(function ($value) { return ctype_graph($value);}, function($request, $slug) use($app) {
					return $slug;
				});
			});
		});

        $response = $app->run(new Bullet\Request('GET', 'paramtest/my-blog-post-title'));
        $this->assertEquals(200, $response->status());
        $this->assertEquals('my-blog-post-title', $response->content());
    }

    public function testPathParamCaptureWithMethodHandlers()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('paramtest', function($request) use($app) {
				// Digit
				$app->param($app::paramInt(), function($request, $id) use($app) {
					$app->get(function($request) use($id) {
						// View resource
						return 'view_' . $id;
					});

					$app->put(function($request) use($id) {
						// Update resource
						return 'update_' . $id;
					});
				});
				// All printable characters except space
				$app->param($app::paramSlug(), function($request, $slug) use($app) {
					return $slug;
				});
			});
		});

        $response = $app->run(new Bullet\Request('PUT', 'paramtest/546'));
        $this->assertEquals(200, $response->status());
        $this->assertEquals('update_546', $response->content());
    }

    public function testHandlersCanReturnIntergerAsHttpStatus()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('testint', function() use($app) {
				return 200;
			});
		});

        $response = $app->run(new Bullet\Request('GET', 'testint'));
        $this->assertEquals(200, $response->status());
        $this->assertEquals(null, $response->content());
    }

    public function testHandlersCanReturnIntergerAsHttpStatusInMethodCallback()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('testint2', function() use($app) {
				// GET
				$app->get(function($request) use($app) {
					return 429;
				});
			});
		});

        $response = $app->run(new Bullet\Request('GET', 'testint2'));
        $this->assertEquals(429, $response->status());
        $this->assertEquals(null, $response->content());
    }

    // TODO: this needs to be documented
    public function testResponseContentCanBeChangedAfterTheFact()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('testhandler', function() use($app) {
				// GET
				$app->get(function($request) use($app) {
					return 500;
				});
			});
		});

        $response = $app->run(new Bullet\Request('GET', 'testhandler'));

        if ($response->status() === 500) {
            $response->status(200)->content("Intercepted 500 Error");
        }

        $this->assertEquals(200, $response->status());
        $this->assertEquals("Intercepted 500 Error", $response->content());
    }

    public function testModifyingResponseStatusKeepsContent()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('testhandler', function() use($app) {
				$app->get(function($request) use($app) {
					return new \Bullet\Response("Oh Snap!", 500);
				});
			});
		});

        $response = $app->run(new Bullet\Request('GET', 'testhandler'));

        if ($response->status() === 500) {
            $response->status(204);
        }

        $this->assertEquals(204, $response->status());
        $this->assertEquals("Oh Snap!", $response->content());
    }

    public function testPathExecutionObservesExtensionsIfNoFormatHandlersArePresent()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('test', function($request) use($app) {
				$collect[] = 'test';
				$app->path('foo.json', function() use($app) {
					return 'Not JSON';
				});
			});
		});

        $response = $app->run(new Bullet\Request('GET', '/test/foo.json'));
        $this->assertEquals(200, $response->status());
        $this->assertEquals('Not JSON', $response->content());
    }

    public function testPathWithExtensionExecutesMatchingFormatHandler()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('test', function($request) use($app) {
				$collect[] = 'test';
				$app->path('foo', function() use($app) {
					$app->format('json', function() use($app) {
						return ['foo' => 'bar', 'bar' => 'baz'];
					});
					$app->format('html', function() use($app) {
						return '<tag>Some HTML</tag>';
					});
				});
			});
		});

        $response = $app->run(new Bullet\Request('GET', '/test/foo.json'));
        $this->assertEquals(200, $response->status());
        $this->assertEquals(json_encode(['foo' => 'bar', 'bar' => 'baz']), $response->content());
    }

    public function testPathWithExtensionAndFormatHandlersButNoMatchingFormatHandlerReturns406()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('test', function($request) use($app) {
				$app->path('foo', function() use($app) {
					$app->format('json', function() use($app) {
						return array('foo' => 'bar', 'bar' => 'baz');
					});
					$app->format('html', function() use($app) {
						return '<tag>Some HTML</tag>';
					});
				});
			});
		});

        $response = $app->run(new Bullet\Request('GET', '/test/foo.xml'));
        $this->assertEquals(406, $response->status());
    }

    public function testFormatHanldersRunUsingAcceptHeader()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('posts', function($request) use($app) {
				$app->get(function() use($app) {
					$app->format('json', function() use($app) {
						return array('listing' => 'something');
					});
				});
				$app->post(function($request) use($app) {
					$app->format('json', function() use($app) {
						return new \Bullet\Response(201, array('created' => 'something'));
					});
				});
			});
		});

        $request = new Bullet\Request('POST', 'posts', array(), array('Accept' => 'application/json'));
        $response = $app->run($request);
        $this->assertEquals(201, $response->status());
        $this->assertEquals('{"created":"something"}', $response->content());
    }

    public function testNestedRun()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('foo', function($request) use($app) {
				return "foo";
			});
			$app->path('bar', function($request) use($app) {
				$foo = $app->run(new Bullet\Request('GET', 'foo')); // $foo is now a `Bullet\Response` instance
				return $foo->content() . "bar";
			});
		});
        $response = $app->run(new Bullet\Request('GET', 'bar'));
        $this->assertEquals(200, $response->status());
        $this->assertEquals('foobar', $response->content());
    }

    public function testStatusOnResponseObjectReturnsCorrectStatus()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('posts', function($request) use($app) {
				$app->post(function() use($app) {
					return new \Bullet\Response(201, 'Created something!');
				});
			});
		});

        $actual = $app->run(new Bullet\Request('POST', 'posts'));
        $this->assertEquals(201, $actual->status());
    }

    public function testStatusOnResponseObjectReturnsCorrectStatusAsJSON()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('posts', function($request) use($app) {
				$app->post(function() use($app) {
					$app->format('json', function() use($app) {
						return new \Bullet\Response(201, array('created' => 'something'));
					});
				});
			});
		});

        $actual = $app->run(new Bullet\Request('POST', 'posts.json'));
        $this->assertEquals(201, $actual->status());
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage This is a specific error message here!
     */
    public function testExceptionIsNotCoughtWhenCallingRun_()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('test', function($request) use($app) {
				throw new \Exception("This is a specific error message here!");
			});
		});

        $response = $app->run_(new Bullet\Request('GET', 'test'));
    }

    public function testExceptionResponsesCanBeManipulated()
    {
        $app = new Bullet\App();

        $app->path('', function() {
			$this->path('test', function($request) use($app) {
				throw new \InvalidArgumentException("This is a specific error message here!");
			});
		});

        $response = $app->run(new Bullet\Request('POST', 'test'));

        if ($response->exception() instanceof \InvalidArgumentException) {
            $response->status(200)->content('There is a pankake on my head. Your argument is invalid.');
        }

        $this->assertEquals(200, $response->status());
        $this->assertEquals('There is a pankake on my head. Your argument is invalid.', $response->content());
    }

    public function testResponseContentCanBeSetAfterTheFact()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('testhandler', function() use($app) {
				$app->put(function($request) use($app) {
					return 'test';
				});
			});
		});

        $response = $app->run(new Bullet\Request('PUT', 'testhandler'));
        $response->content($response->content() . 'AFTER');

        $this->assertEquals('testAFTER', $response->content());
    }

    public function testSupportsPatch()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('update', function($request) use($app) {
				$app->patch(function($request) {
					return $request->params();
				});
			});
		});

        $params = ['foo' => 'bar', 'bar' => 'baz'];
        $request = new \Bullet\Request('PATCH', '/update', $params);
        $result = $app->run($request);

        $this->assertEquals('PATCH', $request->method());
        $this->assertEquals(json_encode($params), $result->content());
    }

    public function testSupportsHeadAsGetWithNoResponseBody()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('someroute', function($request) use($app) {
				$app->get(function($request) {
					return 'I am hidden with a HEAD request!';
				});
			});
		});

        $request = new \Bullet\Request('HEAD', '/someroute');
        $result = $app->run($request);

        $this->assertEquals('HEAD', $request->method());
        $this->assertEquals('', $result->content());
    }

    public function testSupportsHeadWithHandler()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->path('test', function($request) use($app) {
				$app->head(function($request) use($app) {
					return (new \Bullet\Response())->header('X-Special', 'Stuff');
				});
				$app->get(function($request) {
					return 'I am hidden with a HEAD request!';
				});
			});
		});

        $request = new \Bullet\Request('HEAD', '/test');
        $response = $app->run($request);
        $this->assertEquals('HEAD', $request->method());
        $this->assertEquals('Stuff', $response->header('X-Special'));
        $this->assertEquals('', $response->content());
    }

    public function testSupportCustomHeaders()
    {
        $app = new Bullet\App();
        $app->path('test', function($request) use($app) {
            $app->get(function($request) use($app) {
                return (new \Bullet\Response())
                    ->status(200)
                    ->header('X-Special', 'Stuff')
                    ->content('foo');
            });
        });

        $request = new \Bullet\Request('GET', '/test');
        $response = $app->run($request);
        $this->assertEquals('GET', $request->method());
        $this->assertEquals('Stuff', $response->header('X-Special'));
        $this->assertEquals('foo', $response->content());
    }

    public function testSubdomainRoute()
    {
        $app = new Bullet\App();
        $app->subdomain('test', function($request) use($app) {
            $app->path('', function($request) use($app) {
                $app->get(function($request) {
                    return "GET subdomain " . $request->subdomain();
                });
            });
        });

        $request = new \Bullet\Request('GET', '/', [], ['Host' => 'test.bulletphp.com']);
        $result = $app->run($request);

        $this->assertEquals(200, $result->status());
        $this->assertEquals("GET subdomain test", $result->content());
    }

    public function testSubdomainRouteAfterMethodHandler()
    {
        $app = new Bullet\App();
        $app->path('', function() use($app) {
			$app->get(function($request) {
				return "GET main path";
			});
		});
        $app->subdomain('bar', function($request) use($app) {
            $app->path('', function($request) use($app) {
                $app->get(function($request) {
                    return "GET subdomain " . $request->subdomain();
                });
            });
        });

        $request = new \Bullet\Request('GET', '/', array(), array('Host' => 'bar.bulletphp.com'));
        $result = $app->run($request);

        $this->assertEquals(200, $result->status());
        $this->assertEquals("GET subdomain bar", $result->content());
    }

    public function testSubdomainRouteWithPathsWithNoRequestSubdomain()
    {
        $app = new Bullet\App();
        $app->subdomain('bar', function($request) use($app) {
            $app->path('', function($request) use($app) {
                $app->get(function($request) {
                    return "GET subdomain " . $request->subdomain();
                });
            });
        });
        $app->path('', function($request) use($app) {
            $app->get(function($request) {
                return "GET main path";
            });
        });

        $request = new \Bullet\Request('GET', '/');
        $result = $app->run($request);

        $this->assertEquals(200, $result->status());
        $this->assertEquals("GET main path", $result->content());
    }

    public function testSubdomainRouteArray()
    {
        $app = new Bullet\App();
        $app->subdomain(['www', false], function($request) use($app) {
            $app->path('', function($request) use($app) {
                $app->get(function($request) {
                    return "GET www";
                });
            });
        });

        $request = new \Bullet\Request('GET', '/', [], ['Host' => 'www.bulletphp.com']);
        $result = $app->run($request);

        $this->assertEquals(200, $result->status());
        $this->assertEquals("GET www", $result->content());
    }

    public function testSubdomainFalseRouteWithNoSubdomain()
    {
        $app = new Bullet\App();
        $app->subdomain(false, function($request) use($app) {
            $app->path('', function($request) use($app) {
                $app->get(function($request) {
                    return "GET www";
                });
            });
        });

        $request = new \Bullet\Request('GET', '/', [], ['Host' => 'bulletphp.com']);
        $result = $app->run($request);

        $this->assertEquals(200, $result->status());
        $this->assertEquals("GET www", $result->content());
    }

    public function testDomainRoute()
    {
        $app = new Bullet\App();
        $app->domain('example.com', function($request) use($app) {
            $app->path('', function($request) use($app) {
                $app->get(function($request) {
                    return "GET " . $request->host();
                });
            });
        });

        $request = new \Bullet\Request('GET', '/', [], ['Host' => 'www.example.com']);
        $result = $app->run($request);

        $this->assertEquals(200, $result->status());
        $this->assertEquals("GET www.example.com", $result->content());
    }

    public function testDomainRoute404()
    {
        $app = new Bullet\App();
        $app->domain('example.com', function($request) use($app) {
            $app->path('', function($request) use($app) {
                $app->get(function($request) {
                    return "GET " . $request->host();
                });
            });
        });

        $request = new \Bullet\Request('GET', '/', [], ['Host' => 'www.example2.com']);
        $result = $app->run($request);

        $this->assertEquals(404, $result->status());
    }

    public function testDomainRoute404OnRequestedDomain()
    {
        $app = new Bullet\App();
        $app->domain('example.com', function($request) use($app) {
            $app->path('goodpath', function($request) use($app) {
                $app->get(function($request) {
                    return "GET " . $request->host();
                });
            });
        });

        $request = new \Bullet\Request('GET', '/badpath', [], ['Host' => 'www.example.com']);
        $result = $app->run($request);

        $this->assertEquals(404, $result->status());
        $this->assertEquals('Not Found', $result->content());
    }

    public function testDefaultArrayToJSONContentConverter()
    {
        $app = new Bullet\App();
        $app->path('', function($request) use($app) {
            return ['foo' => 'bar'];
        });

        $request = new \Bullet\Request('GET', '/');
        $result = $app->run($request);

        $this->assertEquals(json_encode(['foo' => 'bar']), $result->content());
        $this->assertEquals('application/json', $result->contentType());
    }

    public function testResponseWithNoContentConverterIsUnchanged()
    {
        $app = new Bullet\App();
        $app->path('', function($request) use($app) {
            return 'foobar';
        });

        $request = new \Bullet\Request('GET', '/');
        $result = $app->run($request);

        $this->assertEquals('foobar', $result->content());
        $this->assertEquals('text/html', $result->contentType());
    }

    public function testResponseOfSpecificClassGetsConverted()
    {
        $app = new Bullet\App();
        $app->path('', function($request) use($app) {
            return new TestHelper();
        });

        $app->registerResponseHandler(
            function($response) {
                return $response->content() instanceof TestHelper;
            },
            function($response) {
                $response->contentType('text/plain');
                $response->content($response->content()->something());
            }
        );

        $request = new \Bullet\Request('GET', '/');
        $result = $app->run($request);

        $this->assertEquals('something', $result->content());
        $this->assertEquals('text/plain', $result->contentType());
    }

    public function testThatAllApplicableResponseHandlersAreApplied()
    {
        $app = new Bullet\App();
        $app->path('', function($request) use($app) {
            return 'a';
        });

        // Always extend my response
        $app->registerResponseHandler(
            null,
            function($response) {
                $response->content($response->content() . 'b');
            }
        );

        // Further extend the response as condition returns true
        $app->registerResponseHandler(
            function($response) {
                return true;
            },
            function($response) {
                $response->content($response->content() . 'c');
            }
        );

        // Condition returns false so handler should not be applied
        $app->registerResponseHandler(
            function($response) {
                return false;
            },
            function($response) {
                $response->content($response->content() . 'd');
            }
        );

        $request = new \Bullet\Request('GET', '/');
        $result = $app->run($request);

        $this->assertEquals('abc', $result->content());
    }

    public function testThatUserResponseHandlersOverrideDefaults()
    {
        $app = new Bullet\App();
        $app->path('', function($request) use($app) {
            return array('a');
        });

        $app->registerResponseHandler(
            function($response) {
                return is_array($response->content());
            },
            function($response) {
                $response->contentType('text/plain');
                $response->content('this is not json');
            },
            'array_json'
        );

        $request = new \Bullet\Request('GET', '/');
        $result = $app->run($request);

        $this->assertEquals('this is not json', $result->content());
        $this->assertEquals('text/plain', $result->contentType());
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Third argument to Bullet\App::registerResponseHandler must be a string. Given argument was not a string.
     */
    public function testThatResponseHandlerNamesMustBeAString()
    {
        $app = new Bullet\App();
        $app->registerResponseHandler(
            function($response) { return true; },
            function($response) {},
            123
        );
    }

    public function testThatDefaultResponseHandlerMayBeRemoved()
    {
        $app = new Bullet\App();
        $app->path('', function($request) use($app) {
            return ['a'];
        });

        $app->removeResponseHandler('array_json');

        $request = new \Bullet\Request('GET', '/');
        $result = $app->run($request);

        $this->assertEquals(array('a'), $result->content());
    }

    public function testThatUserResponseHandlersMayBeRemoved()
    {
        $app = new Bullet\App();
        $app->path('', function($request) use($app) {
            return 'foo';
        });
        $request = new \Bullet\Request('GET', '/');

        $app->registerResponseHandler(
            function() { return true; },
            function($response) {
                $response->content('bar');
            },
            'foo'
        );

        $result = $app->run($request);
        $this->assertEquals('bar', $result->content());

        $app->removeResponseHandler('foo');
        $result = $app->run($request);
        $this->assertEquals('foo', $result->content());
    }

    public function testThatIndexedResponseHandlersMayBeRemoved()
    {
        $app = new Bullet\App();
        $app->path('', function($request) use($app) {
            return 'foo';
        });
        $request = new \Bullet\Request('GET', '/');

        $app->registerResponseHandler(
            function() { return true; },
            function($response) {
                $response->content('bar');
            }
        );

        $result = $app->run($request);
        $this->assertEquals('bar', $result->content());

        $app->removeResponseHandler(0);
        $result = $app->run($request);
        $this->assertEquals('foo', $result->content());
    }

    public function testNestedRoutesInsideParamCallback()
    {
        $app = new Bullet\App();

        $app->path('', function() use($app) {
			$app->path('admin', function($request) use($app) {
				$app->path('client', function($request) use($app) {
					$app->param($app::paramInt(), function($request,$id) use($app){
						$app->path('toggleVisiblity', function($request) use($app,$id) {
							$app->path('item', function($request) use($app,$id) {
								$app->get(function($request)use($app){
									return "deep";
								});
							});
						});
					});
				});
			});
		});

        $result = $app->run(new Bullet\Request('GET', '/admin/client/1/toggleVisiblity/item'));
        $this->assertEquals('deep', $result->content());
    }

    public function testHMVCNestedRouting()
    {
        $app = new Bullet\App();

        $app->path('', function() use($app) {
			$app->path('a', function($request) use($app) {
				$app->path('b', function($request) use($app) {
					return 'a/b';
				});
			});

			$app->path('c', function($request) use($app) {
				$app->path('a', function($request) use($app) {
					$app->path('b', function($request) use($app) {
						$a = $app->run(new Bullet\Request('GET', 'a/b'));
						return $a->content() . " + c/a/b";
					});
				});
			});
		});

        $result = $app->run(new Bullet\Request('GET', 'c/a/b'));
        $this->assertEquals('a/b + c/a/b', $result->content());
    }

    public function testHMVCNestedRoutingWithSubdomain()
    {
        $app = new Bullet\App();

        $app->subdomain('test', function($request) use($app) {
			$app->path('', function() use($app) {
				$app->path('a', function($request) use($app) {
					$app->path('b', function($request) use($app) {
						return 'a/b';
					});
				});

				$app->path('c', function($request) use($app) {
					$app->path('a', function($request) use($app) {
						$app->path('b', function($request) use($app) {
							$request = new \Bullet\Request('GET', 'a/b', array(), array('Host' => 'test.bulletphp.com'));
							$a = $app->run($request);
							return $a->content() . " + c/a/b";
						});
					});
				});
			});
        });

        $request = new \Bullet\Request('GET', 'c/a/b', array(), array('Host' => 'test.bulletphp.com'));
        $result = $app->run($request);
        $this->assertEquals('a/b + c/a/b', $result->content());
    }

    public function testClosureBinding()
    {
        $app = new Bullet\App();

        $app->path('closure-binding', function($request) {
            return $this->url('/worked/');
        });

        $result = $app->run(new Bullet\Request('GET', '/closure-binding/'));
        $this->assertEquals('cli:/worked/', $result->content());
    }
}

class TestHelper
{
    public function something()
    {
        return "something";
    }
}
