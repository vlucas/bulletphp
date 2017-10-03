<?php
namespace Bullet\Tests;

class SseResponseTest extends \PHPUnit_Framework_TestCase
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
        $app = new \Bullet\App();
        $app->path('/test', function($request) use ($content) {
            return new \Bullet\Response\Sse($content);
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
            = "foo: bar\r\n"
            . "\r\n"
            . "bar: baz\r\n"
            . ":this is a comment\r\n"
            . "emptyfield\r\n"
            . "\r\n";
        $this->assertEquals($shouldBe, $output);
    }
}
