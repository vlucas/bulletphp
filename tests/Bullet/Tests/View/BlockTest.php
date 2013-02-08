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

    public function testBlockDefaultContent()
    {
        $tpl = new Template('block');
        $block = $tpl->block(__FUNCTION__, function() { echo "Default Content"; });
        $this->assertEquals("Default Content", $block->content());
    }

    public function testBlockAppend()
    {
        $tpl = new Template('block');
        $block = $tpl->block(__FUNCTION__)->append(function() { echo "Test"; });
        $this->assertEquals("Test", $block->content());
    }

    public function testBlockAppendAppendsContent()
    {
        $tpl = new Template('block');
        $block = $tpl->block(__FUNCTION__)->content(function() { echo 'Content'; })
            ->append(function() { echo 'After'; });
        $this->assertEquals("ContentAfter", $block->content());
    }

    public function testBlockAppendTemplateRender()
    {
        $tpl = new Template('test');
        $tpl->layout('layouts/block');
        $block = $tpl->block('js')->content(function() { })
            ->append(function() { echo 'Content'; });
        $this->assertEquals("Content<div><p>Test</p></div>", $tpl->content());
    }

    public function testBlockPreppend()
    {
        $tpl = new Template('block');
        $block = $tpl->block(__FUNCTION__)->prepend(function() { echo "Test"; });
        $this->assertEquals("Test", $block->content());
    }

    public function testBlockPrependPrependsContent()
    {
        $tpl = new Template('block');
        $block = $tpl->block(__FUNCTION__)->content(function() { echo 'Content'; })
            ->prepend(function() { echo 'Before'; });
        $this->assertEquals("BeforeContent", $block->content());
    }

    public function testBlockPrependTemplateRender()
    {
        $tpl = new Template('test');
        $tpl->layout('layouts/block');
        $block = $tpl->block('js')->content(function() { })
            ->prepend(function() { echo 'Content'; });
        $this->assertEquals("Content<div><p>Test</p></div>", $tpl->content());
    }

    public function testBlockRendersInLayoutWithSetContent()
    {
        $tpl = new Template('block');
        $tpl->layout('layouts/block');
        $this->assertEquals("<script src='one.js'></script>\n<script src='two.js'></script>\n<div><p>Test</p></div>", $tpl->content());
    }
}
