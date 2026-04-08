<?php

namespace Perfbase\WordPress\Tests\Integration;

use Brain\Monkey\Functions;
use Mockery;
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
 * Integration tests for PerfbasePlugin
 */
class PluginIntegrationTest extends BaseWordPressTest
{
    private $plugin;
    private $mock_config_manager;
    private $mock_request_context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock_config_manager = Mockery::mock(ConfigManager::class);
        $this->mock_request_context = Mockery::mock(RequestContext::class);

        $this->plugin = new PerfbasePlugin(
            $this->mock_config_manager,
            $this->mock_request_context
        );
    }

    /**
     * Helper to set up the plugin with a mock SDK and config for lifecycle tests.
     */
    private function setUpPluginWithMockSdk(array $config = [], array $perfbaseMethods = []): Mockery\MockInterface
    {
        $config = array_merge(TestData::getValidConfig(), ['sample_rate' => 1.0], $config);
        $mock_perfbase = MockFactory::createMockPerfbase($perfbaseMethods);

        $mock_perfbase->shouldReceive('isExtensionAvailable')->byDefault()->andReturn(true);

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'config', $config);

        return $mock_perfbase;
    }

    public function testFullRequestCycleWithProfiling()
    {
        $mock_perfbase = $this->setUpPluginWithMockSdk();

        $this->mock_request_context
            ->shouldReceive('getSpanName')
            ->andReturn('wordpress.request');

        $this->mock_request_context
            ->shouldReceive('shouldProfileRequest')
            ->andReturn(true);

        $this->mock_request_context
            ->shouldReceive('getRequestAttributes')
            ->andReturn(['http.method' => 'GET', 'http.url' => 'https://example.com/test-page/']);

        $this->mock_request_context
            ->shouldReceive('getTemplateContext')
            ->andReturn(['wordpress.template' => 'page.php']);

        $this->mock_request_context
            ->shouldReceive('getWordPressContext')
            ->andReturn(['wordpress.is_page' => 'true']);

        $this->mock_request_context
            ->shouldReceive('getFinalAttributes')
            ->andReturn(['http_status_code' => '200']);

        $mock_perfbase
            ->shouldReceive('startTraceSpan')
            ->with('wordpress.request')
            ->once();

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

        // Run full lifecycle
        $this->plugin->on_init();
        $this->assertInstanceOf(HttpRequestLifecycle::class, $this->plugin->get_active_lifecycle());

        $this->plugin->on_template_redirect();
        $this->plugin->on_shutdown();

        $this->assertNull($this->plugin->get_active_lifecycle());
    }

    public function testAjaxRequestProfiling()
    {
        $_REQUEST['action'] = 'test_action';

        $mock_perfbase = $this->setUpPluginWithMockSdk(['profile_ajax' => true]);

        $mock_perfbase
            ->shouldReceive('startTraceSpan')
            ->with('ajax.test_action')
            ->once();

        $mock_perfbase
            ->shouldReceive('setAttribute')
            ->zeroOrMoreTimes();

        $mock_perfbase
            ->shouldReceive('stopTraceSpan')
            ->with('ajax.test_action')
            ->once()
            ->andReturn(true);

        $mock_perfbase
            ->shouldReceive('submitTrace')
            ->once()
            ->andReturn(SubmitResult::success());

        // Context detection now happens in on_init() via createLifecycleForContext().
        // Since we can't set DOING_AJAX constant at runtime, create lifecycle directly.
        $lifecycle = new AjaxRequestLifecycle('test_action', $this->plugin);
        $this->setPrivateProperty($this->plugin, 'active_lifecycle', $lifecycle);
        $lifecycle->startProfiling();

        $this->assertInstanceOf(AjaxRequestLifecycle::class, $this->plugin->get_active_lifecycle());

        $this->plugin->on_shutdown();
        $this->assertNull($this->plugin->get_active_lifecycle());
    }

    public function testCronJobProfiling()
    {
        $mock_perfbase = $this->setUpPluginWithMockSdk(['profile_cron' => true]);

        $mock_perfbase
            ->shouldReceive('startTraceSpan')
            ->with('cron.execution')
            ->once();

        $mock_perfbase
            ->shouldReceive('setAttribute')
            ->zeroOrMoreTimes();

        $mock_perfbase
            ->shouldReceive('stopTraceSpan')
            ->with('cron.execution')
            ->once()
            ->andReturn(true);

        $mock_perfbase
            ->shouldReceive('submitTrace')
            ->once()
            ->andReturn(SubmitResult::success());

        // Context detection now happens in on_init() via createLifecycleForContext().
        // Since we can't set DOING_CRON constant at runtime, create lifecycle directly.
        $lifecycle = new CronLifecycle($this->plugin);
        $this->setPrivateProperty($this->plugin, 'active_lifecycle', $lifecycle);
        $lifecycle->startProfiling();

        $this->assertInstanceOf(CronLifecycle::class, $this->plugin->get_active_lifecycle());

        $this->plugin->on_shutdown();
        $this->assertNull($this->plugin->get_active_lifecycle());
    }

    public function testRequestExclusion()
    {
        $mock_perfbase = $this->setUpPluginWithMockSdk();

        $this->mock_request_context
            ->shouldReceive('getSpanName')
            ->andReturn('wordpress.request');

        $this->mock_request_context
            ->shouldReceive('shouldProfileRequest')
            ->andReturn(false);

        // Should not start profiling for excluded path
        $mock_perfbase
            ->shouldNotReceive('startTraceSpan');

        $this->plugin->on_init();

        // Lifecycle is created but profiling was not started
        $this->assertInstanceOf(HttpRequestLifecycle::class, $this->plugin->get_active_lifecycle());
    }

    public function testConfigurationUpdate()
    {
        $initial_config = TestData::getValidConfig();
        $new_settings = ['sample_rate' => 0.5, 'timeout' => 15];
        $expected_merged = array_merge($initial_config, $new_settings);

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

        Functions\when('load_plugin_textdomain')->justReturn(true);

        $this->plugin->init();
        $result = $this->plugin->update_config($new_settings);

        $this->assertTrue($result);

        $updated_config = $this->plugin->get_config();
        $this->assertEquals(0.5, $updated_config['sample_rate']);
        $this->assertEquals(15, $updated_config['timeout']);
    }

    public function testProfilingWithNullSdk()
    {
        // perfbase is null (extension not available, SDK init failed)
        $config = TestData::getValidConfig();
        $this->setPrivateProperty($this->plugin, 'config', $config);

        $this->mock_request_context
            ->shouldReceive('getSpanName')
            ->andReturn('wordpress.request');

        // on_init should not throw when perfbase is null
        $this->plugin->on_init();

        $lifecycle = $this->plugin->get_active_lifecycle();
        $this->assertInstanceOf(HttpRequestLifecycle::class, $lifecycle);

        // on_shutdown should not throw when perfbase is null
        $this->plugin->on_shutdown();
        $this->assertNull($this->plugin->get_active_lifecycle());
    }

    public function testHookRegistration()
    {
        $config = TestData::getValidConfig();

        $this->mock_config_manager
            ->shouldReceive('getConfig')
            ->once()
            ->andReturn($config);

        $this->mock_config_manager
            ->shouldReceive('isEnabled')
            ->once()
            ->andReturn(true);

        // Track hook registrations
        $hooks_registered = [];

        Functions\when('add_action')->alias(function ($hook, $callback, $priority = 10, $accepted_args = 1) use (&$hooks_registered) {
            $hooks_registered[] = $hook;
        });

        Functions\when('add_filter')->alias(function ($hook, $callback, $priority = 10, $accepted_args = 1) use (&$hooks_registered) {
            $hooks_registered[] = $hook;
        });

        Functions\when('load_plugin_textdomain')->justReturn(true);

        $this->plugin->init();

        // Verify that expected hooks were registered
        $this->assertContains('init', $hooks_registered);
        $this->assertContains('shutdown', $hooks_registered);
        $this->assertContains('template_redirect', $hooks_registered);
        $this->assertContains('pre_http_request', $hooks_registered);
        $this->assertContains('wp_die_handler', $hooks_registered);
    }

    public function testShutdownWithNoLifecycleDoesNothing()
    {
        // No lifecycle set — on_shutdown should exit gracefully
        $this->plugin->on_shutdown();

        $this->assertNull($this->plugin->get_active_lifecycle());
    }

    public function testDatabaseQueryProfilingIntegration()
    {
        $mock_perfbase = $this->setUpPluginWithMockSdk();

        $mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('database.last_query', Mockery::any())
            ->twice();

        $this->plugin->on_database_query('SELECT * FROM wp_posts WHERE ID = 1');
        $result = $this->plugin->on_database_query('INSERT INTO wp_postmeta (post_id, meta_key) VALUES (1, "key")');

        // Query should be returned unchanged
        $this->assertEquals('INSERT INTO wp_postmeta (post_id, meta_key) VALUES (1, "key")', $result);
    }
}
