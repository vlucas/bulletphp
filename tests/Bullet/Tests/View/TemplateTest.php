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
        $app->path('', function() {
            $this->path('template-test', function($request) {
                $this->get(function($request) {
                    return new \Bullet\View\Template('test');
                });
            });
        });
        $tpl = $app->run(new \Bullet\Request('GET', 'template-test'));
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

    public function testExceptionThrownInTemplate()
    {
        $app = new Bullet\App();

        $app->path('', function() {
            $this->path('test', function() {
                return new \Bullet\View\Template('exception');
            });
        });

        $res = $app->run(new \Bullet\Request('GET', '/test/'));

        // The template isn't rendered at this point, so everything should look peachy.
        $this->assertEquals(200, $res->status());

        // Render the template, and let it blow up.
        $c = $res->content();

        // The template should contain the exception, and the status should be the default 500
        $this->assertEquals(500, $res->status());

        // And the content is the standard status text
    }

    public function testTemplateDoesNotDoubleRender()
    {
        $app = new Bullet\App();

        $app->path('', function() {
            $this->path('renderCount', function() {
                return new \Bullet\View\Template('renderCount');
            });
        });

        $res = $app->run(new \Bullet\Request('GET', '/renderCount/'));

        $this->assertEquals('1', $res->content());
    }

    public function testTemplateRenderCacheCanBeCleared()
    {
        $app = new Bullet\App();

        $app->path('', function() {
            $this->path('renderCount', function() {
                
                $tpl = new \Bullet\View\Template('renderCount');
                $tpl->content();
                return $tpl->clearCachedContent();
            });
        });

        $res = $app->run(new \Bullet\Request('GET', '/renderCount/'));

        $this->assertEquals('3', $res->content());
    }

    public function testTemplateResponseCanBeModifiedAfterTheFact()
    {
        Template::config(array('path_layouts' => $this->templateDir . 'layouts/'));

        $app = new Bullet\App();

        $app->path('', function() {
            $this->path('variableSet', function() {
                return (new \Bullet\View\Template('variableSet'))
                    ->set('variable', 'one')
                    ->layout('div');
            });
        });

        $rsp = $app->run(new \Bullet\Request('GET', '/variableSet/'));

        $rsp->set('variable', 'two')->layout(false);

        $this->assertEquals('two', $rsp->content());
    }
}
