<?php
namespace Bullet\Tests;
use Bullet\App;
use Bullet\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    function testMethod()
    {
        $r = new Request('POST', '/foo/bar');
        $this->assertEquals('POST', $r->method());
    }

    function testMethodSupportsPatch()
    {
        $r = new Request('PATCH', '/foo/bar');
        $this->assertEquals('PATCH', $r->method());
    }

    function testUrl()
    {
        $r = new Request('DELETE', '/foo/bar/');
        $this->assertEquals('/foo/bar/', $r->url());
    }

    function testFormatDefaultsToNull()
    {
        $r = new Request('DELETE', '/foo/bar/');
        $this->assertEquals(null, $r->format());
    }

    function testExtensionOverridesAcceptHeader()
    {
        $r = new Request('PUT', '/users/42.xml', array(), array('Accept' => 'text/html,application/json'));
        $this->assertEquals('xml', $r->format());
    }

    function testAccept()
    {
        $r = new Request('', '', array(), array('Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8,application/json'));
        $this->assertTrue($r->accept('html'));
        $this->assertTrue($r->accept('xhtml'));
        $this->assertTrue($r->accept('xml'));
        $this->assertTrue($r->accept('json'));
        $this->assertFalse($r->accept('csv'));
    }

    function testAcceptHeader()
    {
        $r = new Request('', '', array(), array('Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8,application/json'));
        $this->assertEquals(array(
          "text/html" => "text/html",
          "application/xhtml+xml" => "application/xhtml+xml",
          "application/json" => "application/json",
          "application/xml" => "application/xml",
          "*/*" => "*/*"
        ), $r->accept());
    }

    function testAcceptHeaderOverridesDefaultHtmlInApp()
    {
        $app = new App();
        // Accept only JSON and request URL with no extension
        $req = new Request('PUT', '/foo', array(), array('Accept' => 'application/json'));
        $app->path('foo', function($request) use($app) {
            $app->format('json', function($request) {
                return array('foo' => 'bar');
            });
            $app->format('html', function($request) {
                return '<html></html>';
            });
        });
        $res = $app->run($req);
        $this->assertEquals('json', $req->format());
        $this->assertEquals('{"foo":"bar"}', $res->content());
        $this->assertEquals('application/json', $res->contentType());
    }

    function testAcceptAnyReturnsFirstFormat()
    {
        $app = new App();
        // Accept only JSON and request URL with no extension
        $req = new Request('GET', '/foo', array(), array('Accept' => '*/*'));
        $app->path('foo', function($request) use($app) {
            $app->format('json', function($request) {
                return array('foo' => 'bar');
            });
            $app->format('html', function($request) {
                return '<html></html>';
            });
        });
        $res = $app->run($req);
        $this->assertEquals(null, $req->format());
        $this->assertEquals('{"foo":"bar"}', $res->content());
        $this->assertEquals('application/json', $res->contentType());
    }

    function testAcceptEmptyReturnsFirstFormat()
    {
        $app = new App();
        // Accept only JSON and request URL with no extension
        $req = new Request('GET', '/foo', array());
        $app->path('foo', function($request) use($app) {
            $app->format('json', function($request) {
                return array('foo' => 'bar');
            });
        });
        $res = $app->run($req);
        $this->assertEquals('{"foo":"bar"}', $res->content());
        $this->assertEquals('application/json', $res->contentType());
    }

    function testRawUrlencodedBodyIsDecodedInPutRequest()
    {
        $r = new Request('PUT', '/users/123.json', array(), array('Accept' => 'application/json'), 'id=123&foo=bar&bar=bar+baz');
        $this->assertEquals('123', $r->id);
        $this->assertEquals('bar baz', $r->bar);
    }

    function testRawJsonBodyIsDecodedInPutRequest()
    {
        $r = new Request('PUT', '/users/42.json', array(), array('Accept' => 'application/json'), '{"id":"123"}');
        $this->assertEquals('123', $r->id);
    }

    function testRawJsonBodyIsDecodedInPostRequest()
    {
        $r = new Request('POST', '/users/129.json', array(), array('Accept' => 'application/json'), '{"id":"124"}');
        $this->assertEquals('124', $r->id);
    }

    function testRawJsonBodyIsIgnoredInPostRequestIfPostParamsAreSet()
    {
        $r = new Request('POST', '/users/129.json', array('id' => '123'), array('Accept' => 'application/json'), '{"id":"124"}');
        $this->assertEquals('123', $r->id);
    }

    function testGetWithParamsAreSetInQuerystringData()
    {
        $r = new Request('GET', '/users/129.json', array('id' => '124', 'foo' => 'bar'));
        $this->assertEquals('124', $r->query('id'));
        $this->assertEquals('bar', $r->query('foo'));
        $this->assertEquals(array('id' => '124', 'foo' => 'bar'), $r->query());
    }

    function testPostWithParamsAreSetInPostData()
    {
        $r = new Request('POST', '/users/129.json', array('id' => '124', 'foo' => 'bar'));
        $this->assertEquals('124', $r->post('id'));
        $this->assertEquals('bar', $r->post('foo'));
    }

    function testRawJsonBodyWithSpacesInValueIsDecodedInPutRequest()
    {
        $r = new Request('PUT', '/users/42.json', array(), array('Accept' => 'application/json'), '{"foo":"bar baz"}');
        $this->assertEquals('bar baz', $r->foo);
    }

    function testRawJsonBodyWithSpacesInKeyIsDecodedInPutRequest()
    {
        $r = new Request('PUT', '/users/42.json', array(), array('Accept' => 'application/json'), '{"foo bar":"baz"}');
        $this->assertEquals('baz', $r->{'foo bar'});
    }

    function testRawJsonBodyWithSpacesInKeyAndValueIsDecodedInPutRequest()
    {
        $r = new Request('PUT', '/users/42.json', array(), array('Accept' => 'application/json'), '{"foo bar":"bar baz"}');
        $this->assertEquals('bar baz', $r->{'foo bar'});
    }

    function testRawJsonBodyWithDotsInValueIsDecodedInPutRequest()
    {
        $r = new Request('PUT', '/users/42.json', array(), array('Accept' => 'application/json'), '{"link":"http://bulletphp.com/"}');
        $this->assertEquals('http://bulletphp.com/', $r->link);
    }

    function testRawJsonBodyWithDotsInKeyIsDecodedInPutRequest()
    {
        $r = new Request('PUT', '/users/42.json', array(), array('Accept' => 'application/json'), '{"the.link":"bulletphp"}');
        $this->assertEquals('bulletphp', $r->{'the.link'});
    }

    function testRawJsonBodyWithDotsInKeyAndValueIsDecodedInPutRequest()
    {
        $r = new Request('PUT', '/users/42.json', array(), array('Accept' => 'application/json'), '{"the.link":"http://bulletphp.com"}');
        $this->assertEquals('http://bulletphp.com', $r->{'the.link'});
    }

    function testRawJsonBodyIsDecodedWithBadJSON()
    {
        $r = new Request('PUT', '/test', array(), array('Content-Type' => 'application/json'), '{\"title\":\"Updated New Post Title\",\"body\":\"<p>A much better post body</p>\"}\n');
        $app = new App();
        $app->path('test', function($request) use($app) {
            $app->put(function($request) {
                return 'title: ' . $request->get('title');
            });
        });
        $res = $app->run($r);
        $this->assertEquals('title: Updated New Post Title', $res->content());
    }

    function testSubdomainCapture()
    {
        $r = new Request('GET', '/', array(), array('Host' => 'test.bulletphp.com'));
        $this->assertEquals('test', $r->subdomain());
    }

    function testSubdomainCaptureWithNoSubdomain()
    {
        $r = new Request('GET', '/', array(), array('Host' => 'bulletphp.com'));
        $this->assertFalse($r->subdomain());
    }

    function testOptionsHeader()
    {
        $app = new App();
        $req = new Request('OPTIONS', '/test');
        $app->path('test', function($request) use($app) {
            $app->get(function($request) {
                return 'GET';
            });
            $app->post(function($request) {
                return 'POST';
            });
        });
        $res = $app->run($req);
        $this->assertEquals('OK', $res->content());
        $this->assertEquals('GET,POST,OPTIONS', $res->header('Allow'));
    }

    function testOptionsHeaderForParamPaths()
    {
        $app = new App();
        $req = new Request('OPTIONS', '/test/this_is_a_slug');
        $app->path('test', function($request) use($app) {
            $app->param('slug', function($request, $param) use ($app) {
                $app->get(function($request) {
                    return 'GET';
                });
                $app->post(function($request) {
                    return 'POST';
                });
            });
        });
        $res = $app->run($req);
        $this->assertEquals('OK', $res->content());
        $this->assertEquals('GET,POST,OPTIONS', $res->header('Allow'));
    }

    function testOptionsHeaderWithCustomOptionsRoute()
    {
        $app = new App();
        $req = new Request('OPTIONS', '/test');
        $app->path('test', function($request) use($app) {
            $app->get(function($request) {
                return 'GET';
            });
            $app->post(function($request) {
                return 'POST';
            });
            $app->options(function($request) {
                return 'OPTIONS';
            });
        });
        $res = $app->run($req);
        $this->assertEquals('OPTIONS', $res->content());
        $this->assertEquals(false, $res->header('Allow'));
    }

    function testCacheHeaderFalse()
    {
        $app = new App();
        $req = new Request('GET', '/cache');
        $app->path('cache', function($request) use($app) {
            $app->get(function($request) use($app) {
                return $app->response(200, 'CONTENT')->cache(false);
            });
        });
        $res = $app->run($req);
        $this->assertEquals('CONTENT', $res->content());
        $this->assertEquals('no-cache, no-store', $res->header('Cache-Control'));
    }

    function testCacheHeaderTime()
    {
        $app = new App();
        $req = new Request('GET', '/cache');
        $currentTime = time();
        $cacheTime = strtotime('+1 hour', $currentTime);
        $app->path('cache', function($request) use($app, $cacheTime) {
            $app->get(function($request) use($app, $cacheTime) {
                return $app->response(200, 'CONTENT')->cache($cacheTime);
            });
        });
        $res = $app->run($req);
        $this->assertEquals('CONTENT', $res->content());
        $this->assertEquals('public, max-age=3600', $res->header('Cache-Control'));
        $this->assertEquals(gmdate("D, d M Y H:i:s", $cacheTime), $res->header('Expires'));
    }

    function testCacheWithDateTimeObject()
    {
        $app = new App();
        $req = new Request('GET', '/cache');
        $currentTime = time();
        $cacheTime = new \DateTime('+1 hour');
        $app->path('cache', function($request) use($app, $cacheTime) {
            $app->get(function($request) use($app, $cacheTime) {
                return $app->response(200, 'CONTENT')->cache($cacheTime);
            });
        });
        $res = $app->run($req);
        $this->assertEquals('CONTENT', $res->content());
        $this->assertEquals('public, max-age=3600', $res->header('Cache-Control'));
        $this->assertEquals(gmdate("D, d M Y H:i:s", $cacheTime->getTimestamp()), $res->header('Expires'));
    }

    function testCacheWithString()
    {
        $app = new App();
        $req = new Request('GET', '/cache');
        $currentTime = time();
        $cacheTime = '1 hour';
        $app->path('cache', function($request) use($app, $cacheTime) {
            $app->get(function($request) use($app, $cacheTime) {
                return $app->response(200, 'CONTENT')->cache($cacheTime);
            });
        });
        $res = $app->run($req);
        $this->assertEquals('CONTENT', $res->content());
        $this->assertEquals('public, max-age=3600', $res->header('Cache-Control'));
        $this->assertEquals(gmdate("D, d M Y H:i:s", strtotime($cacheTime)), $res->header('Expires'));
    }

    function testCacheWithSeconds()
    {
        $app = new App();
        $req = new Request('GET', '/cache');
        $currentTime = time();
        $cacheTime = 3600;
        $app->path('cache', function($request) use($app, $cacheTime) {
            $app->get(function($request) use($app, $cacheTime) {
                return $app->response(200, 'CONTENT')->cache($cacheTime);
            });
        });
        $res = $app->run($req);
        $this->assertEquals('CONTENT', $res->content());
        $this->assertEquals('public, max-age=3600', $res->header('Cache-Control'));
        $this->assertEquals(gmdate("D, d M Y H:i:s", $currentTime+$cacheTime), $res->header('Expires'));
    }

    function testGetWithQueryString()
    {
        $app = new App();
        $app->path('test', function($request) use($app) {
            $app->get(function($request) {
                return 'foo=' . $request->foo;
            });
        });
        $req = new Request('GET', '/test?foo=bar');
        $res = $app->run($req);
        $this->assertEquals('foo=bar', $res->content());
    }

    public function testRequestsDoNotShareParams()
    {
        $first_request  = new Request('GET', '/', array('foo' => 'bar'));
        $second_request = new Request('GET', '/', array());
        $this->assertNull($second_request->foo);
    }

    public function testRequestIpClientId()
    {
        $req = new Request();

        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['HTTP_CLIENT_IP'] = "192.168.1.1";

        $this->assertEquals("192.168.1.1", $req->ip());
    }

    public function testRequestIpForwardedFor()
    {
        $req = new Request();

        unset($_SERVER['HTTP_CLIENT_IP']);
        unset($_SERVER['REMOTE_ADDR']);
        $_SERVER['HTTP_X_FORWARDED_FOR'] = "10.1.1.1, 172.16.1.1, 192.168.1.2, 127.0.0.1";

        $this->assertEquals("127.0.0.1", $req->ip());
    }

    public function testRequestIpRemoteAddr()
    {
        $req = new Request();

        unset($_SERVER['HTTP_CLIENT_IP']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['REMOTE_ADDR'] = "192.168.1.3";

        $this->assertEquals("192.168.1.3", $req->ip());
    }
}

