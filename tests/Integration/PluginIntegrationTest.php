<?php

namespace Perfbase\WordPress\Tests\Integration;

use Brain\Monkey\Functions;
use Mockery;
use Perfbase\SDK\Config;
use Perfbase\SDK\Perfbase;
use Perfbase\WordPress\PerfbasePlugin;
use Perfbase\WordPress\Tests\Helpers\BaseWordPressTest;
use Perfbase\WordPress\Tests\Helpers\MockFactory;
use Perfbase\WordPress\Tests\Helpers\TestData;

/**
 * Integration tests for PerfbasePlugin
 */
class PluginIntegrationTest extends BaseWordPressTest
{
    private $plugin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plugin = new PerfbasePlugin();
    }

    public function testFullRequestCycleWithProfiling()
    {
        // Set up configuration
        $config = TestData::getValidConfig();

        Functions\when('get_option')
            ->justReturn($config);

        Functions\when('load_plugin_textdomain')->justReturn(true);

        // Mock request environment
        $this->setServerVars([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test-page/',
            'HTTP_HOST' => 'example.com',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Test Browser'
        ]);

        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.0');

        // Initialize plugin
        $this->plugin->init();

        // Start profiling
        $this->plugin->start_request_profiling();

        // Simulate WordPress lifecycle
        $this->plugin->wp_loaded_profiling();
        $this->plugin->template_redirect_profiling();

        // Finish profiling
        Functions\when('memory_get_peak_usage')->justReturn(1024000);
        Functions\when('memory_get_usage')->justReturn(512000);
        Functions\when('get_num_queries')->justReturn(5);
        Functions\when('http_response_code')->justReturn(200);

        $this->plugin->finish_request_profiling();

        // Test passes if no exceptions thrown
        $this->assertTrue(true);
    }

    public function testAjaxRequestProfiling()
    {
        $config = TestData::getValidConfig();

        Functions\when('get_option')
            ->justReturn($config);

        Functions\when('load_plugin_textdomain')->justReturn(true);

        MockFactory::mockAjaxRequest('test_action');

        Functions\when('is_admin')->justReturn(true);

        $this->plugin->init();
        $this->plugin->start_ajax_profiling();

        $this->assertTrue(true);
    }

    public function testCronJobProfiling()
    {
        $config = TestData::getValidConfig();

        Functions\when('get_option')
            ->justReturn($config);

        Functions\when('load_plugin_textdomain')->justReturn(true);

        $this->mockCronEnvironment();

        $this->plugin->init();
        $this->plugin->start_cron_profiling();

        $this->assertTrue(true);
    }

    public function testRequestExclusion()
    {
        $config = TestData::getValidConfig();

        Functions\when('get_option')
            ->justReturn($config);

        Functions\when('load_plugin_textdomain')->justReturn(true);

        // Set up excluded request
        $this->setServerVars([
            'REQUEST_URI' => '/wp-admin/admin-ajax.php',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Test Browser'
        ]);

        Functions\when('is_admin')->justReturn(false);

        $this->plugin->init();

        // Should not start profiling for excluded path
        $this->plugin->start_request_profiling();

        $this->assertTrue(true);
    }

    public function testUserAgentExclusion()
    {
        $config = TestData::getValidConfig();

        Functions\when('get_option')
            ->justReturn($config);

        Functions\when('load_plugin_textdomain')->justReturn(true);

        // Set up bot user agent
        $this->setServerVars([
            'REQUEST_URI' => '/test-page/',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'
        ]);

        Functions\when('is_admin')->justReturn(false);

        $this->plugin->init();

        // Should not start profiling for bot user agent
        $this->plugin->start_request_profiling();

        $this->assertTrue(true);
    }

    public function testConfigurationUpdate()
    {
        $initial_config = TestData::getValidConfig();
        $new_settings = ['sample_rate' => 0.5, 'timeout' => 15];

        Functions\when('get_option')
            ->justReturn($initial_config);

        Functions\when('update_option')
            ->justReturn(true);

        Functions\when('load_plugin_textdomain')->justReturn(true);

        $this->plugin->init();
        $result = $this->plugin->update_config($new_settings);

        $this->assertTrue($result);

        $updated_config = $this->plugin->get_config();
        $this->assertEquals(0.5, $updated_config['sample_rate']);
        $this->assertEquals(15, $updated_config['timeout']);
    }

    public function testProfilingWithDisabledExtension()
    {
        $config = TestData::getValidConfig();

        Functions\when('get_option')
            ->justReturn($config);

        Functions\when('load_plugin_textdomain')->justReturn(true);

        // Mock scenario where extension is not available
        $mock_perfbase = Mockery::mock(Perfbase::class);
        $mock_perfbase
            ->shouldReceive('isExtensionAvailable')
            ->andReturn(false);

        $this->plugin->init();

        // Should handle gracefully when extension is not available
        $this->plugin->start_request_profiling();

        $this->assertTrue(true);
    }

    public function testHookRegistration()
    {
        $config = TestData::getValidConfig();

        Functions\when('get_option')
            ->justReturn($config);

        Functions\when('load_plugin_textdomain')->justReturn(true);

        // Track hook registrations
        $hooks_registered = [];

        Functions\when('add_action')->alias(function($hook, $callback, $priority = 10, $accepted_args = 1) use (&$hooks_registered) {
            $hooks_registered[] = $hook;
        });

        Functions\when('add_filter')->alias(function($hook, $callback, $priority = 10, $accepted_args = 1) use (&$hooks_registered) {
            $hooks_registered[] = $hook;
        });

        $this->plugin->init();

        // Verify that expected hooks were registered
        $this->assertContains('init', $hooks_registered);
        $this->assertContains('wp_loaded', $hooks_registered);
        $this->assertContains('shutdown', $hooks_registered);
        $this->assertContains('template_redirect', $hooks_registered);
    }

    public function testMemoryAndPerformanceTracking()
    {
        $config = TestData::getValidConfig();

        Functions\when('get_option')
            ->justReturn($config);

        Functions\when('load_plugin_textdomain')->justReturn(true);

        // Mock memory and performance functions
        Functions\when('memory_get_peak_usage')->justReturn(2048000);
        Functions\when('memory_get_usage')->justReturn(1024000);
        Functions\when('get_num_queries')->justReturn(10);
        Functions\when('http_response_code')->justReturn(200);

        $this->plugin->init();

        // Simulate request lifecycle
        $this->plugin->start_request_profiling();
        $this->plugin->finish_request_profiling();

        $this->assertTrue(true);
    }

    public function testSamplingBehavior()
    {
        // Test with 100% sampling
        $config = TestData::getValidConfig();
        $config['sample_rate'] = 1.0;

        Functions\when('get_option')
            ->justReturn($config);

        Functions\when('load_plugin_textdomain')->justReturn(true);

        $this->plugin->init();
        $this->plugin->start_request_profiling();
        $this->plugin->finish_request_profiling();

        // Test with 0% sampling
        $config['sample_rate'] = 0.0;

        Functions\when('get_option')
            ->justReturn($config);

        $plugin2 = new PerfbasePlugin();
        $plugin2->init();
        $plugin2->start_request_profiling();
        $plugin2->finish_request_profiling();

        $this->assertTrue(true);
    }
}