<?php
use Bullet\View\Template;

class ViewTemplateTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->templateDir = dirname(dirname(__DIR__)) . '/fixtures/templates/';

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

    public function testTemplateFilename()
    {
        $tpl = new Template('index');
        $this->assertEquals('index', $tpl->file());
    }

    public function testStaticConfigPath()
    {
        $tpl = new Template('index');
        $this->assertEquals($this->templateDir, $tpl->path());
    }

    public function testInstancePathOverridesStaticConfigPath()
    {
        $tpl = new Template('index');
        $tpl->path('should_override_static_config');
        $this->assertEquals('should_override_static_config', $tpl->path());
    }

    public function testTemplateGetsFileContent()
    {
        $tpl = new Template('test');
        $this->assertEquals('<p>Test</p>', $tpl->content());
    }

    public function testTemplateGetsFileAndParsesPHP()
    {
        $tpl = new Template('phptest');
        $this->assertEquals(date('Y'), $tpl->content());
    }

    public function testTemplateGetsRawContentWithoutParsingPHP()
    {
        $tpl = new Template('phptest');
        $this->assertEquals("<?php echo date('Y'); ?>", $tpl->content(false));
    }

    public function testFileVsFileNameMethods()
    {
        $tpl = new Template('index');
        $this->assertEquals('index', $tpl->file());
        $this->assertEquals('index.html.php', $tpl->fileName());
    }

    public function testTemplateLayoutWrapping()
    {
        Template::config(array('path_layouts' => $this->templateDir . 'layouts/'));
        $tpl = new Template('test');
        $tpl->layout('div');
        $this->assertEquals('<div><p>Test</p></div>', $tpl->content());
    }

    public function testTemplateLayoutAutoWrappingConfig()
    {
        Template::config(array(
          'path_layouts' => $this->templateDir . 'layouts/',
          'auto_layout' => 'div'
        ));
        $tpl = new Template('test');
        $this->assertEquals('<div><p>Test</p></div>', $tpl->content());
    }

    public function testTemplateLayoutWrappingWithoutParsingPHP()
    {
        Template::config(array('path_layouts' => $this->templateDir . 'layouts/'));
        $tpl = new Template('test');
        $tpl->layout('div');
        $this->assertEquals('<div><?php echo $yield; ?></div>', $tpl->content(false));
    }
}
