<?php
namespace Bullet\Tests;
use Bullet\App;
use Bullet\Response\Sse;
use PHPUnit\Framework\TestCase;

class SseResponseTest extends TestCase
{
    private $_testContent = [
        [
            "foo" => "bar"
        ],[
            "bar"  => "baz",
            ":this is a comment" => null,
            "emptyfield" => null
        ]
    ];

    private function _runTestBulletApp($content)
    {
        $app = new App();
        $app->path('/test', function($request) use ($content) {
            return new Sse($content);
        });
        $response = $app->run('GET', '/test');
        $this->assertInstanceOf('\Bullet\\Response\\Sse', $response);
        ob_start();
        $response->send();
        $output = ob_get_clean();
        return $output;
    }

    public function testSseResponse()
    {
        $output = $this->_runTestBulletApp($this->_testContent);
        $shouldBe
            = "c\r\n"
            . "foo: bar\r\n"
            . "\r\n"
            . "\r\n"
            . "2c\r\n"
            . "bar: baz\r\n"
            . ":this is a comment\r\n"
            . "emptyfield\r\n"
            . "\r\n"
            . "\r\n";
        $this->assertEquals($shouldBe, $output);
    }
}
