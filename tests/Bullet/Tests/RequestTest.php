<?php
namespace Bullet\Tests;
use Bullet;

class RequestTest extends \PHPUnit_Framework_TestCase
{
    function testMethod()
    {
        $r = new Bullet\Request('POST', '/foo/bar');
        $this->assertEquals('POST', $r->method());
    }

    function testMethodSupportsPatch()
    {
        $r = new Bullet\Request('PATCH', '/foo/bar');
        $this->assertEquals('PATCH', $r->method());
    }

    function testUrl()
    {
        $r = new Bullet\Request('DELETE', '/foo/bar/');
        $this->assertEquals('/foo/bar/', $r->url());
    }

    function testFormatDefaultsToNull()
    {
        $r = new Bullet\Request('DELETE', '/foo/bar/');
        $this->assertEquals(null, $r->format());
    }

    function testExtensionOverridesAcceptHeader()
    {
        $r = new Bullet\Request('PUT', '/users/42.xml', array(), array('Accept' => 'text/html,application/json'));
        $this->assertEquals('xml', $r->format());
    }

    function testAccept()
    {
        $r = new Bullet\Request('', '', array(), array('Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8,application/json'));
        $this->assertTrue($r->accept('html'));
        $this->assertTrue($r->accept('xhtml'));
        $this->assertTrue($r->accept('xml'));
        $this->assertTrue($r->accept('json'));
        $this->assertFalse($r->accept('csv'));
    }

    function testAcceptHeader()
    {
        $r = new Bullet\Request('', '', array(), array('Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8,application/json'));
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
        $app = new Bullet\App();
        // Accept only JSON and request URL with no extension
        $req = new Bullet\Request('PUT', '/foo', array(), array('Accept' => 'application/json'));
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
        $app = new Bullet\App();
        // Accept only JSON and request URL with no extension
        $req = new Bullet\Request('GET', '/foo', array(), array('Accept' => '*/*'));
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
        $app = new Bullet\App();
        // Accept only JSON and request URL with no extension
        $req = new Bullet\Request('GET', '/foo', array());
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
        $r = new Bullet\Request('PUT', '/users/123.json', array(), array('Accept' => 'application/json'), 'id=123&foo=bar&bar=bar+baz');
        $this->assertEquals('123', $r->id);
        $this->assertEquals('bar baz', $r->bar);
    }

    function testRawJsonBodyIsDecodedInPutRequest()
    {
        $r = new Bullet\Request('PUT', '/users/42.json', array(), array('Accept' => 'application/json'), '{"id":"123"}');
        $this->assertEquals('123', $r->id);
    }

    function testRawJsonBodyIsDecodedInPostRequest()
    {
        $r = new Bullet\Request('POST', '/users/129.json', array(), array('Accept' => 'application/json'), '{"id":"124"}');
        $this->assertEquals('124', $r->id);
    }

    function testRawJsonBodyIsIgnoredInPostRequestIfPostParamsAreSet()
    {
        $r = new Bullet\Request('POST', '/users/129.json', array('id' => '123'), array('Accept' => 'application/json'), '{"id":"124"}');
        $this->assertEquals('123', $r->id);
    }

    function testGetWithParamsAreSetInQuerystringData()
    {
        $r = new Bullet\Request('GET', '/users/129.json', array('id' => '124', 'foo' => 'bar'));
        $this->assertEquals('124', $r->query('id'));
        $this->assertEquals('bar', $r->query('foo'));
        $this->assertEquals(array('id' => '124', 'foo' => 'bar'), $r->query());
    }

    function testPostWithParamsAreSetInPostData()
    {
        $r = new Bullet\Request('POST', '/users/129.json', array('id' => '124', 'foo' => 'bar'));
        $this->assertEquals('124', $r->post('id'));
        $this->assertEquals('bar', $r->post('foo'));
    }

    function testRawJsonBodyWithSpacesInValueIsDecodedInPutRequest()
    {
        $r = new Bullet\Request('PUT', '/users/42.json', array(), array('Accept' => 'application/json'), '{"foo":"bar baz"}');
        $this->assertEquals('bar baz', $r->foo);
    }

