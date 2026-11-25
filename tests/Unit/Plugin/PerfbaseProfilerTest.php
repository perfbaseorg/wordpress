<?php

namespace Perfbase\WordPress\Tests\Unit\Plugin;

use Brain\Monkey\Functions;
use Mockery;
use Perfbase\SDK\Perfbase;
use Perfbase\WordPress\PerfbaseProfiler;
use Perfbase\WordPress\PerfbasePlugin;
use Perfbase\WordPress\Tests\Helpers\BaseWordPressTest;
use Perfbase\WordPress\Tests\Helpers\MockFactory;

/**
 * Test PerfbaseProfiler class
 */
class PerfbaseProfilerTest extends BaseWordPressTest
{
    private $mock_plugin;
    private $mock_perfbase;
    private $profiler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock_plugin = Mockery::mock(PerfbasePlugin::class);
        $this->mock_perfbase = MockFactory::createMockPerfbase();

        $this->mock_plugin->shouldReceive('get_perfbase')->andReturn($this->mock_perfbase);

        Functions\expect('add_action')->zeroOrMoreTimes();
        Functions\expect('add_filter')->zeroOrMoreTimes();

        $this->profiler = new PerfbaseProfiler($this->mock_plugin);
    }

    public function testConstructorRegistersEventHooks()
    {
        Functions\expect('add_action')->with('wp_head', Mockery::any(), 999);
        Functions\expect('add_action')->with('wp_footer', Mockery::any(), 1);
        Functions\expect('add_action')->with('shutdown', Mockery::any(), 1);
        Functions\expect('add_action')->with('after_setup_theme', Mockery::any(), 999);
        Functions\expect('add_action')->with('plugins_loaded', Mockery::any(), 999);
        Functions\expect('add_action')->with('wp_insert_post', Mockery::any(), 10, 3);
        Functions\expect('add_action')->with('wp_insert_comment', Mockery::any(), 10, 2);
        Functions\expect('add_action')->with('wp_login', Mockery::any(), 10, 2);
        Functions\expect('add_action')->with('wp_logout', Mockery::any());
        Functions\expect('add_action')->with('rest_api_init', Mockery::any());

        $profiler = new PerfbaseProfiler($this->mock_plugin);

        $this->assertInstanceOf(PerfbaseProfiler::class, $profiler);
    }

    public function testOnWpHeadSetsAttribute()
    {
        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.wp_head', 'reached')
            ->once();

        $this->profiler->on_wp_head();
        $this->addToAssertionCount(1);
    }

    public function testOnWpFooterSetsAttribute()
    {
        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.wp_footer', 'reached')
            ->once();

        $this->profiler->on_wp_footer();
        $this->addToAssertionCount(1);
    }

    public function testAddDatabaseStatsWithBasicCount()
    {
        Functions\when('get_num_queries')->justReturn(42);

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('database.queries.total', '42')
            ->once();

        $this->profiler->add_database_stats();
        $this->addToAssertionCount(1);
    }

    public function testAddDatabaseStatsWithSlowQueries()
    {
        Functions\when('get_num_queries')->justReturn(10);

        if (!defined('SAVEQUERIES')) {
            define('SAVEQUERIES', true);
        }

        $GLOBALS['wpdb'] = (object) [
            'queries' => [
                ['SELECT * FROM wp_posts', 0.05, 'stack'],
                ['SELECT * FROM wp_users', 0.15, 'stack'],
                ['UPDATE wp_options', 0.02, 'stack'],
                ['INSERT INTO wp_posts', 0.12, 'stack'],
            ],
        ];

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('database.queries.total', '10')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('database.queries.slow', '2')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('database.queries.total_time', Mockery::any())
            ->once();

        $this->profiler->add_database_stats();
        $this->addToAssertionCount(1);
    }

    /**
     * after_setup_theme fires before the trace span starts, where
     * perfbase_set_attribute() is a no-op, so nothing may be written yet.
     */
    public function testProfileThemeSetupWritesNothingBeforeShutdown()
    {
        $this->mock_perfbase->shouldNotReceive('setAttribute');

        $this->profiler->profile_theme_setup();
        $this->addToAssertionCount(1);
    }

    public function testEarlyAttributesAreWrittenAtShutdown()
    {
        Functions\when('get_option')->alias(function ($option, $default = []) {
            if ($option === 'active_plugins') {
                return [
                    'plugin1/plugin1.php',
                    'plugin2/plugin2.php',
                    'plugin3/plugin3.php',
                ];
            }

            return $default;
        });

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.theme_setup', 'completed')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.plugins.count', '3')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.plugins_loaded', 'completed')
            ->once();

        $this->profiler->profile_theme_setup();
        $this->profiler->profile_plugins_loaded();
        $this->profiler->add_database_stats();
        $this->addToAssertionCount(1);
    }

    public function testPluginsCountTreatsNonArrayOptionAsEmpty()
    {
        Functions\when('get_option')->justReturn(false);

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.plugins.count', '0')
            ->once();

        $this->profiler->profile_plugins_loaded();
        $this->profiler->add_database_stats();
        $this->addToAssertionCount(1);
    }

    public function testProfilePostSaveInsert()
    {
        $mock_post = $this->createMockPost([
            'ID' => 123,
            'post_type' => 'post',
        ]);

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.post.insert', '123')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.post.insert.type', 'post')
            ->once();

        $this->profiler->profile_post_save(123, $mock_post, false);
        $this->addToAssertionCount(1);
    }

    public function testProfilePostSaveUpdate()
    {
        $mock_post = $this->createMockPost([
            'ID' => 456,
            'post_type' => 'page',
        ]);

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.post.update', '456')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.post.update.type', 'page')
            ->once();

        $this->profiler->profile_post_save(456, $mock_post, true);
        $this->addToAssertionCount(1);
    }

    public function testProfileCommentInsert()
    {
        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.comment.insert', '789')
            ->once();

        $this->profiler->profile_comment_insert(789, ['comment_ID' => 789]);
        $this->addToAssertionCount(1);
    }

    public function testProfileUserLogin()
    {
        $mock_user = $this->createMockUser(['ID' => 123]);

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.user.login', '123')
            ->once();

        $this->profiler->profile_user_login('testuser', $mock_user);
        $this->addToAssertionCount(1);
    }

    public function testProfileUserLogout()
    {
        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.user.logout', 'true')
            ->once();

        $this->profiler->profile_user_logout();
        $this->addToAssertionCount(1);
    }

    public function testInitRestProfiling()
    {
        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.rest_api', 'initialized')
            ->once();

        $this->profiler->init_rest_profiling();
        $this->assertTrue(true);
    }

    public function testMethodsHandleNullPerfbaseGracefully()
    {
        $mock_plugin = Mockery::mock(PerfbasePlugin::class);
        $mock_plugin->shouldReceive('get_perfbase')->andReturn(null);

        Functions\expect('add_action')->zeroOrMoreTimes();
        Functions\expect('add_filter')->zeroOrMoreTimes();

        $profiler = new PerfbaseProfiler($mock_plugin);

        $profiler->on_wp_head();
        $profiler->on_wp_footer();
        $profiler->add_database_stats();
        $profiler->profile_theme_setup();
        $profiler->profile_plugins_loaded();
        $profiler->profile_user_logout();

        $this->assertTrue(true);
    }

    public function testWooCommerceVersionIsGuardedWhenSingletonUnavailable()
    {
        if (!class_exists('WooCommerce')) {
            if (!class_exists('PerfbaseTestWooCommerceStub', false)) {
                eval('class PerfbaseTestWooCommerceStub {}');
            }

            class_alias('PerfbaseTestWooCommerceStub', 'WooCommerce');
        }

        if (!function_exists('WC')) {
            eval('function WC() { return null; }');
        }

        // The plugin boots on plugins_loaded, before the trace span starts, so
        // constructing the profiler must not write anything yet.
        $spy_perfbase = Mockery::spy(Perfbase::class);
        $spy_plugin = Mockery::mock(PerfbasePlugin::class);
        $spy_plugin->shouldReceive('get_perfbase')->andReturn($spy_perfbase);

        new PerfbaseProfiler($spy_plugin);

        $spy_perfbase->shouldNotHaveReceived('setAttribute');

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('woocommerce.active', 'true')
            ->once();

        $this->mock_perfbase
            ->shouldNotReceive('setAttribute')
            ->with('woocommerce.version', Mockery::any());

        $profiler = new PerfbaseProfiler($this->mock_plugin);

        $profiler->add_database_stats();

        $this->assertInstanceOf(PerfbaseProfiler::class, $profiler);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWooCommerceVersionIsWrittenAtShutdown()
    {
        eval('class PerfbaseTestWooCommerceStub {}');
        class_alias('PerfbaseTestWooCommerceStub', 'WooCommerce');

        $woocommerce = new \stdClass();
        $woocommerce->version = '9.8.1';
        $GLOBALS['perfbase_test_woocommerce'] = $woocommerce;
        eval('function WC() { return $GLOBALS["perfbase_test_woocommerce"]; }');

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('woocommerce.active', 'true')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('woocommerce.version', '9.8.1')
            ->once();

        $profiler = new PerfbaseProfiler($this->mock_plugin);
        $profiler->add_database_stats();

        $this->assertInstanceOf(PerfbaseProfiler::class, $profiler);
    }

    /**
     * Build the profiler with WooCommerce present and return every callback
     * it registered, grouped by hook name.
     *
     * @return array<string, array<int, callable>>
     */
    private function captureWooCommerceHooks(): array
    {
        if (!class_exists('WooCommerce')) {
            if (!class_exists('PerfbaseTestWooCommerceStub', false)) {
                eval('class PerfbaseTestWooCommerceStub {}');
            }

            class_alias('PerfbaseTestWooCommerceStub', 'WooCommerce');
        }

        if (!function_exists('WC')) {
            eval('function WC() { return null; }');
        }

        $hooks = [];
        Functions\when('add_action')->alias(function ($hook, $callback) use (&$hooks) {
            $hooks[$hook][] = $callback;

            return true;
        });

        new PerfbaseProfiler($this->mock_plugin);

        return $hooks;
    }

    public function testWooCommercePageDetectionWaitsForTemplateRedirect()
    {
        $hooks = $this->captureWooCommerceHooks();

        // is_shop() and friends read the main query, which does not exist
        // when woocommerce_init fires on init.
        $this->assertArrayNotHasKey('woocommerce_init', $hooks);
        $this->assertCount(1, $hooks['template_redirect']);
    }

    public function testWooCommerceShopPageIsRecorded()
    {
        $hooks = $this->captureWooCommerceHooks();

        Functions\when('is_shop')->justReturn(true);

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('woocommerce.page', 'shop')
            ->once();

        foreach ($hooks['template_redirect'] as $callback) {
            $callback();
        }

        $this->addToAssertionCount(1);
    }

    public function testWooCommerceProductPageIsRecorded()
    {
        $hooks = $this->captureWooCommerceHooks();

        Functions\when('is_shop')->justReturn(false);
        Functions\when('is_product')->justReturn(true);
        Functions\when('wc_get_product')
            ->justReturn(MockFactory::createMockWooCommerceProduct(['id' => 7, 'type' => 'variable']));

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('woocommerce.page', 'product')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('woocommerce.product.id', '7')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('woocommerce.product.type', 'variable')
            ->once();

        foreach ($hooks['template_redirect'] as $callback) {
            $callback();
        }

        $this->addToAssertionCount(1);
    }
}
