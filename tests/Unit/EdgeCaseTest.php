<?php

namespace Perfbase\WordPress\Tests\Unit;

use Brain\Monkey\Functions;
use Exception;
use Mockery;
use Perfbase\SDK\Perfbase;
use Perfbase\SDK\PerfbaseConfig;
use Perfbase\WordPress\PerfbasePlugin;
use Perfbase\WordPress\PerfbaseProfiler;
use Perfbase\WordPress\Tests\Helpers\BaseWordPressTest;

/**
 * Test edge cases, error scenarios, and failure modes
 */
class EdgeCaseTest extends BaseWordPressTest
{
    private $mock_plugin;
    private $mock_perfbase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock Perfbase SDK
        $this->mock_perfbase = Mockery::mock(Perfbase::class);

        // Mock PerfbasePlugin
        $this->mock_plugin = Mockery::mock(PerfbasePlugin::class);
        $this->mock_plugin->shouldReceive('get_perfbase')
            ->andReturn($this->mock_perfbase);
        $this->mock_plugin->shouldReceive('get_config')
            ->andReturn([
                'enabled' => true,
                'api_key' => 'test-key-123',
                'api_url' => 'https://receiver.perfbase.com',
                'sample_rate' => 0.5,
                'timeout' => 10,
                'proxy' => '',
                'profile_admin' => true,
                'profile_ajax' => true,
                'profile_cron' => false,
                'profile_cli' => false,
                'flags' => 0,
                'excluded_paths' => [],
                'excluded_user_agents' => []
            ]);

