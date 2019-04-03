<?php
namespace Bullet\Tests;

class SseResponseTest extends \PHPUnit\Framework\TestCase
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
        $app->path('test', function() use ($content) {
            return new \Bullet\Response\Sse($content);
        });
        $response = $app->run(new \Bullet\Request('GET', '/test'));
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
