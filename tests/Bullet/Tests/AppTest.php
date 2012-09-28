<?php
namespace Bullet\Tests;
use Bullet;

class AppTest extends \PHPUnit_Framework_TestCase
{
    public function testSinglePathGet()
    {
        $collect = array();

        $app = new Bullet\App();
        $app->path('test', function() use(&$collect) {
            $collect[] = 'test';
        });

        $app->run('GET', '/test/');

        $expect = array('test');
        $this->assertEquals($collect, $expect);
    }

    public function testMultiplePathsWithAray()
    {
        $app = new Bullet\App();
        $app->path(array('test', 'test2'), function() use($app) {
            return "test";
        });

        $res1 = $app->run('GET', '/test/');
        $res2 = $app->run('GET', '/test2/');
        $this->assertEquals($res1->content(), $res2->content());
    }

    public function testDoublePathGetWithBranch()
    {
        $collect = array();

        $app = new Bullet\App();
        $app->path('test', function($request) use($app, &$collect) {
            $collect[] = 'test';
            $app->path('foo', function() use(&$collect) {
                $collect[] = 'foo';
            });
            $app->path('foo2', function() use(&$collect) {
                $collect[] = 'foo2';
            });
        });

        $app->run('GET', '/test/foo/bar/');

        $expect = array('test', 'foo');
        $this->assertEquals($collect, $expect);
    }

    public function testDoublePathGetReturnValues()
    {
        $collect = array();

        $app = new Bullet\App();
        $app->path('test', function($request) use($app, &$collect) {
            $app->path('foo', function() use(&$collect) {
                return 'foo';
            });
            $app->path('foo2', function() use(&$collect) {
                return 'foo2';
            });
            return 'test';
        });

        $collect = (string) $app->run('GET', '/test/foo/');

        $expect = 'foo';
        $this->assertEquals($collect, $expect);
    }

    public function testNonmatchingPathRunReturns404()
    {
        $collect = array();

        $app = new Bullet\App();
        $app->path('test', function($request) use($app, &$collect) {
            $app->path('foo', function() use(&$collect) {
                return 'foo';
            });
            $app->path('foo2', function() use(&$collect) {
                return 'foo2';
            });
            return 'test';
        });

        $actual = $app->run('GET', '/test/foo/bar/');
        $expected = 404;

        $this->assertEquals($expected, $actual->status());
    }

    public function testDoublePathPostOnly()
    {
        $collect = array();

        $app = new Bullet\App();
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

        $app->run('post', '/test/foo/');

        $expected = array('test', 'foo', 'post');
        $this->assertEquals($expected, $collect);
    }

    public function testRootPathGet()
    {
        $collect = array();

        $app = new Bullet\App();
        $app->path('/', function($request) use($app, &$collect) {
            $collect[] = 'root';
        });
        $app->path('notmatched', function($request) use($app, &$collect) {
            $collect[] = 'notmatched';
        });

        $app->run('GET', '/');

        $expected = array('root');
        $this->assertEquals($expected, $collect);
    }

    public function testLeadingAndTrailingSlashesInPathGetStripped()
    {
        $collect = array();

        $app = new Bullet\App();
        $app->path('/match/', function($request) use($app, &$collect) {
            $collect[] = 'matched';
            $app->path('/me/', function($request) use($app, &$collect) {
                $collect[] = 'me';
            });
        });
        $app->path('/notmatched/', function($request) use($app, &$collect) {
            $collect[] = 'notmatched';
        });

        $app->run('GET', 'match/me');

        $expected = array('matched', 'me');
        $this->assertEquals($expected, $collect);
    }

    public function testHelperIsRequestPathReturnsTrueWhenPathIsCurrent()
    {
        $app = new Bullet\App();
        $app->path('test', function($request) use($app, &$collect) {
            return var_export($app->isRequestPath(), true);
        });

        $actual = $app->run('GET', '/test/');
        $this->assertEquals('true', $actual->content());
    }

    public function testHelperIsRequestPathReturnsFalseWhenPathIsNotCurrent()
    {
        $actual = array();

        $app = new Bullet\App();
        $app->path('foo', function($request) use($app, &$actual) {
            // Should be 'false' - "foo" is not the full requested path, "foo/bar" is
            $actual[] = var_export($app->isRequestPath(), true);

            $app->path('bar', function($request) use($app) {
                return 'anything at all';
            });
        });

        $app->run('GET', 'foo/bar');
        $this->assertEquals(array('false'), $actual);
    }

    public function testStringReturnsBulletResponseInstanceWith200StatusCodeAndCorrectContent()
    {
        $collect = array();

        $app = new Bullet\App();
        $app->path('test', function($request) use($app, &$collect) {
            return 'test';
        });

        $actual = $app->run('GET', '/test/');
        $this->assertInstanceOf('\Bullet\Response', $actual);
        $this->assertEquals(200, $actual->status());
        $this->assertEquals('test', $actual->content());
    }