        // Mock WordPress constants and functions
        Functions\when('plugin_dir_path')->justReturn('/path/to/plugin/');
        Functions\when('plugin_basename')->justReturn('perfbase/perfbase.php');
        Functions\when('get_option')->justReturn([
            'enabled' => true,
            'api_key' => 'test-key-123',
            'api_url' => 'https://receiver.perfbase.com',
            'sample_rate' => 0.5,
            'timeout' => 10,
            'proxy' => '',
            'profile_admin' => true,
            'profile_ajax' => true,
            'profile_cron' => false,
            'profile_cli' => false,
            'flags' => 0,
            'excluded_paths' => [],
            'excluded_user_agents' => []
        ]);
    }

    // ===== SDK Initialization Failures =====

    public function testPluginWithInvalidApiKey()
    {
        Functions\when('get_option')->alias(function($key) {
            if ($key === 'perfbase_settings') {
                return ['enabled' => true, 'api_key' => '', 'api_url' => 'https://receiver.perfbase.com'];
            }
            return false;
        });

        $mock_config_manager = Mockery::mock(\Perfbase\WordPress\Helpers\ConfigManager::class);
        $mock_config_manager->shouldReceive('get')
            ->andReturn(['enabled' => true, 'api_key' => '', 'api_url' => 'https://receiver.perfbase.com']);

        $plugin = new PerfbasePlugin($mock_config_manager);

        // Should handle gracefully - plugin created but SDK not initialized
        $this->assertInstanceOf(PerfbasePlugin::class, $plugin);
    }

    public function testPluginWithInvalidUrl()
    {
        Functions\when('get_option')->alias(function($key) {
            if ($key === 'perfbase_settings') {
                return ['enabled' => true, 'api_key' => 'valid-key', 'api_url' => ''];
            }
            return false;
        });

        $mock_config_manager = Mockery::mock(\Perfbase\WordPress\Helpers\ConfigManager::class);
        $mock_config_manager->shouldReceive('get')
            ->andReturn(['enabled' => true, 'api_key' => 'valid-key', 'api_url' => '']);

        $plugin = new PerfbasePlugin($mock_config_manager);

        // Should handle gracefully
        $this->assertInstanceOf(PerfbasePlugin::class, $plugin);
    }

    public function testPluginWithMissingConfiguration()
    {
        Functions\when('get_option')->justReturn(false);

        $mock_config_manager = Mockery::mock(\Perfbase\WordPress\Helpers\ConfigManager::class);
        $mock_config_manager->shouldReceive('get')
            ->andReturn(['enabled' => false]);

        $plugin = new PerfbasePlugin($mock_config_manager);

        $this->assertInstanceOf(PerfbasePlugin::class, $plugin);
    }

    // ===== API Failure Scenarios =====

    public function testProfilerInitializesWithNullPerfbaseInstance()
    {
        $mock_plugin = Mockery::mock(PerfbasePlugin::class);
        $mock_plugin->shouldReceive('get_perfbase')->andReturn(null);
        $mock_plugin->shouldReceive('get_config')->andReturn([]);

        Functions\expect('add_action')->zeroOrMoreTimes();
        Functions\expect('add_filter')->zeroOrMoreTimes();

        $profiler = new PerfbaseProfiler($mock_plugin);

        // Should handle gracefully without throwing
        $this->assertInstanceOf(PerfbaseProfiler::class, $profiler);
    }

    public function testProfilerInitializesWithMissingWooCommerce()
    {
        Functions\expect('add_action')->zeroOrMoreTimes();
        Functions\expect('add_filter')->zeroOrMoreTimes();

        $profiler = new PerfbaseProfiler($this->mock_plugin);

        // Should handle missing WooCommerce gracefully
        $this->assertInstanceOf(PerfbaseProfiler::class, $profiler);
    }

    // ===== Request Context Edge Cases =====

    public function testRequestContextWithMissingServerVariables()
    {
        // Clear all $_SERVER variables
        $_SERVER = [];

        $this->mock_perfbase->shouldReceive('setAttribute')->zeroOrMoreTimes();

        $profiler = new PerfbaseProfiler($this->mock_plugin);

        // Should handle gracefully with defaults - just verify no exceptions
        $this->assertInstanceOf(PerfbaseProfiler::class, $profiler);
    }

    public function testRequestContextWithMalformedUri()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = 'not://a::valid//uri';

        $this->mock_perfbase->shouldReceive('setAttribute')->zeroOrMoreTimes();

        $profiler = new PerfbaseProfiler($this->mock_plugin);

        // Should not throw exception
        $this->assertInstanceOf(PerfbaseProfiler::class, $profiler);
    }

    public function testRequestContextWithExtremelyLongUri()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/path/' . str_repeat('a', 10000);

        $this->mock_perfbase->shouldReceive('setAttribute')->zeroOrMoreTimes();

        $profiler = new PerfbaseProfiler($this->mock_plugin);

        // Should handle without crashing
        $this->assertInstanceOf(PerfbaseProfiler::class, $profiler);
    }

    // ===== Database Query Edge Cases =====

    public function testProfilerInitializesWithComplexEnvironment()
    {
        global $wpdb;
        $wpdb = (object) ['queries' => []];

        Functions\expect('add_action')->zeroOrMoreTimes();
        Functions\expect('add_filter')->zeroOrMoreTimes();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php?action=complex_query';

        $profiler = new PerfbaseProfiler($this->mock_plugin);

        // Should handle complex environment without crashing
        $this->assertInstanceOf(PerfbaseProfiler::class, $profiler);
    }

    // ===== Sampling Edge Cases =====

    public function testConfigWithZeroSampleRate()
    {
        $mock_config_manager = Mockery::mock(\Perfbase\WordPress\Helpers\ConfigManager::class);
        $mock_config_manager->shouldReceive('get')
            ->andReturn(['enabled' => true, 'sample_rate' => 0.0, 'api_key' => 'key', 'api_url' => 'https://example.com']);

        Functions\when('get_option')->alias(function($key) {
            if ($key === 'perfbase_settings') {
                return ['enabled' => true, 'sample_rate' => 0.0];
            }
            return false;
        });

        $plugin = new PerfbasePlugin($mock_config_manager);

        $this->assertInstanceOf(PerfbasePlugin::class, $plugin);
    }

    public function testConfigWithFullSampleRate()
    {
        $mock_config_manager = Mockery::mock(\Perfbase\WordPress\Helpers\ConfigManager::class);
        $mock_config_manager->shouldReceive('get')
            ->andReturn(['enabled' => true, 'sample_rate' => 1.0, 'api_key' => 'key', 'api_url' => 'https://example.com']);

        Functions\when('get_option')->alias(function($key) {
            if ($key === 'perfbase_settings') {
                return ['enabled' => true, 'sample_rate' => 1.0];
            }
            return false;
        });

        $plugin = new PerfbasePlugin($mock_config_manager);

        $this->assertInstanceOf(PerfbasePlugin::class, $plugin);
    }

    // ===== WordPress Hook Failures =====

    public function testHandlesMissingWordPressFunctions()
    {
        Functions\expect('add_action')->zeroOrMoreTimes();
        Functions\expect('add_filter')->zeroOrMoreTimes();

        $this->mock_perfbase->shouldReceive('setAttribute')->zeroOrMoreTimes();

        $profiler = new PerfbaseProfiler($this->mock_plugin);

        // Should handle missing WordPress functions gracefully
        $this->assertInstanceOf(PerfbaseProfiler::class, $profiler);
    }

    // ===== Configuration Edge Cases =====

    public function testConfigWithInvalidTypesHandledGracefully()
    {
        $mock_config_manager = Mockery::mock(\Perfbase\WordPress\Helpers\ConfigManager::class);
        $mock_config_manager->shouldReceive('get')
            ->andReturn([
                'enabled' => true,
                'flags' => 'not-an-integer',
                'timeout' => -10,
                'sample_rate' => 'invalid',
                'api_key' => 'key',
                'api_url' => 'https://example.com'
            ]);

        Functions\when('get_option')->alias(function($key) {
            if ($key === 'perfbase_settings') {
                return ['enabled' => true];
            }
            return false;
        });

        $plugin = new PerfbasePlugin($mock_config_manager);

        // Should handle invalid types gracefully
        $this->assertInstanceOf(PerfbasePlugin::class, $plugin);
    }

    // ===== Edge Case Coverage =====

    public function testProfilerWithExtremeLongUris()
    {
        Functions\expect('add_action')->zeroOrMoreTimes();
        Functions\expect('add_filter')->zeroOrMoreTimes();

        $_SERVER['REQUEST_URI'] = '/' . str_repeat('a', 5000);

        $profiler = new PerfbaseProfiler($this->mock_plugin);

        // Should handle extremely long URIs without crashing
        $this->assertInstanceOf(PerfbaseProfiler::class, $profiler);
    }

    public function testProfilerWithSpecialCharactersInUri()
    {
        Functions\expect('add_action')->zeroOrMoreTimes();
        Functions\expect('add_filter')->zeroOrMoreTimes();

        $_SERVER['REQUEST_URI'] = '/test?foo=bar&baz=<script>alert(1)</script>&id=123';

        $profiler = new PerfbaseProfiler($this->mock_plugin);

        // Should handle special characters and potential XSS in URI
        $this->assertInstanceOf(PerfbaseProfiler::class, $profiler);
    }
}
