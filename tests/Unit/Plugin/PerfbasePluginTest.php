<?php

namespace Perfbase\WordPress\Tests\Unit\Plugin;

use Brain\Monkey\Functions;
use Mockery;
use Perfbase\SDK\Config;
use Perfbase\SDK\Perfbase;
use Perfbase\WordPress\PerfbaseAdmin;
use Perfbase\WordPress\PerfbasePlugin;
use Perfbase\WordPress\PerfbaseProfiler;
use Perfbase\WordPress\Helpers\ConfigManager;
use Perfbase\WordPress\Helpers\RequestContext;
use Perfbase\WordPress\Helpers\SamplingStrategy;
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
    private $mock_sampling_strategy;
    private $plugin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for dependencies
        $this->mock_config_manager = Mockery::mock(ConfigManager::class);
        $this->mock_request_context = Mockery::mock(RequestContext::class);
        $this->mock_sampling_strategy = Mockery::mock(SamplingStrategy::class);

        // Create plugin instance with mocked dependencies
        $this->plugin = new PerfbasePlugin(
            $this->mock_config_manager,
            $this->mock_request_context,
            $this->mock_sampling_strategy
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
        $sampling_strategy = Mockery::mock(SamplingStrategy::class);

        $plugin = new PerfbasePlugin($config_manager, $request_context, $sampling_strategy);

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

        $this->assertTrue(true); // If we get here without errors, test passes
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

        $this->assertTrue(true); // If we get here without errors, test passes
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

    public function testStartRequestProfilingWhenShouldNotProfile()
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

        $this->mock_request_context
            ->shouldReceive('shouldProfileRequest')
            ->once()
            ->with($config)
            ->andReturn(false);

        Functions\when('load_plugin_textdomain')->justReturn(true);

        $this->plugin->init();
        $this->plugin->start_request_profiling();

        // Should exit early without starting profiling
        $this->assertTrue(true);
    }

    public function testStartRequestProfilingSuccess()
    {
        $config = TestData::getValidConfig();
        $span_name = 'wordpress.request';
        $attributes = ['http.method' => 'GET', 'http.url' => 'https://example.com/'];

        // Set up mocks
        $mock_perfbase = MockFactory::createMockPerfbase();

        $this->mock_config_manager
            ->shouldReceive('getConfig')
            ->once()
            ->andReturn($config);

        $this->mock_config_manager
            ->shouldReceive('isEnabled')
            ->once()
            ->andReturn(true);

        $this->mock_request_context
            ->shouldReceive('shouldProfileRequest')
            ->once()
            ->with($config)
            ->andReturn(true);

        $this->mock_request_context
            ->shouldReceive('getSpanName')
            ->once()
            ->andReturn($span_name);

        $this->mock_request_context
            ->shouldReceive('getRequestAttributes')
            ->once()
            ->andReturn($attributes);

        $mock_perfbase
            ->shouldReceive('startTraceSpan')
            ->zeroOrMoreTimes();

        Functions\when('load_plugin_textdomain')->justReturn(true);

        // Use reflection to inject the mock Perfbase instance
        $this->plugin->init();
        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);

        $this->plugin->start_request_profiling();

        // Verify that the span was added to active spans
        $active_spans = $this->getPrivateProperty($this->plugin, 'active_spans');
        $this->assertContains($span_name, $active_spans);
    }

    public function testStartRequestProfilingWithException()
    {
        $config = TestData::getValidConfig();

        $mock_perfbase = Mockery::mock(Perfbase::class);
        $mock_perfbase
            ->shouldReceive('startTraceSpan')
            ->once()
            ->andThrow(new \Exception('Test exception'));

        $this->mock_config_manager
            ->shouldReceive('getConfig')
            ->once()
            ->andReturn($config);

        $this->mock_config_manager
            ->shouldReceive('isEnabled')
            ->once()
            ->andReturn(true);

        $this->mock_request_context
            ->shouldReceive('shouldProfileRequest')
            ->once()
            ->andReturn(true);

        $this->mock_request_context
            ->shouldReceive('getSpanName')
            ->once()
            ->andReturn('test.span');

        $this->mock_request_context
            ->shouldReceive('getRequestAttributes')
            ->once()
            ->andReturn([]);

        Functions\when('load_plugin_textdomain')->justReturn(true);

        $this->plugin->init();
        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);

        // Should not throw exception, but log error
        $this->plugin->start_request_profiling();

        // Active spans should remain empty
        $active_spans = $this->getPrivateProperty($this->plugin, 'active_spans');
        $this->assertEmpty($active_spans);
    }

    public function testWpLoadedProfiling()
    {
        $mock_perfbase = MockFactory::createMockPerfbase();

        $mock_perfbase
            ->shouldReceive('setAttribute')
            ->zeroOrMoreTimes();

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'active_spans', ['test.span']);

        $this->plugin->wp_loaded_profiling();

        $this->assertTrue(true); // If we get here without errors, test passes
    }

    public function testWpLoadedProfilingWithoutActiveSpans()
    {
        $mock_perfbase = MockFactory::createMockPerfbase();

        // Should not call setAttribute if no active spans
        $mock_perfbase
            ->shouldNotReceive('setAttribute');

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'active_spans', []);

        $this->plugin->wp_loaded_profiling();

        $this->assertTrue(true);
    }

    public function testTemplateRedirectProfiling()
    {
        $template_context = ['wordpress.template' => 'page.php'];
        $wordpress_context = ['wordpress.is_page' => 'true'];

        $mock_perfbase = MockFactory::createMockPerfbase();

        $this->mock_request_context
            ->shouldReceive('getTemplateContext')
            ->once()
            ->andReturn($template_context);

        $this->mock_request_context
            ->shouldReceive('getWordPressContext')
            ->once()
            ->andReturn($wordpress_context);

        $mock_perfbase
            ->shouldReceive('setAttribute')
            ->zeroOrMoreTimes();

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'active_spans', ['test.span']);

        $this->plugin->template_redirect_profiling();

        $this->assertTrue(true);
    }

    public function testFinishRequestProfilingSuccess()
    {
        $config = TestData::getValidConfig();
        $final_attributes = ['memory.peak' => '1024', 'database.queries' => '5'];
        $active_spans = ['wordpress.request'];

        $mock_perfbase = MockFactory::createMockPerfbase();

        $this->mock_request_context
            ->shouldReceive('getFinalAttributes')
            ->once()
            ->andReturn($final_attributes);

        $this->mock_sampling_strategy
            ->shouldReceive('getSamplingDecision')
            ->once()
            ->with($config)
            ->andReturn(true);

        $mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('memory.peak', '1024')
            ->zeroOrMoreTimes();

        $mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('database.queries', '5')
            ->zeroOrMoreTimes();

        $mock_perfbase
            ->shouldReceive('stopTraceSpan')
            ->with('wordpress.request')
            ->zeroOrMoreTimes();

        $mock_perfbase
            ->shouldReceive('submitTrace')
            ->zeroOrMoreTimes();

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'active_spans', $active_spans);
        $this->setPrivateProperty($this->plugin, 'config', $config);

        $this->plugin->finish_request_profiling();

        // Active spans should be cleared
        $final_spans = $this->getPrivateProperty($this->plugin, 'active_spans');
        $this->assertEmpty($final_spans);
    }

    public function testFinishRequestProfilingWithoutSampling()
    {
        $config = TestData::getValidConfig();
        $config['sample_rate'] = 0.0; // No sampling

        $mock_perfbase = MockFactory::createMockPerfbase();

        $this->mock_request_context
            ->shouldReceive('getFinalAttributes')
            ->once()
            ->andReturn([]);

        $this->mock_sampling_strategy
            ->shouldReceive('getSamplingDecision')
            ->once()
            ->with($config)
            ->andReturn(false);

        $mock_perfbase
            ->shouldReceive('stopTraceSpan')
            ->zeroOrMoreTimes();

        // Should not submit trace if not sampled
        $mock_perfbase
            ->shouldNotReceive('submitTrace');

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'active_spans', ['test.span']);
        $this->setPrivateProperty($this->plugin, 'config', $config);

        $this->plugin->finish_request_profiling();

        $this->assertTrue(true);
    }

    public function testFinishRequestProfilingWithoutActiveSpans()
    {
        $mock_perfbase = MockFactory::createMockPerfbase();

        // Should not proceed if no active spans
        $mock_perfbase
            ->shouldNotReceive('stopTraceSpan');

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($this->plugin, 'active_spans', []);

        $this->plugin->finish_request_profiling();

        $this->assertTrue(true);
    }

    public function testStartAjaxProfiling()
    {
        $_REQUEST['action'] = 'test_action';

        $mock_perfbase = MockFactory::createMockPerfbase();

        $expected_attributes = [
            'request.type' => 'ajax',
            'ajax.action' => 'test_action',
        ];

        $mock_perfbase
            ->shouldReceive('startTraceSpan')
            ->zeroOrMoreTimes();

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);

        $this->plugin->start_ajax_profiling();

        $active_spans = $this->getPrivateProperty($this->plugin, 'active_spans');
        $this->assertContains('ajax.test_action', $active_spans);
    }

    public function testStartCronProfiling()
    {
        $mock_perfbase = MockFactory::createMockPerfbase();

        $expected_attributes = [
            'request.type' => 'cron',
        ];

        $mock_perfbase
            ->shouldReceive('startTraceSpan')
            ->zeroOrMoreTimes();

        $this->setPrivateProperty($this->plugin, 'perfbase', $mock_perfbase);

        $this->plugin->start_cron_profiling();

        $active_spans = $this->getPrivateProperty($this->plugin, 'active_spans');
        $this->assertContains('cron.execution', $active_spans);
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
        $this->assertEquals('1.0.0', PerfbasePlugin::VERSION);
    }
}