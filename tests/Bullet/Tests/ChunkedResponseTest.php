<?php
namespace Bullet\Tests;
use Bullet\App;
use Bullet\Response\Chunked;
use PHPUnit\Framework\TestCase;

class ChunkedTest extends TestCase
{
    private $_testContent = [
        "The", " ", "quick", " brown", " fox ", "jumps", " over ", "the ",
        "lazy ", "dog."
    ];
    private function _runTestBulletApp($chunkSize, $content) {
        $app = new App();
        $app->path('/test', function($request) use ($chunkSize, $content) {
            $c = new Chunked($content);
            $c->chunkSize = $chunkSize;
            return $c;
        });
        $response = $app->run('GET', '/test');
        $this->assertInstanceOf('\Bullet\\Response\\Chunked', $response);
        ob_start();
        $response->send();
        $output = ob_get_clean();
        return $output;
    }
    public function testNonBufferedChunkedEncoding() {
        $output = $this->_runTestBulletApp(0, $this->_testContent);
        $shouldBe = '';
        foreach ($this->_testContent as $word) {
            $shouldBe .= sprintf("%x\r\n%s\r\n", strlen($word), $word);
        }
        $shouldBe .= "0\r\n\r\n";
        $this->assertEquals($shouldBe, $output);
    }
    public function testBufferedChunkedEncoding() {
        $CHUNKSIZE = 10;
        $output = $this->_runTestBulletApp($CHUNKSIZE, $this->_testContent);
        $shouldBe = '';
        foreach (str_split(implode('', $this->_testContent), $CHUNKSIZE) as $word) {
            $shouldBe .= sprintf("%x\r\n%s\r\n", strlen($word), $word);
        }
        $shouldBe .= "0\r\n\r\n";
        $this->assertEquals($shouldBe, $output);
    }
    public function testGeneratorFunction() {
        $CHUNKSIZE = 10;
        $content = function () {
            for ($i = 0; $i < 5; $i++) {
                yield "xxxxx";
            }
        };
        $output = $this->_runTestBulletApp(10, $content());
        $strcontent = '';
        foreach ($content() as $piece) {
            $strcontent .= $piece;
        }
        $shouldBe = '';
        foreach (str_split($strcontent, $CHUNKSIZE) as $word) {
            $shouldBe .= sprintf("%x\r\n%s\r\n", strlen($word), $word);
        }
        $shouldBe .= "0\r\n\r\n";
        $this->assertEquals($shouldBe, $output);
    }
}