    public function testOnlyLastMatchedCallbackIsReturned()
    {
        $app = new Bullet\App();
        $app->path('test', function($request) use($app) {
            $app->path('teapot', function($request) use($app) {
                // GET
                $app->get(function($request) use($app) {
                    // Should be returned
                    return $app->response("Teapot", 418);
                });

                // Should not be returned
                return 'Nothing to see here...';
            });
            return 'test';
        });

        $actual = $app->run('GET', '/test/teapot');
        $this->assertInstanceOf('\Bullet\Response', $actual);
        $this->assertEquals('Teapot', $actual->content());
        $this->assertEquals(418, $actual->status());
    }

    public function testMethodHandlersNotInFullPathDoNotGetExecuted()
    {
        $actual = array();

        $app = new Bullet\App();
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

        $app->run('GET', '/test/teapot');
        $this->assertEquals(array('teapot'), $actual);
    }

    public function testPathMethodCallbacksDoNotCarryOverIntoNextPath()
    {
        $actual = array();

        $app = new Bullet\App();
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

        $response = $app->run('post', 'test/method');
        $this->assertEquals(array(), $actual);
    }

    public function testIfPathExistsAndMethodCallbackDoesNotResponseIs405()
    {
        $app = new Bullet\App();
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

        $response = $app->run('PUT', 'methodnotallowed');
        $this->assertEquals(405, $response->status());
    }

    public function testPathParamCaptureFirst()
    {
        $app = new Bullet\App();
        $app->path('paramtest', function($request) use($app) {
            // Digit
            $app->param('ctype_digit', function($request, $id) use($app) {
                return $id;
            });
            // Alphanumeric
            $app->param('ctype_alnum', function($request, $slug) use($app) {
                return $slug;
            });
        });

        $response = $app->run('GET', 'paramtest/42');
        $this->assertEquals(200, $response->status());
        $this->assertEquals('42', $response->content());
    }

    public function testPathParamCaptureSecond()
    {
        $app = new Bullet\App();
        $app->path('paramtest', function($request) use($app) {
            // Digit
            $app->param('ctype_digit', function($request, $id) use($app) {
                return $id;
            });
            // All printable characters except space
            $app->param('ctype_graph', function($request, $slug) use($app) {
                return $slug;
            });
        });

        $response = $app->run('GET', 'paramtest/my-blog-post-title');
        $this->assertEquals(200, $response->status());
        $this->assertEquals('my-blog-post-title', $response->content());
    }

    public function testPathParamCaptureWithMethodHandlers()
    {
        $app = new Bullet\App();
        $app->path('paramtest', function($request) use($app) {
            // Digit
            $app->param('int', function($request, $id) use($app) {
                $app->get(function($request) use($id) {
                    // View resource
                    return 'view_' . $id;
                });

                $app->put(function($request) use($id) {
                    // Update resource
                    return 'update_' . $id;
                });
                return $id;
            });
            // All printable characters except space
            $app->param('slug', function($request, $slug) use($app) {
                return $slug;
            });
        });

        $response = $app->run('PUT', 'paramtest/546');
        $this->assertEquals(200, $response->status());
        $this->assertEquals('update_546', $response->content());
    }

    public function testPathExecutionIgnoresExtension()
    {
        $app = new Bullet\App();
        $app->path('test', function($request) use($app) {
            $collect[] = 'test';
            $app->path('foo', function() use($app) {
                return 'Not JSON';
            });
        });

        $response = $app->run('GET', '/test/foo.json');
        $this->assertEquals(200, $response->status());
        $this->assertEquals('Not JSON', $response->content());
    }

    public function testPathWithExtensionExecutesMatchingFormatHandler()
    {
        $app = new Bullet\App();
        $app->path('test', function($request) use($app) {
            $collect[] = 'test';
            $app->path('foo', function() use($app) {
                $app->format('json', function() use($app) {
                    return array('foo' => 'bar', 'bar' => 'baz');
                });
                $app->format('html', function() use($app) {
                    return '<tag>Some HTML</tag>';
                });
            });
        });

        $response = $app->run('GET', '/test/foo.json');
        $this->assertEquals(200, $response->status());
        $this->assertEquals(json_encode(array('foo' => 'bar', 'bar' => 'baz')), $response->content());
    }

    public function testPathWithExtensionAndFormatHandlersButNoMatchingFormatHandlerReturns406()
    {
        $app = new Bullet\App();
        $app->path('test', function($request) use($app) {
            $collect[] = 'test';
            $app->path('foo', function() use($app) {
                $app->format('json', function() use($app) {
                    return array('foo' => 'bar', 'bar' => 'baz');
                });
                $app->format('html', function() use($app) {
                    return '<tag>Some HTML</tag>';
                });
            });
        });

        $response = $app->run('GET', '/test/foo.xml');
        $this->assertEquals(406, $response->status());
        $this->assertEquals('Not Acceptable', $response->content());
    }

