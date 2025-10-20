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

        // Create mocks
        $this->mock_plugin = Mockery::mock(PerfbasePlugin::class);
        $this->mock_perfbase = MockFactory::createMockPerfbase();

        $this->mock_plugin->shouldReceive('get_perfbase')
            ->andReturn($this->mock_perfbase);

        // Expect hook registration in constructor
        Functions\expect('add_action')->zeroOrMoreTimes();
        Functions\expect('add_filter')->zeroOrMoreTimes();

        // Create profiler instance
        $this->profiler = new PerfbaseProfiler($this->mock_plugin);
    }

    public function testConstructorRegistersHooks()
    {
        Functions\expect('add_action')
            ->with('wp', Mockery::any(), 1);

        Functions\expect('add_action')
            ->with('template_redirect', Mockery::any(), 1);

        Functions\expect('add_action')
            ->with('wp_head', Mockery::any(), 999);

        Functions\expect('add_action')
            ->with('wp_footer', Mockery::any(), 1);

        Functions\expect('add_filter')
            ->with('query', Mockery::any(), 10, 1);

        $profiler = new PerfbaseProfiler($this->mock_plugin);

        // Verify profiler was created successfully
        $this->assertInstanceOf(PerfbaseProfiler::class, $profiler);
    }

    public function testOnWpReadyAddsWordPressContext()
    {
        Functions\when('is_front_page')->justReturn(true);
        Functions\when('is_home')->justReturn(false);
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_single')->justReturn(false);
        Functions\when('is_page')->justReturn(false);
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('is_singular')->justReturn(false);
        Functions\when('is_category')->justReturn(false);
        Functions\when('is_tag')->justReturn(false);
        Functions\when('is_tax')->justReturn(false);

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.is_front_page', 'true')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.is_home', 'false')
            ->once();

        $this->profiler->on_wp_ready();
    }

    public function testOnWpReadyAddsUserContext()
    {
        $mock_user = (object) [
            'ID' => 123,
            'roles' => ['administrator', 'editor']
        ];

        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('wp_get_current_user')->justReturn($mock_user);
        Functions\when('is_front_page')->justReturn(false);
        Functions\when('is_home')->justReturn(false);
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_single')->justReturn(false);
        Functions\when('is_page')->justReturn(false);
        Functions\when('is_singular')->justReturn(false);
        Functions\when('is_category')->justReturn(false);
        Functions\when('is_tag')->justReturn(false);
        Functions\when('is_tax')->justReturn(false);

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('user.id', '123')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('user.role', 'administrator,editor')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->zeroOrMoreTimes();

        $this->profiler->on_wp_ready();
    }

    public function testOnWpReadyAddsPostContext()
    {
        $mock_post = $this->createMockPost([
            'ID' => 456,
            'post_type' => 'post',
            'post_status' => 'publish'
        ]);

        Functions\when('is_singular')->justReturn(true);
        Functions\when('get_queried_object')->justReturn($mock_post);
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('is_front_page')->justReturn(false);
        Functions\when('is_home')->justReturn(false);
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_single')->justReturn(false);
        Functions\when('is_page')->justReturn(false);
        Functions\when('is_category')->justReturn(false);
        Functions\when('is_tag')->justReturn(false);
        Functions\when('is_tax')->justReturn(false);

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.post.id', '456')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.post.type', 'post')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.post.status', 'publish')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->zeroOrMoreTimes();

        $this->profiler->on_wp_ready();
    }

    public function testOnWpReadyAddsTaxonomyContext()
    {
        $mock_term = $this->createMockTerm([
            'term_id' => 789,
            'taxonomy' => 'category',
            'slug' => 'test-category'
        ]);

        Functions\when('is_category')->justReturn(true);
        Functions\when('get_queried_object')->justReturn($mock_term);
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('is_front_page')->justReturn(false);
        Functions\when('is_home')->justReturn(false);
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_single')->justReturn(false);
        Functions\when('is_page')->justReturn(false);
        Functions\when('is_singular')->justReturn(false);
        Functions\when('is_tag')->justReturn(false);
        Functions\when('is_tax')->justReturn(false);

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.term.id', '789')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.term.taxonomy', 'category')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.term.slug', 'test-category')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->zeroOrMoreTimes();

        $this->profiler->on_wp_ready();
    }

    public function testOnTemplateRedirectAddsTemplateInfo()
    {
        Functions\when('get_page_template_slug')->justReturn('page-template.php');
        Functions\when('wp_get_theme')->justReturn($this->createMockTheme());
        Functions\when('is_404')->justReturn(false);
        Functions\when('is_search')->justReturn(false);
        Functions\when('is_archive')->justReturn(false);
        Functions\when('is_attachment')->justReturn(false);
        Functions\when('is_feed')->justReturn(false);

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.template', 'page-template.php')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.theme.name', 'Test Theme')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.theme.version', '1.0')
            ->once();

        $this->profiler->on_template_redirect();
    }

    public function testOnTemplateRedirectAddsConditionalTags()
    {
        Functions\when('get_page_template_slug')->justReturn('');
        Functions\when('wp_get_theme')->justReturn($this->createMockTheme());
        Functions\when('is_404')->justReturn(true);
        Functions\when('is_search')->justReturn(true);
        Functions\when('is_archive')->justReturn(false);
        Functions\when('is_attachment')->justReturn(false);
        Functions\when('is_feed')->justReturn(false);

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.is_404', 'true')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.is_search', 'true')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->zeroOrMoreTimes();

        $this->profiler->on_template_redirect();
    }

    public function testOnWpHeadSetsAttribute()
    {
        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.wp_head', 'reached')
            ->once();

        $this->profiler->on_wp_head();
    }

    public function testOnWpFooterSetsAttribute()
    {
        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.wp_footer', 'reached')
            ->once();

        $this->profiler->on_wp_footer();
    }

    public function testProfileQueryDetectsSELECT()
    {
        $query = 'SELECT * FROM wp_posts WHERE post_status = "publish"';

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('database.query.select', 'true')
            ->once();

        $result = $this->profiler->profile_query($query);
        $this->assertEquals($query, $result);
    }

    public function testProfileQueryDetectsINSERT()
    {
        $query = 'INSERT INTO wp_posts (post_title, post_content) VALUES ("Test", "Content")';

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('database.query.insert', 'true')
            ->once();

        $result = $this->profiler->profile_query($query);
        $this->assertEquals($query, $result);
    }

    public function testProfileQueryDetectsUPDATE()
    {
        $query = 'UPDATE wp_posts SET post_status = "publish" WHERE ID = 1';

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('database.query.update', 'true')
            ->once();

        $result = $this->profiler->profile_query($query);
        $this->assertEquals($query, $result);
    }

    public function testProfileQueryDetectsDELETE()
    {
        $query = 'DELETE FROM wp_posts WHERE ID = 1';

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('database.query.delete', 'true')
            ->once();

        $result = $this->profiler->profile_query($query);
        $this->assertEquals($query, $result);
    }

    public function testAddDatabaseStatsWithBasicCount()
    {
        Functions\when('get_num_queries')->justReturn(42);

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('database.queries.total', '42')
            ->once();

        $this->profiler->add_database_stats();
    }

    public function testAddDatabaseStatsWithSlowQueries()
    {
        Functions\when('get_num_queries')->justReturn(10);

        // Define SAVEQUERIES constant
        if (!defined('SAVEQUERIES')) {
            define('SAVEQUERIES', true);
        }

        // Mock $wpdb->queries
        $GLOBALS['wpdb'] = (object) [
            'queries' => [
                ['SELECT * FROM wp_posts', 0.05, 'stack'],
                ['SELECT * FROM wp_users', 0.15, 'stack'], // Slow
                ['UPDATE wp_options', 0.02, 'stack'],
                ['INSERT INTO wp_posts', 0.12, 'stack'], // Slow
            ]
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
    }

    public function testProfileThemeSetup()
    {
        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.theme_setup', 'completed')
            ->once();

        $this->profiler->profile_theme_setup();
    }

    public function testProfilePluginsLoaded()
    {
        Functions\when('get_option')->alias(function($option, $default = []) {
            if ($option === 'active_plugins') {
                return [
                    'plugin1/plugin1.php',
                    'plugin2/plugin2.php',
                    'plugin3/plugin3.php'
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
    }

    public function testProfilePostSaveInsert()
    {
        $mock_post = $this->createMockPost([
            'ID' => 123,
            'post_type' => 'post'
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
    }

    public function testProfilePostSaveUpdate()
    {
        $mock_post = $this->createMockPost([
            'ID' => 456,
            'post_type' => 'page'
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
    }

    public function testProfileCommentInsert()
    {
        $comment = ['comment_ID' => 789];

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.comment.insert', '789')
            ->once();

        $this->profiler->profile_comment_insert(789, $comment);
    }

    public function testProfileUserLogin()
    {
        $mock_user = $this->createMockUser(['ID' => 123]);

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.user.login', '123')
            ->once();

        $this->profiler->profile_user_login('testuser', $mock_user);
    }

    public function testProfileUserLogout()
    {
        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.user.logout', 'true')
            ->once();

        $this->profiler->profile_user_logout();
    }

    public function testProfileCacheOperation()
    {
        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.cache.operation', 'set')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.cache.group', 'posts')
            ->once();

        $this->profiler->profile_cache_operation('data', 'key', 'posts');
    }

    public function testProfileCacheGet()
    {
        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.cache.operation', 'get')
            ->once();

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.cache.group', 'options')
            ->once();

        $this->profiler->profile_cache_get('key', 'options');
    }

    public function testInitRestProfiling()
    {
        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->with('wordpress.rest_api', 'initialized')
            ->once();

        $this->profiler->init_rest_profiling();

        // Verify method executed successfully
        $this->assertTrue(true);
    }

    public function testMethodsHandleNullPerfbaseGracefully()
    {
        // Create plugin mock that returns null
        $mock_plugin = Mockery::mock(PerfbasePlugin::class);
        $mock_plugin->shouldReceive('get_perfbase')
            ->andReturn(null);

        Functions\expect('add_action')->zeroOrMoreTimes();
        Functions\expect('add_filter')->zeroOrMoreTimes();

        $profiler = new PerfbaseProfiler($mock_plugin);

        // These should not throw exceptions
        $profiler->on_wp_ready();
        $profiler->on_template_redirect();
        $profiler->on_wp_head();
        $profiler->on_wp_footer();
        $profiler->add_database_stats();
        $profiler->profile_theme_setup();
        $profiler->profile_plugins_loaded();
        $profiler->profile_user_logout();

        $this->assertTrue(true);
    }

    public function testProfileQueryReturnsQueryUnchanged()
    {
        $query = 'SELECT * FROM wp_posts';

        $this->mock_perfbase
            ->shouldReceive('setAttribute')
            ->zeroOrMoreTimes();

        $result = $this->profiler->profile_query($query);

        $this->assertEquals($query, $result);
    }

    private function createMockTheme(): object
    {
        $theme = Mockery::mock('WP_Theme');
        $theme->shouldReceive('get')
            ->with('Name')
            ->andReturn('Test Theme');
        $theme->shouldReceive('get')
            ->with('Version')
            ->andReturn('1.0');
        return $theme;
    }
}
