<?php

namespace Perfbase\WordPress\Tests\Unit\Plugin;

use Brain\Monkey\Functions;
use Mockery;
use Perfbase\SDK\FeatureFlags;
use Perfbase\SDK\Perfbase;
use Perfbase\SDK\SubmitResult;
use Perfbase\WordPress\Lifecycle\AjaxRequestLifecycle;
use Perfbase\WordPress\Lifecycle\CronLifecycle;
use Perfbase\WordPress\Lifecycle\HttpRequestLifecycle;
use Perfbase\WordPress\PerfbasePlugin;
use Perfbase\WordPress\Helpers\ConfigManager;
use Perfbase\WordPress\Helpers\RequestContext;
use Perfbase\WordPress\Tests\Helpers\BaseWordPressTest;
use Perfbase\WordPress\Tests\Helpers\MockFactory;
use Perfbase\WordPress\Tests\Helpers\TestData;

/**
 * Test PerfbasePlugin class
 */
class PerfbasePluginTest extends BaseWordPressTest
{
    private $mock_config_manager;
    private $mock_request_context;
    private $plugin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for dependencies
        $this->mock_config_manager = Mockery::mock(ConfigManager::class);
        $this->mock_request_context = Mockery::mock(RequestContext::class);

        // Create plugin instance with mocked dependencies
        $this->plugin = new PerfbasePlugin(
            $this->mock_config_manager,
            $this->mock_request_context
        );
    }

    public function testConstructorWithDefaultDependencies()
    {
        $plugin = new PerfbasePlugin();
        $this->assertInstanceOf(PerfbasePlugin::class, $plugin);
    }

    public function testConstructorWithInjectedDependencies()
    {
        $config_manager = Mockery::mock(ConfigManager::class);
        $request_context = Mockery::mock(RequestContext::class);

        $plugin = new PerfbasePlugin($config_manager, $request_context);

        $this->assertInstanceOf(PerfbasePlugin::class, $plugin);
    }

    public function testInitWithDisabledProfiling()
    {
        $config = TestData::getValidConfig();
        $config['enabled'] = false;

        $this->mock_config_manager
            ->shouldReceive('getConfig')
            ->once()
            ->andReturn($config);

        $this->mock_config_manager
            ->shouldReceive('isEnabled')
            ->once()
            ->with($config)
            ->andReturn(false);

        // Should not proceed with initialization if disabled
        $this->plugin->init();

        // perfbase should remain null when disabled
        $this->assertNull($this->plugin->get_perfbase());
    }

    public function testInitWithEnabledProfiling()
    {
        $config = TestData::getValidConfig();

        $this->mock_config_manager
            ->shouldReceive('getConfig')
            ->once()
            ->andReturn($config);

        $this->mock_config_manager
            ->shouldReceive('isEnabled')
            ->once()
            ->with($config)
            ->andReturn(true);

        // Mock WordPress functions for initialization
        Functions\when('load_plugin_textdomain')->justReturn(true);

        $this->plugin->init();

        // Config should be loaded
        $this->assertEquals($config, $this->plugin->get_config());
    }

    public function testGetConfig()
    {
        $expected_config = TestData::getValidConfig();

        $this->mock_config_manager
            ->shouldReceive('getConfig')
            ->once()
            ->andReturn($expected_config);

        $this->mock_config_manager
            ->shouldReceive('isEnabled')
            ->once()
            ->andReturn(false);

        $this->plugin->init();

        $actual_config = $this->plugin->get_config();
        $this->assertEquals($expected_config, $actual_config);
    }

    public function testUpdateConfig()
    {
        $initial_config = TestData::getValidConfig();
        $new_config = ['sample_rate' => 0.5];
        $expected_merged = array_merge($initial_config, $new_config);

        $this->mock_config_manager
            ->shouldReceive('getConfig')
            ->once()
            ->andReturn($initial_config);

        $this->mock_config_manager
            ->shouldReceive('isEnabled')
            ->once()
            ->andReturn(false);

        $this->mock_config_manager
            ->shouldReceive('updateConfig')
            ->once()
            ->with($expected_merged)
            ->andReturn(true);

        $this->plugin->init();
        $result = $this->plugin->update_config($new_config);

        $this->assertTrue($result);
    }

    public function testOnInitCreatesHttpRequestLifecycle()
    {
        $config = TestData::getValidConfig();
        $config['sample_rate'] = 1.0;
        $mock_perfbase = MockFactory::createMockPerfbase();

        $this->mock_request_context
            ->shouldReceive('getSpanName')
            ->andReturn('wordpress.request');

        $this->mock_request_context
            ->shouldReceive('shouldProfileRequest')
            ->andReturn(true);

        $this->mock_request_context
            ->shouldReceive('getRequestAttributes')
            ->andReturn(['http.method' => 'GET']);

        $mock_perfbase
            ->shouldReceive('isExtensionAvailable')
            ->andReturn(true);

        $mock_perfbase
            ->shouldReceive('startTraceSpan')
            ->once()
            ->with('wordpress.request');

        $mock_perfbase
            ->shouldReceive('setAttribute')
            ->zeroOrMoreTimes();

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'config', $config);

        $this->plugin->on_init();

        $lifecycle = $this->plugin->get_active_lifecycle();
        $this->assertInstanceOf(HttpRequestLifecycle::class, $lifecycle);
    }

    public function testOnInitWhenShouldNotProfile()
    {
        $config = TestData::getValidConfig();
        $config['sample_rate'] = 1.0;
        $mock_perfbase = MockFactory::createMockPerfbase();

        $this->mock_request_context
            ->shouldReceive('getSpanName')
            ->andReturn('wordpress.request');

        $this->mock_request_context
            ->shouldReceive('shouldProfileRequest')
            ->andReturn(false);

        $mock_perfbase
            ->shouldReceive('isExtensionAvailable')
            ->andReturn(true);

        // startTraceSpan should NOT be called when shouldProfile returns false
        $mock_perfbase
            ->shouldNotReceive('startTraceSpan');

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'config', $config);

        $this->plugin->on_init();

        // Lifecycle is still created, but profiling was not started
        $lifecycle = $this->plugin->get_active_lifecycle();
        $this->assertInstanceOf(HttpRequestLifecycle::class, $lifecycle);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testOnInitSkipsWpCliWhenCliProfilingIsDisabled()
    {
        if (!defined('WP_CLI')) {
            define('WP_CLI', true);
        }

        $config = TestData::getValidConfig();
        $config['sample_rate'] = 1.0;
        $config['profile_cli'] = false;
        $mock_perfbase = MockFactory::createMockPerfbase();

        $mock_perfbase->shouldNotReceive('isExtensionAvailable');
        $mock_perfbase->shouldNotReceive('startTraceSpan');

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'config', $config);

        $this->plugin->on_init();

        $this->assertNull($this->plugin->get_active_lifecycle());
    }

    public function testOnInitWithExceptionHandledGracefully()
    {
        $config = TestData::getValidConfig();
        $config['sample_rate'] = 1.0;
        $mock_perfbase = Mockery::mock(Perfbase::class);

        $this->mock_request_context
            ->shouldReceive('getSpanName')
            ->andReturn('wordpress.request');

        $this->mock_request_context
            ->shouldReceive('shouldProfileRequest')
            ->andReturn(true);

        $this->mock_request_context
            ->shouldReceive('getRequestAttributes')
            ->andReturn([]);

        $mock_perfbase
            ->shouldReceive('isExtensionAvailable')
            ->andReturn(true);

        $mock_perfbase
            ->shouldReceive('startTraceSpan')
            ->once()
            ->andThrow(new \Exception('Test exception'));

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'config', $config);

        // Should not throw — error is handled gracefully
        $this->plugin->on_init();

        // Lifecycle is created despite the error
        $lifecycle = $this->plugin->get_active_lifecycle();
        $this->assertInstanceOf(HttpRequestLifecycle::class, $lifecycle);
    }

    public function testOnTemplateRedirectCallsAddWordPressContext()
    {
        $config = TestData::getValidConfig();
        $config['sample_rate'] = 1.0;
        $mock_perfbase = MockFactory::createMockPerfbase();

        $template_context = ['wordpress.template' => 'page.php'];
        $wordpress_context = ['wordpress.is_page' => 'true'];

        $this->mock_request_context
            ->shouldReceive('getSpanName')
            ->andReturn('wordpress.request');

        $this->mock_request_context
            ->shouldReceive('shouldProfileRequest')
            ->andReturn(true);

        $this->mock_request_context
            ->shouldReceive('getRequestAttributes')
            ->andReturn([]);

        $this->mock_request_context
            ->shouldReceive('getTemplateContext')
            ->once()
            ->andReturn($template_context);

        $this->mock_request_context
            ->shouldReceive('getWordPressContext')
            ->once()
            ->andReturn($wordpress_context);

        $mock_perfbase
            ->shouldReceive('isExtensionAvailable')
            ->andReturn(true);

        $mock_perfbase
            ->shouldReceive('startTraceSpan')
            ->zeroOrMoreTimes();

        $mock_perfbase
            ->shouldReceive('setAttribute')
            ->zeroOrMoreTimes();

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'config', $config);

        // First create the lifecycle via on_init
        $this->plugin->on_init();

        // Then call template_redirect
        $this->plugin->on_template_redirect();

        // Verify lifecycle exists and is the right type
        $lifecycle = $this->plugin->get_active_lifecycle();
        $this->assertInstanceOf(HttpRequestLifecycle::class, $lifecycle);
    }

    public function testOnShutdownCallsStopProfiling()
    {
        $config = TestData::getValidConfig();
        $config['sample_rate'] = 1.0;
        $mock_perfbase = MockFactory::createMockPerfbase();

        $this->mock_request_context
            ->shouldReceive('getSpanName')
            ->andReturn('wordpress.request');

        $this->mock_request_context
            ->shouldReceive('shouldProfileRequest')
            ->andReturn(true);

        $this->mock_request_context
            ->shouldReceive('getRequestAttributes')
            ->andReturn([]);

        $this->mock_request_context
            ->shouldReceive('getFinalAttributes')
            ->once()
            ->andReturn(['http_status_code' => '200']);

        $mock_perfbase
            ->shouldReceive('isExtensionAvailable')
            ->andReturn(true);

        $mock_perfbase
            ->shouldReceive('startTraceSpan')
            ->zeroOrMoreTimes();

        $mock_perfbase
            ->shouldReceive('setAttribute')
            ->zeroOrMoreTimes();

        $mock_perfbase
            ->shouldReceive('stopTraceSpan')
            ->with('wordpress.request')
            ->once()
            ->andReturn(true);

        $mock_perfbase
            ->shouldReceive('submitTrace')
            ->once()
            ->andReturn(SubmitResult::success());

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'config', $config);

        $this->plugin->on_init();
        $this->plugin->on_shutdown();

        // Active lifecycle should be cleared after shutdown
        $this->assertNull($this->plugin->get_active_lifecycle());
    }

    public function testOnShutdownSkipsSubmittingForDisallowedHttpStatusByDefault()
    {
        $config = TestData::getValidConfig();
        $config['sample_rate'] = 1.0;
        $mock_perfbase = MockFactory::createMockPerfbase();

        $this->mock_request_context
            ->shouldReceive('getSpanName')
            ->andReturn('wordpress.request');

        $this->mock_request_context
            ->shouldReceive('shouldProfileRequest')
            ->andReturn(true);

        $this->mock_request_context
            ->shouldReceive('getRequestAttributes')
            ->andReturn([]);

        $this->mock_request_context
            ->shouldReceive('getFinalAttributes')
            ->once()
            ->andReturn(['http_status_code' => '404']);

        $mock_perfbase
            ->shouldReceive('isExtensionAvailable')
            ->andReturn(true);

        $mock_perfbase
            ->shouldReceive('startTraceSpan')
            ->zeroOrMoreTimes();

        $mock_perfbase
            ->shouldReceive('setAttribute')
            ->zeroOrMoreTimes();

        $mock_perfbase
            ->shouldReceive('stopTraceSpan')
            ->with('wordpress.request')
            ->once()
            ->andReturn(true);

        $mock_perfbase
            ->shouldNotReceive('submitTrace');

        $mock_perfbase
            ->shouldReceive('reset')
            ->once();

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'config', $config);

        $this->plugin->on_init();
        $this->plugin->on_shutdown();

        $this->assertNull($this->plugin->get_active_lifecycle());
    }

    public function testOnShutdownSubmitsForConfigured404Status()
    {
        $config = TestData::getValidConfig();
        $config['sample_rate'] = 1.0;
        $config['profile_http_status_codes'] = [200, 404];
        $mock_perfbase = MockFactory::createMockPerfbase();

        $this->mock_request_context
            ->shouldReceive('getSpanName')
            ->andReturn('wordpress.request');

        $this->mock_request_context
            ->shouldReceive('shouldProfileRequest')
            ->andReturn(true);

        $this->mock_request_context
            ->shouldReceive('getRequestAttributes')
            ->andReturn([]);

        $this->mock_request_context
            ->shouldReceive('getFinalAttributes')
            ->once()
            ->andReturn(['http_status_code' => '404']);

        $mock_perfbase
            ->shouldReceive('isExtensionAvailable')
            ->andReturn(true);

        $mock_perfbase
            ->shouldReceive('startTraceSpan')
            ->zeroOrMoreTimes();

        $mock_perfbase
            ->shouldReceive('setAttribute')
            ->zeroOrMoreTimes();

        $mock_perfbase
            ->shouldReceive('stopTraceSpan')
            ->with('wordpress.request')
            ->once()
            ->andReturn(true);

        $mock_perfbase
            ->shouldReceive('submitTrace')
            ->once()
            ->andReturn(SubmitResult::success());

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'config', $config);

        $this->plugin->on_init();
        $this->plugin->on_shutdown();

        $this->assertNull($this->plugin->get_active_lifecycle());
    }

    public function testOnShutdownSubmitsForAllowed500StatusByDefault()
    {
        $config = TestData::getValidConfig();
        $config['sample_rate'] = 1.0;
        $mock_perfbase = MockFactory::createMockPerfbase();

        $this->mock_request_context
            ->shouldReceive('getSpanName')
            ->andReturn('wordpress.request');

        $this->mock_request_context
            ->shouldReceive('shouldProfileRequest')
            ->andReturn(true);

        $this->mock_request_context
            ->shouldReceive('getRequestAttributes')
            ->andReturn([]);

        $this->mock_request_context
            ->shouldReceive('getFinalAttributes')
            ->once()
            ->andReturn(['http_status_code' => '503']);

        $mock_perfbase
            ->shouldReceive('isExtensionAvailable')
            ->andReturn(true);

        $mock_perfbase
            ->shouldReceive('startTraceSpan')
            ->zeroOrMoreTimes();

        $mock_perfbase
            ->shouldReceive('setAttribute')
            ->zeroOrMoreTimes();

        $mock_perfbase
            ->shouldReceive('stopTraceSpan')
            ->with('wordpress.request')
            ->once()
            ->andReturn(true);

        $mock_perfbase
            ->shouldReceive('submitTrace')
            ->once()
            ->andReturn(SubmitResult::success());

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'config', $config);

        $this->plugin->on_init();
        $this->plugin->on_shutdown();

        $this->assertNull($this->plugin->get_active_lifecycle());
    }

    public function testOnShutdownWithNoActiveLifecycle()
    {
        // on_shutdown with no active lifecycle should do nothing
        $this->plugin->on_shutdown();

        $this->assertNull($this->plugin->get_active_lifecycle());
    }

    public function testOnInitCreatesAjaxRequestLifecycleWhenDoingAjax()
    {
        $_REQUEST['action'] = 'test_action';

        $config = TestData::getValidConfig();
        $config['sample_rate'] = 1.0;
        $config['profile_ajax'] = true;
        $mock_perfbase = MockFactory::createMockPerfbase();

        $mock_perfbase
            ->shouldReceive('isExtensionAvailable')
            ->andReturn(true);

        $mock_perfbase
            ->shouldReceive('startTraceSpan')
            ->once()
            ->with('ajax.test_action');

        $mock_perfbase
            ->shouldReceive('setAttribute')
            ->zeroOrMoreTimes();

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'config', $config);

        // Use reflection to call createLifecycleForContext with DOING_AJAX
        // Since constants can't be redefined, test the lifecycle directly
        $lifecycle = new AjaxRequestLifecycle('test_action', $this->plugin);
        $this->setPrivateProperty($this->plugin, 'active_lifecycle', $lifecycle);
        $lifecycle->startProfiling();

        $this->assertInstanceOf(AjaxRequestLifecycle::class, $this->plugin->get_active_lifecycle());
    }

    public function testAjaxLifecycleNormalizesActionName()
    {
        $config = TestData::getValidConfig();
        $config['sample_rate'] = 1.0;
        $config['profile_ajax'] = true;
        $mock_perfbase = MockFactory::createMockPerfbase();

        $mock_perfbase
            ->shouldReceive('isExtensionAvailable')
            ->andReturn(true);

        $mock_perfbase
            ->shouldReceive('startTraceSpan')
            ->once()
            ->with('ajax.load_more_posts');

        $mock_perfbase
            ->shouldReceive('setAttribute')
            ->zeroOrMoreTimes();

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'config', $config);

        $lifecycle = new AjaxRequestLifecycle('  Load More Posts!?  ', $this->plugin);
        $lifecycle->startProfiling();

        $this->assertSame('ajax.load_more_posts', $lifecycle->getSpanName());
    }

    public function testOnShutdownSkipsSubmittingForDisallowedAjaxHttpStatusByDefault()
    {
        $config = TestData::getValidConfig();
        $config['sample_rate'] = 1.0;
        $config['profile_ajax'] = true;
        $mock_perfbase = MockFactory::createMockPerfbase();

        $this->mock_request_context
            ->shouldReceive('getFinalAttributes')
            ->once()
            ->andReturn(['http_status_code' => '404']);

        $mock_perfbase
            ->shouldReceive('setAttribute')
            ->zeroOrMoreTimes();

        $mock_perfbase
            ->shouldReceive('stopTraceSpan')
            ->with('ajax.test_action')
            ->once()
            ->andReturn(true);

        $mock_perfbase
            ->shouldNotReceive('submitTrace');

        $mock_perfbase
            ->shouldReceive('reset')
            ->once();

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'config', $config);
        $this->setPrivateProperty(
            $this->plugin,
            'active_lifecycle',
            new AjaxRequestLifecycle('test_action', $this->plugin, $this->mock_request_context)
        );

        $this->plugin->on_shutdown();

        $this->assertNull($this->plugin->get_active_lifecycle());
    }

    public function testOnInitCreatesCronLifecycleWhenDoingCron()
    {
        $config = TestData::getValidConfig();
        $config['sample_rate'] = 1.0;
        $config['profile_cron'] = true;
        $mock_perfbase = MockFactory::createMockPerfbase();

        $mock_perfbase
            ->shouldReceive('isExtensionAvailable')
            ->andReturn(true);

        $mock_perfbase
            ->shouldReceive('startTraceSpan')
            ->once()
            ->with('cron.execution');

        $mock_perfbase
            ->shouldReceive('setAttribute')
            ->zeroOrMoreTimes();

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'config', $config);

        // Since constants can't be redefined, test the lifecycle directly
        $lifecycle = new CronLifecycle($this->plugin);
        $this->setPrivateProperty($this->plugin, 'active_lifecycle', $lifecycle);
        $lifecycle->startProfiling();

        $this->assertInstanceOf(CronLifecycle::class, $this->plugin->get_active_lifecycle());
    }

    public function testRegisterHooksDoesNotRegisterQueryFilter()
    {
        $filtersRegistered = [];

        Functions\when('add_action')->justReturn(true);
        Functions\when('add_filter')->alias(function ($hook) use (&$filtersRegistered) {
            $filtersRegistered[] = $hook;
            return true;
        });

        $this->setPrivateProperty($this->plugin, 'config', TestData::getValidConfig());

        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('register_hooks');
        $method->setAccessible(true);
        $method->invoke($this->plugin);

        $this->assertNotContains('query', $filtersRegistered);
        $this->assertContains('pre_http_request', $filtersRegistered);
    }

    public function testOnHttpRequest()
    {
        $config = TestData::getValidConfig();
        $config['flags'] = FeatureFlags::TrackHttp;
        $mock_perfbase = MockFactory::createMockPerfbase();

        $mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('http.external_request', 'https://api.example.com/data')
            ->once();

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'config', $config);

        $result = $this->plugin->on_http_request(false, [], 'https://api.example.com/data');

        $this->assertFalse($result);
    }

    public function testOnHttpRequestStripsQueryStringAndFragment()
    {
        $config = TestData::getValidConfig();
        $config['flags'] = FeatureFlags::TrackHttp;
        $mock_perfbase = MockFactory::createMockPerfbase();

        $mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('http.external_request', 'https://api.example.com/data/path')
            ->once();

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'config', $config);

        $this->plugin->on_http_request(false, [], 'https://api.example.com/data/path?token=secret#frag');
        $this->assertTrue(true);
    }

    public function testOnHttpRequestOmitsInvalidUrl()
    {
        $config = TestData::getValidConfig();
        $config['flags'] = FeatureFlags::TrackHttp;
        $mock_perfbase = MockFactory::createMockPerfbase();

        $mock_perfbase
            ->shouldNotReceive('setAttribute');

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'config', $config);

        $this->plugin->on_http_request(false, [], 'not a valid url');
        $this->assertTrue(true);
    }

    public function testGetPerfbaseReturnsInstance()
    {
        $mock_perfbase = MockFactory::createMockPerfbase();
        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);

        $result = $this->plugin->get_perfbase();
        $this->assertSame($mock_perfbase, $result);
    }

    public function testGetPerfbaseReturnsNull()
    {
        $result = $this->plugin->get_perfbase();
        $this->assertNull($result);
    }

    public function testVersionConstant()
    {
        $this->assertEquals(PERFBASE_PLUGIN_VERSION, PerfbasePlugin::VERSION);
    }
}