    function testRawJsonBodyWithSpacesInKeyIsDecodedInPutRequest()
    {
        $r = new Bullet\Request('PUT', '/users/42.json', array(), array('Accept' => 'application/json'), '{"foo bar":"baz"}');
        $this->assertEquals('baz', $r->{'foo bar'});
    }

    function testRawJsonBodyWithSpacesInKeyAndValueIsDecodedInPutRequest()
    {
        $r = new Bullet\Request('PUT', '/users/42.json', array(), array('Accept' => 'application/json'), '{"foo bar":"bar baz"}');
        $this->assertEquals('bar baz', $r->{'foo bar'});
    }

    function testRawJsonBodyWithDotsInValueIsDecodedInPutRequest()
    {
        $r = new Bullet\Request('PUT', '/users/42.json', array(), array('Accept' => 'application/json'), '{"link":"http://bulletphp.com/"}');
        $this->assertEquals('http://bulletphp.com/', $r->link);
    }

    function testRawJsonBodyWithDotsInKeyIsDecodedInPutRequest()
    {
        $r = new Bullet\Request('PUT', '/users/42.json', array(), array('Accept' => 'application/json'), '{"the.link":"bulletphp"}');
        $this->assertEquals('bulletphp', $r->{'the.link'});
    }

    function testRawJsonBodyWithDotsInKeyAndValueIsDecodedInPutRequest()
    {
        $r = new Bullet\Request('PUT', '/users/42.json', array(), array('Accept' => 'application/json'), '{"the.link":"http://bulletphp.com"}');
        $this->assertEquals('http://bulletphp.com', $r->{'the.link'});
    }

    function testRawJsonBodyIsDecodedWithBadJSON()
    {
        $r = new Bullet\Request('PUT', '/test', array(), array('Content-Type' => 'application/json'), '{\"title\":\"Updated New Post Title\",\"body\":\"<p>A much better post body</p>\"}\n');
        $app = new Bullet\App();
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
        $r = new Bullet\Request('GET', '/', array(), array('Host' => 'test.bulletphp.com'));
        $this->assertEquals('test', $r->subdomain());
    }

    function testSubdomainCaptureWithNoSubdomain()
    {
        $r = new Bullet\Request('GET', '/', array(), array('Host' => 'bulletphp.com'));
        $this->assertFalse($r->subdomain());
    }

    function testOptionsHeader()
    {
        $app = new Bullet\App();
        $req = new Bullet\Request('OPTIONS', '/test');
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

    function testOptionsHeaderWithCustomOptionsRoute()
    {
        $app = new Bullet\App();
        $req = new Bullet\Request('OPTIONS', '/test');
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
        $app = new Bullet\App();
        $req = new Bullet\Request('GET', '/cache');
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
        $app = new Bullet\App();
        $req = new Bullet\Request('GET', '/cache');
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
        $app = new Bullet\App();
        $req = new Bullet\Request('GET', '/cache');
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
        $app = new Bullet\App();
        $req = new Bullet\Request('GET', '/cache');
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
        $app = new Bullet\App();
        $req = new Bullet\Request('GET', '/cache');
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
        $this->assertEquals(gmdate("D, d M Y H:i:s", time()+$cacheTime), $res->header('Expires'));
    }

    function testGetWithQueryString()
    {
        $app = new Bullet\App();
        $app->path('test', function($request) use($app) {
            $app->get(function($request) {
                return 'foo=' . $request->foo;
            });
        });
        $req = new Bullet\Request('GET', '/test?foo=bar');
        $res = $app->run($req);
        $this->assertEquals('foo=bar', $res->content());
    }

    public function testRequestsDoNotShareParams()
    {
        $first_request  = new Bullet\Request('GET', '/', array('foo' => 'bar'));
        $second_request = new Bullet\Request('GET', '/', array());
        $this->assertNull($second_request->foo);
    }
}

