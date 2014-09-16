<?php
namespace Bullet\Tests;
use Bullet;

/**
 * Unit tests for \Bullet\Response.
 */
class ResponseTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Bullet\Response Subject under test
     */
    protected $response;

    /**
     * Set up a clean test fixture.
     */
    protected function setUp()
    {
        $this->response = new Bullet\Response();
    }

    /**
     * Test initial state of headers.
     * @test
     */
    public function testHeadersEmpty()
    {
        $this->assertEmpty($this->response->headers());
    }

    /**
     * Test that adding a content type header is handled differently than other
     * headers.
     * @test
     */
    public function testAddContentType()
    {
        $this->response->header('Content-Type', 'fake/type');
        $this->assertEmpty($this->response->headers());
    }

    /**
     * Test that adding a new header adds it to the headers array.
     * @test
     */
    public function testAddHeader()
    {
        $this->response->header('Link', 'http://foo.com');
        $this->assertEquals(
            array('Link' => 'http://foo.com'),
            $this->response->headers()
        );
    }

    /**
     * Test that adding the same header twice replaces the original value.
     * @test
     */
    public function testAddHeaderTwiceReplacesOriginal()
    {
        $this->response->header('Link', 'http://foo.com');
        $this->response->header('Link', 'http://bar.com');
        $this->assertEquals(
            array('Link' => 'http://bar.com'),
            $this->response->headers()
        );
    }

    /**
     * Test that adding the same header twice replaces the original value with
     * the replace switch set to false allows adding multiple headers with the
     * same name.
     * @test
     */
    public function testAddHeaderWithoutReplacing()
    {
        $this->response->header('Link', 'http://foo.com');
        $this->response->header('Link', 'http://bar.com', false);
        $this->assertEquals(
            array(
                'Link' => array(
                    'http://foo.com',
                    'http://bar.com',
                ),
            ),
            $this->response->headers()
        );
    }
}
