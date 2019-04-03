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
        $app->path('test', function() use(&$collect) {
            $collect[] = 'test';
        });

        $app->run(new Bullet\Request('GET', '/test/'));

        $expect = ['test'];
        $this->assertEquals($expect, $collect);
    }

    public function testSingleResourceGet()
    {
        $app = new Bullet\App();
        $app->resource('test', function() {
            return 'resource';
        });

        $res = $app->run(new Bullet\Request('GET', '/test/'));
        $this->assertEquals('resource', $res->content());
    }

    public function testDoublePathGetWithBranch()
    {
        $collect = array();

        $app = new Bullet\App();
        $app->path('test', function($request) use(&$collect) {
            $collect[] = 'test';
            $this->path('foo', function() use(&$collect) {
                $collect[] = 'foo';
            });
            $this->path('foo2', function() use(&$collect) {
                $collect[] = 'foo2';
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
        $app->path('test', function($request) use(&$collect) {
            $this->path('foo', function() use(&$collect) {
                return 'foo';
            });
            $this->path('foo2', function() use(&$collect) {
                return 'foo2';
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
        $app->path('test', function($request) use(&$collect) {
            $this->path('foo', function() use(&$collect) {
            });
            $this->path('foo2', function() use(&$collect) {
            });
        });

        $actual = $app->run(new Bullet\Request('GET', '/test/foo/bar/'));
        $expected = 404;
        $this->assertEquals($expected, $actual->status());
    }

    public function test204HasNoBodyContent()
    {
        $app = new Bullet\App();
        $app->path('test-no-content', function($request) {
            $this->get(function($request) {
                return 204;
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
        $app->path('test', function($request) use(&$collect) {
            $collect[] = 'test';
            $this->path('foo', function() use(&$collect) {
                $collect[] = 'foo';
                $this->get(function() use(&$collect) {
                    $collect[] = 'get';
                });
                $this->post(function() use(&$collect) {
                    $collect[] = 'post';
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
        $app->path('', function($request) use(&$collect) {
            $collect[] = 'root';
        });
        $app->path('notmatched', function($request) use(&$collect) {
            $collect[] = 'notmatched';
        });

        $app->run(new Bullet\Request('GET', '/'));

        $expected = array('root');
        $this->assertEquals($expected, $collect);
    }

    public function testExactPathMatchNotInParamCapture()
    {
        $app = new Bullet\App();
        $app->path('ping', function($request) {
            $this->param($this::paramSlug(), function($request, $slug) {
                return $slug;
            });

            return "pong";
        });

        $response = $app->run(new Bullet\Request('GET', '/ping'));
        $this->assertEquals("pong", $response->content());
    }

    public function testRepeatPathnameNested()
    {
        $app = new Bullet\App();
		$app->path('about', function($request) {
			// return data for my "about" path
			return 'about';
		});

		$app->path('rels', function($request) {
			$this->param($this::paramSlug(), function($request, $rel) {
				// return the documentation page for /rels/{rel} such as /rels/about
				return 'rel/' . $rel;
			});
		});

        $response = $app->run(new Bullet\Request('GET', '/rels/about/'));
        $this->assertEquals("rel/about", $response->content());
    }

    public function testRepeatPathnameNestedPathInsideParam()
    {
        $app = new Bullet\App();
		$app->path('about', function($request) {
			// return data for my "about" path
			return 'about';
		});

		$app->path('rels', function($request) {
			$this->param($this::paramSlug(), function($request, $rel) {
				$this->path('test', function($request) use ($rel) {
					return 'rel/' . $rel . '/test';
				});
			});
		});

        $response = $app->run(new Bullet\Request('GET', '/rels/about/test'));
        $this->assertEquals("rel/about/test", $response->content());
    }

    public function testGetHandlerBeforeParamCapture()
    {
        $app = new Bullet\App();
		$app->path('ping', function($request) {
			$this->get(function($request) {
				return "pong";
			});

			$this->param($this::paramSlug(), function($request, $slug) {
				$this->get(function($request) use($slug) {
					return $slug;
				});
			});
		});

        $response = $app->run(new Bullet\Request('GET', '/ping'));
        $this->assertEquals("pong", $response->content());
    }

    public function testParamCallbacksGetClearedAfterMatch()
    {
        $app = new Bullet\App();
		$app->path('about', function() {
			$page = 'about';
			$this->get(function() use ($page) {
				return 'about';
			});

			$this->param($this::paramSlug(),function($request,$slug) use ($page){
				$this->get(function() use ($page,$slug){
					return $page.'-'.$slug;
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
		$app->path('ping', function($request) {
			$this->get(function($request) {
				return "pong";
			});

			$this->param($this::paramSlug(), function($request, $slug1) {
				$this->get(function($request) use($slug1) {
					return $slug1;
				});

				$this->param($this::paramSlug(), function($request, $slug2) use($slug1) {
					$this->get(function($request) use($slug1, $slug2) {
						return $slug1 . "/" . $slug2;
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
		$app->path('test', function($request) use(&$collect) {
			return 'test';
		});

        $actual = $app->run(new Bullet\Request('GET', '/test/'));
        $this->assertInstanceOf('\Bullet\Response', $actual);
        $this->assertEquals(200, $actual->status());
        $this->assertEquals('test', $actual->content());
    }

    public function testReturnSHortCircuitsRequest()
    {
        $app = new Bullet\App();
		$app->path('test', function($request) {
			$this->path('teapot', function($request) {
				// GET
				$this->get(function($request) {
					// Should not be returned
					return new \Bullet\Response("Teapot", 418);
				});

				// Should be returned
				return 'Nothing to see here...';
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
		$app->path('test', function($request) use(&$actual) {
			$this->path('teapot', function($request) use(&$actual) {
				// Should be executed
				$this->get(function($request) use(&$actual) {
					$actual[] = 'teapot';
				});
			});

			// Should NOT be executed (not FULL path)
			$this->get(function($request) use(&$actual) {
				$actual[] = 'notateapot';
			});
		});

        $app->run(new Bullet\Request('GET', '/test/teapot'));
        $this->assertEquals(['teapot'], $actual);
    }

    public function testPathMethodCallbacksDoNotCarryOverIntoNextPath()
    {
        $actual = [];

        $app = new Bullet\App();
		$app->path('test', function($request) use(&$actual) {
			$this->path('method', function($request) use(&$actual) {
				// Should not be executed (not POST)
				$this->get(function($request) use(&$actual) {
					$actual[] = 'get';
				});
			});

			// Should NOT be executed (not FULL path)
			$this->post(function($request) use(&$actual) {
				$actual[] = 'post';
			});
		});

        $response = $app->run(new Bullet\Request('post', 'test/method'));
        $this->assertEquals([], $actual);
    }

    public function testIfPathExistsAndMethodCallbackDoesNotResponseIs405()
    {
        $app = new Bullet\App();
		$app->path('methodnotallowed', function($request) {
			$this->get(function($request) {
				return 'get';
			});
			$this->post(function($request) {
				return 'post';
			});
		});

        $response = $app->run(new Bullet\Request('PUT', 'methodnotallowed'));
        $this->assertEquals(405, $response->status());
        $this->assertEquals('GET,POST,OPTIONS', $response->header('Allow'));
    }

    public function testPathParamCaptureFirst()
    {
        $app = new Bullet\App();
		$app->path('paramtest', function($request) {
			// Digit
			$this->param(function ($request, $value) { return ctype_digit($value);}, function($request, $id) {
				return $id;
			});
			// Alphanumeric
			$this->param(function ($request, $value) { return ctype_alnum($value);}, function($request, $slug) {
				return $slug;
			});
		});

        $response = $app->run(new Bullet\Request('GET', 'paramtest/42'));
        $this->assertEquals(200, $response->status());
        $this->assertEquals('42', $response->content());
    }

    public function testPathParamCaptureSecond()
    {
        $app = new Bullet\App();
		$app->path('paramtest', function($request) {
			// Digit
			$this->param(function ($request, $value) { return ctype_digit($value);}, function($request, $id) {
				return $id;
			});
			// All printable characters except space
			$this->param(function ($request, $value) { return ctype_graph($value);}, function($request, $slug) {
				return $slug;
			});
		});

        $response = $app->run(new Bullet\Request('GET', 'paramtest/my-blog-post-title'));
        $this->assertEquals(200, $response->status());
        $this->assertEquals('my-blog-post-title', $response->content());
    }

    public function testPathParamCaptureWithMethodHandlers()
    {
        $app = new Bullet\App();
		$app->path('paramtest', function($request) {
			// Digit
			$this->param($this::paramInt(), function($request, $id) {
				$this->get(function($request) use($id) {
					// View resource
					return 'view_' . $id;
				});

				$this->put(function($request) use($id) {
					// Update resource
					return 'update_' . $id;
				});
			});
			// All printable characters except space
			$this->param($this::paramSlug(), function($request, $slug) {
				return $slug;
			});
		});

        $response = $app->run(new Bullet\Request('PUT', 'paramtest/546'));
        $this->assertEquals(200, $response->status());
        $this->assertEquals('update_546', $response->content());
    }

    public function testHandlersCanReturnIntergerAsHttpStatus()
    {
        $app = new Bullet\App();
		$app->path('testint', function() {
			return 200;
		});

        $response = $app->run(new Bullet\Request('GET', 'testint'));
        $this->assertEquals(200, $response->status());
        $this->assertEquals(null, $response->content());
    }

    public function testHandlersCanReturnIntergerAsHttpStatusInMethodCallback()
    {
        $app = new Bullet\App();
		$app->path('testint2', function() {
			$this->get(function($request) {
				return 429;
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
		$app->path('testhandler', function() {
			$this->get(function($request) {
				return 500;
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
		$app->path('testhandler', function() {
			$this->get(function($request) {
				return new \Bullet\Response("Oh Snap!", 500);
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
		$app->path('test', function($request) {
			$collect[] = 'test';
			$this->path('foo.json', function() {
				return 'Not JSON';
			});
		});

        $response = $app->run(new Bullet\Request('GET', '/test/foo.json'));
        $this->assertEquals(200, $response->status());
        $this->assertEquals('Not JSON', $response->content());
    }

    public function testPathWithExtensionExecutesMatchingFormatHandler()
    {
        $app = new Bullet\App();
		$app->path('test', function($request) {
			$collect[] = 'test';
			$this->path('foo', function() {
                $this->get(function() {
                    $this->format('json', function() {
						return ['foo' => 'bar', 'bar' => 'baz'];
					});
					$this->format('html', function() {
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
		$app->path('test', function($request) {
			$this->path('foo', function() {
                $this->get(function () {
                    $this->format('json', function() {
						return array('foo' => 'bar', 'bar' => 'baz');
					});
					$this->format('html', function() {
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
		$app->path('posts', function($request) {
			$this->get(function() {
				$this->format('json', function() {
					return ['listing' => 'something'];
				});
			});
			$this->post(function($request) {
				$this->format('json', function() {
					return \Bullet\Response::make(['created' => 'something'], 201);
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
		$app->path('foo', function($request) {
			return 'foo';
		});
		$app->path('bar', function($request) {
			$foo = $this->run_(new Bullet\Request('GET', 'foo')); // $foo is now a `Bullet\Response` instance
			return $foo->content() . 'bar';
		});
        $response = $app->run(new Bullet\Request('GET', 'bar'));
        $this->assertEquals(200, $response->status());
        $this->assertEquals('foobar', $response->content());
    }

    public function testStatusOnResponseObjectReturnsCorrectStatus()
    {
        $app = new Bullet\App();
		$app->path('posts', function($request) {
			$this->post(function() {
				return new \Bullet\Response('Created something!', 201);
			});
		});

        $actual = $app->run(new Bullet\Request('POST', 'posts'));
        $this->assertEquals(201, $actual->status());
    }

    public function testStatusOnResponseObjectReturnsCorrectStatusAsJSON()
    {
        $app = new Bullet\App();
		$app->path('posts', function($request) {
			$this->post(function() {
				$this->format('json', function() {
					return new \Bullet\Response(array('created' => 'something'), 201);
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
		$app->path('test', function($request) {
			throw new \Exception("This is a specific error message here!");
		});

        $response = $app->run_(new Bullet\Request('GET', 'test'));
    }

    public function testExceptionResponsesCanBeManipulated()
    {
        $app = new Bullet\App();

		$app->path('test', function($request) {
			throw new \InvalidArgumentException("This is a specific error message here!");
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
		$app->path('testhandler', function() {
			$this->put(function($request) {
				return 'test';
			});
		});

        $response = $app->run(new Bullet\Request('PUT', 'testhandler'));
        $response->content($response->content() . 'AFTER');

        $this->assertEquals('testAFTER', $response->content());
    }

    public function testSupportsPatch()
    {
        $app = new Bullet\App();
		$app->path('update', function($request) {
			$this->patch(function($request) {
				return $request->params();
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
		$app->path('someroute', function($request) {
			$this->get(function($request) {
				return 'I am hidden with a HEAD request!';
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
		$app->path('test', function($request) {
			$this->head(function($request) {
				return (new \Bullet\Response())->header('X-Special', 'Stuff');
			});
			$this->get(function($request) {
				return 'I am hidden with a HEAD request!';
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
        $app->path('test', function($request) {
            $this->get(function($request) {
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
        $app->subdomain('test', function($request) {
            $this->path('', function($request) {
                $this->get(function($request) {
                    return "GET subdomain " . $request->subdomain();
                });
            });
        });

        $request = new \Bullet\Request('GET', '/', [], ['Host' => 'test.bulletphp.com']);
        $result = $app->run_($request);

        $this->assertEquals(200, $result->status());
        $this->assertEquals("GET subdomain test", $result->content());
    }

    public function testSubdomainRouteAfterMethodHandler()
    {
        $app = new Bullet\App();
        $app->subdomain('bar', function($request) {
            $this->path('', function($request) {
                $this->get(function($request) {
                    return "GET subdomain " . $request->subdomain();
                });
            });
        });
        $app->path('', function() {
			$this->get(function($request) {
				return "GET main path";
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
        $app->subdomain('bar', function($request) {
            $this->path('', function($request) {
                $this->get(function($request) {
                    return "GET subdomain " . $request->subdomain();
                });
            });
        });
        $app->path('', function($request) {
            $this->get(function($request) {
                return "GET main path";
            });
        });

        $request = new \Bullet\Request('GET', '/');
        $result = $app->run($request);

        $this->assertEquals(200, $result->status());
        $this->assertEquals("GET main path", $result->content());
    }

    public function testSubdomainFalseRouteWithNoSubdomain()
    {
        $app = new Bullet\App();
        $app->subdomain(false, function($request) {
            $this->path('', function($request) {
                $this->get(function($request) {
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
        $app->domain('www.example.com', function($request) {
            $this->path('', function($request) {
                $this->get(function($request) {
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
        $app->domain('example.com', function($request) {
            $this->path('', function($request) {
                $this->get(function($request) {
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
        $app->domain('www.example.com', function($request) {
            $this->path('goodpath', function($request) {
                $this->get(function($request) {
                    return "GET " . $request->host();
                });
            });
        });

        $request = new \Bullet\Request('GET', '/badpath', [], ['Host' => 'www.example.com']);
        $result = $app->run($request);

        $this->assertEquals(404, $result->status());
        $this->assertEquals(null, $result->content());
    }

    public function testDefaultArrayToJSONContentConverter()
    {
        $app = new Bullet\App();
        $app->path('', function($request) {
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
        $app->path('', function($request) {
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
        $app->path('', function($request) {
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
        $app->path('', function($request) {
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
        $app->path('', function($request) {
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
        $app->path('', function($request) {
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
        $app->path('', function($request) {
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
        $app->path('', function($request) {
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

		$app->path('admin', function($request) {
			$this->path('client', function($request) {
				$this->param($this::paramInt(), function($request,$id) {
					$this->path('toggleVisiblity', function($request) {
						$this->path('item', function($request) {
							$this->get(function($request) {
								return "deep";
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

		$app->path('a', function($request) {
			$this->path('b', function($request) {
				return 'a/b';
			});
		});

		$app->path('c', function($request) {
			$this->path('a', function($request) {
				$this->path('b', function($request) {
					$a = $this->run(new Bullet\Request('GET', 'a/b'));
					return $a->content() . " + c/a/b";
				});
			});
		});

        $result = $app->run(new Bullet\Request('GET', 'c/a/b'));
        $this->assertEquals('a/b + c/a/b', $result->content());
    }

    public function testHMVCNestedRoutingWithSubdomain()
    {
        $app = new Bullet\App();

        $app->subdomain('test', function($request) {
			$this->path('a', function($request) {
				$this->path('b', function($request) {
					return 'a/b';
				});
			});

			$this->path('c', function($request) {
				$this->path('a', function($request) {
					$this->path('b', function($request) {
						$request = new \Bullet\Request('GET', 'a/b', array(), array('Host' => 'test.bulletphp.com'));
						$a = $this->run($request);
						return $a->content() . " + c/a/b";
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
            return 'worked';
        });

        $result = $app->run(new Bullet\Request('GET', '/closure-binding/'));
        $this->assertEquals('worked', $result->content());
    }
}

class TestHelper
{
    public function something()
    {
        return "something";
    }
}
