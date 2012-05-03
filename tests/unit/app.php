<?php
class AppTest extends \PHPUnit_Framework_TestCase
{
    public function testSomething()
    {
        $this->assertTrue(true);
    }

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

        $collect = $app->run('GET', '/test/foo/');

        $expect = array('test', 'foo');
        $this->assertEquals($collect, $expect);
    }

    public function testNonmatchingPathRunReturnsBooleanFalse()
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

        $collect = $app->run('GET', '/test/foo/bar/');

        $expect = false;
        $this->assertEquals($collect, $expect);
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
                    $collect[] = 'GET';
                });
                $app->post(function() use(&$collect) {
                    $collect[] = 'POST';
                });
            });
        });

        $app->run('POST', '/test/foo/');

        $expect = array('test', 'foo', 'POST');
        $this->assertEquals($collect, $expect);
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

        $expect = array('root');
        $this->assertEquals($collect, $expect);
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

        $expect = array('matched', 'me');
        $this->assertEquals($collect, $expect);
    }
}
