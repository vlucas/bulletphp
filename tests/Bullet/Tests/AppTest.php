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
        $collect = array();

        $app = new Bullet\App();
        $app->path('test', function() use(&$collect) {
            $collect[] = 'test';
        });

        $app->run('GET', '/test/');

        $expect = array('test');
        $this->assertEquals($collect, $expect);
    }

    public function testSingleResourceGet()
    {
        $app = new Bullet\App();
        $app->resource('test', function() {
            return 'resource';
        });

        $res = $app->run('GET', '/test/');
        $this->assertEquals('resource', $res->content());
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
        $this->assertEquals($expect, $collect);
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
        $this->assertEquals($expect, $collect);
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

    public function test204HasNoBodyContent()
    {
        $app = new Bullet\App();
        $app->path('test-no-content', function($request) use($app) {
            $app->get(function($request) {
                return 204;
            });
        });

        $res = $app->run('GET', '/test-no-content');
        $this->assertEquals('', $res->content());
        $this->assertEquals(204, $res->status());
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

    public function testExactPathMatchNotInParamCapture()
    {
        $app = new Bullet\App();
        $app->path('/ping', function($request) use($app) {
            $app->param('slug', function($request, $slug) use($app) {
                return $slug;
            });

            return "pong";
        });

        $response = $app->run('GET', '/ping');
        $this->assertEquals("pong", $response->content());
    }

    public function testRepeatPathnameNested()
    {
        $app = new Bullet\App();
        $app->path('/about/', function($request) use ($app) {
            // return data for my "about" path
            return 'about';
        });

        $app->path('/rels/', function($request) use($app) {
            $app->param('slug', function($request, $rel) use($app) {
                // return the documentation page for /rels/{rel} such as /rels/about
                return 'rel/' . $rel;
            });
        });

        $response = $app->run('GET', '/rels/about/');
        $this->assertEquals("rel/about", $response->content());
    }

    public function testRepeatPathnameNestedPathInsideParam()
    {
        $app = new Bullet\App();
        $app->path('/about/', function($request) use ($app) {
            // return data for my "about" path
            return 'about';
        });

        $app->path('/rels/', function($request) use($app) {
            $app->param('slug', function($request, $rel) use($app) {
                $app->path('/test/', function($request) use ($app, $rel) {
                    return 'rel/' . $rel . '/test';
                });

                // return the documentation page for /rels/{rel} such as /rels/about
                return 'rel/' . $rel;
            });
        });

        $response = $app->run('GET', '/rels/about/test');
        $this->assertEquals("rel/about/test", $response->content());
    }

    public function testGetHandlerBeforeParamCapture()
    {
        $app = new Bullet\App();
        $app->path('/ping', function($request) use($app) {
            $app->get(function($request) use($app) {
                return "pong";
            });

            $app->param('slug', function($request, $slug) use($app) {
                $app->get(function($request) use($slug) {
                    return $slug;
                });
            });
        });

        $response = $app->run('GET', '/ping');
        $this->assertEquals("pong", $response->content());
    }

    public function testParamCallbacksGetClearedAfterMatch()
    {
        $app = new Bullet\App();
        $app->path('about', function() use ($app){
            $page = 'about';
            $app->get(function() use ($app,$page){
                return 'about';
            });

            $app->param('slug',function($request,$slug) use ($app,$page){
                $app->get(function() use ($app,$page,$slug){
                    return $page.'-'.$slug;
                });
            });
        });

        $response1 = $app->run('GET', 'about/slug');
        $this->assertEquals(200, $response1->status());
        $this->assertEquals('about-slug', $response1->content());
        $response2 = $app->run('GET', 'about/slug/foo/bar');
        $this->assertEquals(404, $response2->status());
        $this->assertEquals('Not Found', $response2->content());
    }

    public function testDoubleSlugMethodHandler()
    {
        $app = new Bullet\App();
        $app->path('/ping', function($request) use($app) {
            $app->get(function($request) use($app) {
                return "pong";
            });

            $app->param('slug', function($request, $slug1) use($app) {
                $app->get(function($request) use($slug1) {
                    return $slug1;
                });

                $app->param('slug', function($request, $slug2) use($app, $slug1) {
                    $app->get(function($request) use($slug1, $slug2) {
                        return $slug1 . "/" . $slug2;
                    });
                });
            });
        });

        $response = $app->run('GET', '/ping/slug1');
        $this->assertEquals("slug1", $response->content());
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
                    return $app->response(418, "Teapot");
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
        $this->assertEquals('GET,POST', $response->header('Allow'));
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
        //$this->assertEquals(200, $response->status());
        $this->assertEquals('update_546', $response->content());
    }

    public function testHandlersCanReturnIntergerAsHttpStatus()
    {
        $app = new Bullet\App();
        $app->path('testint', function() use($app) {
            return 200;
        });

        $response = $app->run('GET', 'testint');
        $this->assertEquals(200, $response->status());
        $this->assertEquals("OK", $response->content());
    }

    public function testHandlersCanReturnIntergerAsHttpStatusInMethodCallback()
    {
        $app = new Bullet\App();
        $app->path('testint2', function() use($app) {
            // GET
            $app->get(function($request) use($app) {
                return 429;
            });
        });

        $response = $app->run('GET', 'testint2');
        $this->assertEquals(429, $response->status());
        $this->assertEquals("Too Many Requests", $response->content());
    }

    public function testCustomHttpStatusHandler()
    {
        $app = new Bullet\App();
        $app->path('testhandler', function() use($app) {
            // GET
            $app->get(function($request) use($app) {
                return 500;
            });
        });

        // Register custom handler
        $app->on(500, function($request, $response) {
            $response->status(200)->content("Intercepted 500 Error");
        });

        $response = $app->run('GET', 'testhandler');
        $this->assertEquals(200, $response->status());
        $this->assertEquals("Intercepted 500 Error", $response->content());
    }

    public function testCustomHttpStatusHandlerKeepingContent()
    {
        $app = new Bullet\App();
        $app->path('testhandler', function() use($app) {
            // GET
            $app->get(function($request) use($app) {
                return $app->response(500, "Oh Snap!");
            });
        });

        // Register custom handler
        $app->on(500, function($request, $response) {
            $response->status(204);
        });

        $response = $app->run('GET', 'testhandler');
        $this->assertEquals(204, $response->status());
        $this->assertEquals("Oh Snap!", $response->content());
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
        $this->assertEquals('Not Acceptable', $response->content());
        $this->assertEquals(406, $response->status());
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
                    return $app->response(201, array('created' => 'something'));
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
                return $app->response(201, 'Created something!');
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
                    return $app->response(201, array('created' => 'something'));
                });
            });
        });

        $actual = $app->run('POST', 'posts.json');
        $this->assertEquals(201, $actual->status());
    }

    public function testExceptionsAreCaughtWhenCustomHandlerIsRegistered()
    {
        $app = new Bullet\App();
        $app->on('Exception', function(Request $request, Response $response, \Exception $e) {
            if($e instanceof \Exception) {
                $response->content('yep');
            } else {
                $response->content('nope');
            }
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
        $app->on('InvalidArgumentException', function(Request $request, Response $response, \Exception $e) {
            $response->status(200)->content('There is a pankake on my head. Your argument is invalid.');
        });
        $app->path('test', function($request) use($app) {
            throw new \InvalidArgumentException("This is a specific error message here!");
        });

        $response = $app->run('POST', 'test');
        $this->assertEquals(200, $response->status());
        $this->assertEquals('There is a pankake on my head. Your argument is invalid.', $response->content());
    }

    public function testEventHandlerBefore()
    {
        $app = new Bullet\App();
        $app->path('testhandler', function() use($app) {
            $app->post(function($request) use($app) {
                return $request->foo;
            });
        });

        // Register custom handler
        $app->on('before', function($request, $response) {
            $request->foo = 'bar';
        });

        $response = $app->run('POST', 'testhandler');
        $this->assertEquals('bar', $response->content());
    }

    public function testEventHandlerAfter()
    {
        $app = new Bullet\App();
        $app->path('testhandler', function() use($app) {
            $app->put(function($request) use($app) {
                return 'test';
            });
        });

        // Register custom handler
        $app->on('after', function($request, $response) {
            $response->content($response->content() . 'AFTER');
        });

        $response = $app->run('PUT', 'testhandler');
        $this->assertEquals('testAFTER', $response->content());
    }

    public function testHelperLoading()
    {
        $app = new Bullet\App();
        $app->helper('test', __NAMESPACE__ . '\TestHelper');
        $testHelper = $app->helper('test');

        $this->assertEquals('something', $testHelper->something());
    }

    public function testHelperThrowsExceptionForUnregisteredHelpers()
    {
        $app = new Bullet\App();
        $this->setExpectedException('InvalidArgumentException');
        $testHelper = $app->helper('nonexistent');
    }

    public function testSupportsPatch()
    {
        $app = new Bullet\App();
        $app->path('update', function($request) use($app) {
            $app->patch(function($request) {
                return $request->params();
            });
        });

        $params = array('foo' => 'bar', 'bar' => 'baz');
        $request = new \Bullet\Request('PATCH', '/update', $params);
        $result = $app->run($request);

        $this->assertEquals('PATCH', $request->method());
        $this->assertEquals(json_encode($params), $result->content());
    }

    public function testSupportsHeadAsGetWithNoResponseBody()
    {
        $app = new Bullet\App();
        $app->path('someroute', function($request) use($app) {
            $app->get(function($request) {
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
        $app->path('test', function($request) use($app) {
            $app->head(function($request) use($app) {
                return $app->response()->header('X-Special', 'Stuff');
            });
            $app->get(function($request) {
                return 'I am hidden with a HEAD request!';
            });
        });

        $request = new \Bullet\Request('HEAD', '/test');
        $response = $app->run($request);
        $this->assertEquals('HEAD', $request->method());
        $this->assertEquals('Stuff', $response->header('X-Special'));
        $this->assertEquals('', $response->content());
    }

    public function testSubdomainRoute()
    {
        $app = new Bullet\App();
        $app->subdomain('test', function($request) use($app) {
            $app->path('/', function($request) use($app) {
                $app->get(function($request) {
                    return "GET subdomain " . $request->subdomain();
                });
            });
        });

        $request = new \Bullet\Request('GET', '/', array(), array('Host' => 'test.bulletphp.com'));
        $result = $app->run($request);

        $this->assertEquals(200, $result->status());
        $this->assertEquals("GET subdomain test", $result->content());
    }

    public function testSubdomainRouteAfterMethodHandler()
    {
        $app = new Bullet\App();
        $app->get(function($request) {
            return "GET main path";
        });
        $app->subdomain('bar', function($request) use($app) {
            $app->path('/', function($request) use($app) {
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
            $app->path('/', function($request) use($app) {
                $app->get(function($request) {
                    return "GET subdomain " . $request->subdomain();
                });
            });
        });
        $app->path('/', function($request) use($app) {
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
        $app->subdomain(array('www', false), function($request) use($app) {
            $app->path('/', function($request) use($app) {
                $app->get(function($request) {
                    return "GET www";
                });
            });
        });

        $request = new \Bullet\Request('GET', '/', array(), array('Host' => 'www.bulletphp.com'));
        $result = $app->run($request);

        $this->assertEquals(200, $result->status());
        $this->assertEquals("GET www", $result->content());
    }

    public function testSubdomainFalseRouteWithNoSubdomain()
    {
        $app = new Bullet\App();
        $app->subdomain(false, function($request) use($app) {
            $app->path('/', function($request) use($app) {
                $app->get(function($request) {
                    return "GET www";
                });
            });
        });

        $request = new \Bullet\Request('GET', '/', array(), array('Host' => 'bulletphp.com'));
        $result = $app->run($request);

        $this->assertEquals(200, $result->status());
        $this->assertEquals("GET www", $result->content());
    }

    public function testDomainRoute()
    {
        $app = new Bullet\App();
        $app->domain('example.com', function($request) use($app) {
            $app->path('/', function($request) use($app) {
                $app->get(function($request) {
                    return "GET " . $request->host();
                });
            });
        });

        $request = new \Bullet\Request('GET', '/', array(), array('Host' => 'www.example.com'));
        $result = $app->run($request);

        $this->assertEquals(200, $result->status());
        $this->assertEquals("GET www.example.com", $result->content());
    }

    public function testDomainRoute404()
    {
        $app = new Bullet\App();
        $app->domain('example.com', function($request) use($app) {
            $app->path('/', function($request) use($app) {
                $app->get(function($request) {
                    return "GET " . $request->host();
                });
            });
        });

        $request = new \Bullet\Request('GET', '/', array(), array('Host' => 'www.example2.com'));
        $result = $app->run($request);

        $this->assertEquals(404, $result->status());
    }

    public function testDomainRoute404OnRequestedDomain()
    {
        $app = new Bullet\App();
        $app->domain('example.com', function($request) use($app) {
            $app->path('/goodpath', function($request) use($app) {
                $app->get(function($request) {
                    return "GET " . $request->host();
                });
            });
        });

        $request = new \Bullet\Request('GET', '/badpath', array(), array('Host' => 'www.example.com'));
        $result = $app->run($request);

        $this->assertEquals(404, $result->status());
        $this->assertEquals('Not Found', $result->content());
    }

    public function testDefaultArrayToJSONContentConverter()
    {
        $app = new Bullet\App();
        $app->path('/', function($request) use($app) {
            return array('foo' => 'bar');
        });

        $request = new \Bullet\Request('GET', '/');
        $result = $app->run($request);

        $this->assertEquals(json_encode(array('foo' => 'bar')), $result->content());
        $this->assertEquals('application/json', $result->contentType());
    }

    public function testResponseWithNoContentConverterIsUnchanged()
    {
        $app = new Bullet\App();
        $app->path('/', function($request) use($app) {
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
        $app->path('/', function($request) use($app) {
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
        $app->path('/', function($request) use($app) {
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

    public function testNestedRoutesInsideParamCallback()
    {
        $app = new Bullet\App();

        $app->path('admin', function($request) use($app) {
            $app->path('client', function($request) use($app) {
                $app->param('int', function($request,$id) use($app){
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

        $result = $app->run('GET', '/admin/client/1/toggleVisiblity/item');
        $this->assertEquals('deep', $result->content());
    }

    public function testHMVCNestedRouting()
    {
        $app = new Bullet\App();

        $app->path('a', function($request) use($app) {
            $app->path('b', function($request) use($app) {
                return 'a/b';
            });
        });

        $app->path('c', function($request) use($app) {
            $app->path('a', function($request) use($app) {
                $app->path('b', function($request) use($app) {
                    $a = $app->run('GET', 'a/b');
                    return $a->content() . " + c/a/b";
                });
            });
        });

        $result = $app->run('GET', 'c/a/b');
        $this->assertEquals('a/b + c/a/b', $result->content());
    }

    public function testHMVCNestedRoutingWithSubdomain()
    {
        $app = new Bullet\App();

        $app->subdomain('test', function($request) use($app) {
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

        $request = new \Bullet\Request('GET', 'c/a/b', array(), array('Host' => 'test.bulletphp.com'));
        $result = $app->run($request);
        $this->assertEquals('a/b + c/a/b', $result->content());
    }

    /**
     * @requires PHP 5.4
     */
    public function testPHP5_4ClosureBinding()
    {
        $app = new Bullet\App();

        // Skip test for HHVM
        if($app->request()->isHHVM()) {
            $this->markTestSkipped('HHVM does not support Closure::bindTo');
        }

        $app->path('php5_4', function($request) {
            return $this->url('/worked/');
        });

        $result = $app->run('GET', '/php5_4/');
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

