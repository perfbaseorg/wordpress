<?php

namespace Perfbase\WordPress\Tests\Unit\Plugin;

use Brain\Monkey\Functions;
use Mockery;
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

    public function testProfileThemeSetup()
    {
        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.theme_setup', 'completed')
            ->once();

        $this->profiler->profile_theme_setup();
        $this->addToAssertionCount(1);
    }

    public function testProfilePluginsLoaded()
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
            ->with('wordpress.plugins.count', '3')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.plugins_loaded', 'completed')
            ->once();

        $this->profiler->profile_plugins_loaded();
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

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('woocommerce.active', 'true')
            ->once();

        $this->mock_perfbase
            ->shouldNotReceive('setAttribute')
            ->with('woocommerce.version', Mockery::any());

        $profiler = new PerfbaseProfiler($this->mock_plugin);

        $this->assertInstanceOf(PerfbaseProfiler::class, $profiler);
    }
}
