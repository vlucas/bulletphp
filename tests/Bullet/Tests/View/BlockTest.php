<?php
namespace Bullet\Tests\View;
use Bullet;
use Bullet\View\Template;

class BlockTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->templateDir = dirname(dirname(dirname(__DIR__))) . '/fixtures/templates/';

        // Save default config
        $this->oldConfig = Template::config();

        // Specify fixture path
        Template::config(array(
          'path' => $this->templateDir
        ));
    }

    public function tearDown()
    {
        // Restore config to original state
        Template::config($this->oldConfig);
    }

    public function testBlockRendersInLayoutWithSetContent()
    {
        $tpl = new Template('block');
        $tpl->layout('layouts/block');
        $this->assertEquals("<script src='one.js'></script>\n<script src='two.js'></script>\n<div><p>Test</p></div>", $tpl->content());
    }
}