    public function testFormatHanldersRunUsingAcceptHeader()
    {
        $app = new Bullet\App();
        $app->path('posts', function($request) use($app) {
            $app->get(function() use($app) {
                $app->format('json', function() use($app) {
                    return array('listing' => 'something');
                });
            });
            $app->post(function($request) use($app) {
                $app->format('json', function() use($app) {
                    return $app->response(array('created' => 'something'), 201);
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
        $app->path('foo', function($request) use($app) {
            return "foo";
        });
        $app->path('bar', function($request) use($app) {
            $foo = $app->run('GET', 'foo'); // $foo is now a `Bullet\Response` instance
            return $foo->content() . "bar";
        });
        $response = $app->run('GET', 'bar');
        $this->assertEquals(200, $response->status());
        $this->assertEquals('foobar', $response->content());
    }

    public function testUrlHelperReturnsCurrentPathWhenCalledWithNoArguments()
    {
        $app = new Bullet\App();
        $app->path('test', function($request) use($app) {
            $collect[] = 'test';
            $app->path('foo', function() use($app) {
                return $app->url();
            });
        });

        $response = $app->run('GET', '/test/foo/');
        $this->assertEquals(200, $response->status());
        $this->assertEquals('cli:/test/foo', $response->content());
    }

    public function testUrlHelperReturnsRelativePath()
    {
        $app = new Bullet\App();
        $app->path('test', function($request) use($app) {
            $collect[] = 'test';
            $app->path('foo', function() use($app) {
                return $app->url('./blogs');
            });
        });

        $response = $app->run('GET', '/test/foo/');
        $this->assertEquals(200, $response->status());
        $this->assertEquals('cli:/test/foo/blogs', $response->content());
    }

    public function testUrlHelperReturnsRelativePathWithoutRepeating()
    {
        $app = new Bullet\App();
        $app->path('test', function($request) use($app) {
            $collect[] = 'test';
            $app->path('foo', function() use($app) {
                return $app->url('./test/foo'); // Should not be 'test/foo/test/foo'
            });
        });

        $response = $app->run('GET', '/test/foo/');
        $this->assertEquals(200, $response->status());
        $this->assertEquals('cli:/test/foo', $response->content());
    }

    public function testUrlHelperReturnsRelativePathWithoutRepeatingLastPortion()
    {
        $app = new Bullet\App();
        $app->path('test', function($request) use($app) {
            $app->path('foo', function() use($app) {
                return $app->url('./foo'); // Should not be 'test/foo/foo'
            });
        });

        $response = $app->run('GET', '/test/foo/');
        $this->assertEquals(200, $response->status());
        $this->assertEquals('cli:/test/foo', $response->content());
    }

    public function testUrlHelperReturnsRelativePathWithoutRepeatingBasePath()
    {
        $app = new Bullet\App();
        $app->path('events', function($request) use($app) {
            return $app->url('./events/42'); // Should not be 'events/events/42' or just 'events'
        });

        $response = $app->run('GET', '/events/');
        $this->assertEquals(200, $response->status());
        $this->assertEquals('cli:/events/42', $response->content());
    }

    public function testUrlHelperReturnsGivenPath()
    {
        $app = new Bullet\App();
        $app->path('test', function($request) use($app) {
            $collect[] = 'test';
            $app->path('foo', function() use($app) {
                return $app->url('blog/42/edit');
            });
            $app->path('foo2', function() {
                $collect[] = 'foo2';
            });
        });

        $response = $app->run('GET', '/test/foo/');
        $this->assertEquals(200, $response->status());
        $this->assertEquals('cli:/blog/42/edit', $response->content());
    }

    public function testStatusOnResponseObjectReturnsCorrectStatus()
    {
        $app = new Bullet\App();
        $app->path('posts', function($request) use($app) {
            $app->post(function() use($app) {
                return $app->response('Created something!', 201);
            });
        });

        $actual = $app->run('POST', 'posts');
        $this->assertEquals(201, $actual->status());
    }

    public function testStatusOnResponseObjectReturnsCorrectStatusAsJSON()
    {
        $app = new Bullet\App();
        $app->path('posts', function($request) use($app) {
            $app->post(function() use($app) {
                $app->format('json', function() use($app) {
                    return $app->response(array('created' => 'something'), 201);
                });
            });
        });

        $actual = $app->run('POST', 'posts.json');
        $this->assertEquals(201, $actual->status());
    }

    public function testExceptionsAreCaughtWhenCustomHandlerIsRegistered()
    {
        $app = new Bullet\App();
        $app->exceptionHandler(function($e) {
            if($e instanceof \Exception) {
                return "yep";
            }
            return "nope";
        });
        $app->path('test', function($request) use($app) {
            throw new \Exception("This is a specific error message here!");
        });

        $response = $app->run('GET', 'test');
        $this->assertEquals(500, $response->status());
        $this->assertEquals('yep', $response->content());
    }

    public function testExceptionHandlerAllowsStatusOtherThan500()
    {
        $app = new Bullet\App();
        $app->exceptionHandler(function($e) use($app) {
            return $app->response('Bad Request custom exception handler', 400);
        });
        $app->path('test', function($request) use($app) {
            throw new \Exception("This is a specific error message here!");
        });

        $response = $app->run('POST', 'test');
        $this->assertEquals(400, $response->status());
        $this->assertEquals('Bad Request custom exception handler', $response->content());
    }
}
