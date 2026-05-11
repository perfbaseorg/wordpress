<?php

namespace Perfbase\WordPress\Tests\Functional;

use Brain\Monkey\Functions;
use Mockery;
use Perfbase\SDK\FeatureFlags;
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
 * Functional tests for complete request cycles
 */
class FullRequestCycleTest extends BaseWordPressTest
{
    private $mock_config_manager;
    private $mock_request_context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock_config_manager = Mockery::mock(ConfigManager::class);
        $this->mock_request_context = Mockery::mock(RequestContext::class);
    }

    /**
     * Helper to create a plugin with mock SDK ready for lifecycle tests.
     */
    private function createPluginWithMockSdk(array $configOverrides = []): array
    {
        $plugin = new PerfbasePlugin(
            $this->mock_config_manager,
            $this->mock_request_context
        );

        $config = array_merge(TestData::getValidConfig(), ['sample_rate' => 1.0], $configOverrides);
        $mock_perfbase = MockFactory::createMockPerfbase();

        $mock_perfbase->shouldReceive('isExtensionAvailable')->byDefault()->andReturn(true);
        $mock_perfbase->shouldReceive('startTraceSpan')->zeroOrMoreTimes();
        $mock_perfbase->shouldReceive('setAttribute')->zeroOrMoreTimes();
        $mock_perfbase->shouldReceive('stopTraceSpan')->zeroOrMoreTimes()->andReturn(true);
        $mock_perfbase->shouldReceive('submitTrace')->zeroOrMoreTimes()->andReturn(SubmitResult::success());

        $this->setPrivateProperty($plugin, 'perfbase', $mock_perfbase);
        $this->setPrivateProperty($plugin, 'config', $config);

        return [$plugin, $mock_perfbase];
    }

    public function testCompleteWordPressFrontendRequest()
    {
        [$plugin, $mock_perfbase] = $this->createPluginWithMockSdk();

        $this->mock_request_context
            ->shouldReceive('getSpanName')
            ->andReturn('http');

        $this->mock_request_context
            ->shouldReceive('shouldProfileRequest')
            ->andReturn(true);

        $this->mock_request_context
            ->shouldReceive('getRequestAttributes')
            ->andReturn([
                'http.method' => 'GET',
                'http.url' => 'https://example.com/',
                'action' => 'GET /',
            ]);

        $this->mock_request_context
            ->shouldReceive('getTemplateContext')
            ->andReturn(['wordpress.template' => 'front-page.php']);

        $this->mock_request_context
            ->shouldReceive('getWordPressContext')
            ->andReturn(['wordpress.is_front_page' => 'true']);

        $this->mock_request_context
            ->shouldReceive('getFinalAttributes')
            ->andReturn(['http_status_code' => '200']);

        // Full lifecycle: init -> template_redirect -> http -> shutdown
        $plugin->on_init();
        $this->assertInstanceOf(HttpRequestLifecycle::class, $plugin->get_active_lifecycle());

        $plugin->on_template_redirect();

        // Simulate outbound HTTP hook
        $plugin->on_http_request(null, [], 'https://api.example.com/data');

        $plugin->on_shutdown();
        $this->assertNull($plugin->get_active_lifecycle());
    }

    public function testCompleteAdminRequest()
    {
        [$plugin, $mock_perfbase] = $this->createPluginWithMockSdk(['profile_admin' => true]);

        $this->mock_request_context
            ->shouldReceive('getSpanName')
            ->andReturn('http');

        $this->mock_request_context
            ->shouldReceive('shouldProfileRequest')
            ->andReturn(true);

        $this->mock_request_context
            ->shouldReceive('getRequestAttributes')
            ->andReturn([
                'http.method' => 'GET',
                'http.url' => 'https://example.com/wp-admin/options-general.php?page=perfbase-settings',
                'action' => 'GET /wp-admin/options-general.php',
            ]);

        $this->mock_request_context
            ->shouldReceive('getFinalAttributes')
            ->andReturn(['http_status_code' => '200']);

        $plugin->on_init();
        $this->assertInstanceOf(HttpRequestLifecycle::class, $plugin->get_active_lifecycle());

        $plugin->on_shutdown();
        $this->assertNull($plugin->get_active_lifecycle());
    }

    public function testAjaxRequestCycle()
    {
        $_REQUEST['action'] = 'load_more_posts';

        [$plugin, $mock_perfbase] = $this->createPluginWithMockSdk();

        // Context detection now happens in on_init() via createLifecycleForContext().
        // Since constants can't be set at runtime, create lifecycle directly.
        $lifecycle = new AjaxRequestLifecycle('load_more_posts', $plugin);
        $this->setPrivateProperty($plugin, 'active_lifecycle', $lifecycle);
        $lifecycle->startProfiling();

        $this->assertInstanceOf(AjaxRequestLifecycle::class, $plugin->get_active_lifecycle());
        $this->assertEquals('ajax', $plugin->get_active_lifecycle()->getSpanName());

        $plugin->on_shutdown();
        $this->assertNull($plugin->get_active_lifecycle());
    }

    public function testCronJobCycle()
    {
        [$plugin, $mock_perfbase] = $this->createPluginWithMockSdk();

        // Context detection now happens in on_init() via createLifecycleForContext().
        // Since constants can't be set at runtime, create lifecycle directly.
        $lifecycle = new CronLifecycle($plugin);
        $this->setPrivateProperty($plugin, 'active_lifecycle', $lifecycle);
        $lifecycle->startProfiling();

        $this->assertInstanceOf(CronLifecycle::class, $plugin->get_active_lifecycle());
        $this->assertEquals('cron', $plugin->get_active_lifecycle()->getSpanName());

        $plugin->on_shutdown();
        $this->assertNull($plugin->get_active_lifecycle());
    }

    public function testRequestWithWooCommerce()
    {
        [$plugin, $mock_perfbase] = $this->createPluginWithMockSdk();

        $this->mock_request_context
            ->shouldReceive('getSpanName')
            ->andReturn('http');

        $this->mock_request_context
            ->shouldReceive('shouldProfileRequest')
            ->andReturn(true);

        $this->mock_request_context
            ->shouldReceive('getRequestAttributes')
            ->andReturn([
                'http.method' => 'GET',
                'http.url' => 'https://example.com/shop/',
                'action' => 'GET /shop/',
            ]);

        $this->mock_request_context
            ->shouldReceive('getTemplateContext')
            ->andReturn(['wordpress.template' => 'archive-product.php']);

        $this->mock_request_context
            ->shouldReceive('getWordPressContext')
            ->andReturn(['woocommerce.page' => 'shop']);

        $this->mock_request_context
            ->shouldReceive('getFinalAttributes')
            ->andReturn(['http_status_code' => '200']);

        $plugin->on_init();
        $plugin->on_template_redirect();
        $plugin->on_shutdown();

        $this->assertNull($plugin->get_active_lifecycle());
    }

    public function testHighVolumeRequest()
    {
        [$plugin, $mock_perfbase] = $this->createPluginWithMockSdk();

        $this->mock_request_context
            ->shouldReceive('getSpanName')
            ->andReturn('http');

        $this->mock_request_context
            ->shouldReceive('shouldProfileRequest')
            ->andReturn(true);

        $this->mock_request_context
            ->shouldReceive('getRequestAttributes')
            ->andReturn([
                'http.method' => 'GET',
                'http.url' => 'https://example.com/category/popular/',
                'action' => 'GET /category/popular/',
            ]);

        $this->mock_request_context
            ->shouldReceive('getTemplateContext')
            ->andReturn([]);

        $this->mock_request_context
            ->shouldReceive('getWordPressContext')
            ->andReturn(['wordpress.is_category' => 'true']);

        $this->mock_request_context
            ->shouldReceive('getFinalAttributes')
            ->andReturn(['http_status_code' => '200']);

        $plugin->on_init();

        // Simulate external API calls
        $plugin->on_http_request(null, [], 'https://api.analytics.example.com/track');
        $plugin->on_http_request(null, [], 'https://cdn.example.com/assets/script.js');

        $plugin->on_template_redirect();
        $plugin->on_shutdown();

        $this->assertNull($plugin->get_active_lifecycle());
    }
}
