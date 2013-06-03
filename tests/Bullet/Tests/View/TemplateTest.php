<?php
namespace Bullet\Tests\View;
use Bullet;
use Bullet\View\Template;

class TemplateTest extends \PHPUnit_Framework_TestCase
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

    public function testTemplateHelperReturnsTemplateObjectInstance()
    {
        $app = new Bullet\App(array(
            'template.cfg' => array('path' => $this->templateDir)
        ));
        $app->path('template-test', function($request) use($app) {
            $app->get(function($request) use($app) {
                return $app->template('test');
            });
        });
        $tpl = $app->run('GET', 'template-test');
        $this->assertInstanceOf('Bullet\View\Template', $tpl);
        $this->assertEquals('<p>Test</p>', $tpl->content());
    }

    public function testTemplateLayoutVariablePassing()
    {
        Template::config(array('path_layouts' => $this->templateDir . 'layouts/'));
        $tpl = new Template('variable');
        $tpl->layout('variable');
        $this->assertEquals('bar', $tpl->content());
    }

    public function testAutoLayoutRenderingOnDoubleTemplateRender()
    {
        Template::config(array('path_layouts' => $this->templateDir . 'layouts/', 'auto_layout' => 'div'));
        $tpl = new Template('test');
        $tpl->layout(false);
        $content = $tpl->content();
        $tpl2 = new Template('test');
        $this->assertEquals('<div><p>Test</p></div>', $tpl2->content());
    }

    /**
     * @expectedException \Exception
     */
    public function testExceptionThrownInTemplate()
    {
        $app = new Bullet\App();
        $app->path('test', function() use($app) {
            return $app->template('exception');
        });

        $res = $app->run('GET', '/test/');
    }

    public function testTemplateHandlesExceptionThrownInToString()
    {
        $tpl = new Template('exception');
        $this->assertContains('Exception thrown inside a template! Oh noes!', (string) $tpl);
    }
}
