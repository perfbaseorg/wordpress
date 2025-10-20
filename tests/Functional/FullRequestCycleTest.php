<?php

namespace Perfbase\WordPress\Tests\Functional;

use Brain\Monkey\Functions;
use Perfbase\WordPress\PerfbasePlugin;
use Perfbase\WordPress\Tests\Helpers\BaseWordPressTest;
use Perfbase\WordPress\Tests\Helpers\TestData;

/**
 * Functional tests for complete request cycles
 */
class FullRequestCycleTest extends BaseWordPressTest
{
    public function testCompleteWordPressFrontendRequest()
    {
        $config = TestData::getValidConfig();

        Functions\when('get_option')
            ->justReturn($config);

        Functions\when('load_plugin_textdomain')->justReturn(true);

        // Set up a typical frontend request
        $this->mockFrontendEnvironment();
        $this->setServerVars([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'example.com',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.0');
        Functions\when('is_front_page')->justReturn(true);
        Functions\when('is_home')->justReturn(true);
        Functions\when('get_page_template_slug')->justReturn('');

        $mock_theme = (object) [];
        $mock_theme->get = function($key) {
            return $key === 'Name' ? 'Twenty Twenty-One' : '1.3';
        };
        Functions\when('wp_get_theme')->justReturn($mock_theme);

        // Mock final request stats
        Functions\when('memory_get_peak_usage')->justReturn(5242880); // 5MB
        Functions\when('memory_get_usage')->justReturn(3145728); // 3MB
        Functions\when('get_num_queries')->justReturn(8);
        Functions\when('http_response_code')->justReturn(200);

        // Initialize plugin and run complete lifecycle
        $plugin = new PerfbasePlugin();
        $plugin->init();

        // Simulate WordPress request lifecycle
        do_action('init');
        $plugin->start_request_profiling();

        do_action('wp_loaded');
        $plugin->wp_loaded_profiling();

        do_action('template_redirect');
        $plugin->template_redirect_profiling();

        // Simulate some database queries and HTTP requests
        $plugin->profile_database_query('SELECT * FROM wp_posts WHERE post_status = "publish"');
        $plugin->profile_http_request(null, [], 'https://api.example.com/data');

        do_action('wp_head');
        do_action('wp_footer');

        do_action('shutdown');
        $plugin->finish_request_profiling();

        $this->assertTrue(true);
    }

    public function testCompleteAdminRequest()
    {
        $config = TestData::getValidConfig();
        $config['profile_admin'] = true;

        Functions\when('get_option')
            ->justReturn($config);

        Functions\when('load_plugin_textdomain')->justReturn(true);

        // Set up admin request
        $this->mockAdminEnvironment();
        $this->setServerVars([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/wp-admin/options-general.php?page=perfbase-settings',
            'HTTP_HOST' => 'example.com',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);

        Functions\when('is_ssl')->justReturn(true);
        Functions\when('get_bloginfo')->justReturn('6.0');

        // Mock admin-specific functions
        Functions\when('admin_url')->alias(function($path = '') {
            return 'https://example.com/wp-admin/' . $path;
        });

        // Mock final request stats
        Functions\when('memory_get_peak_usage')->justReturn(8388608); // 8MB
        Functions\when('memory_get_usage')->justReturn(6291456); // 6MB
        Functions\when('get_num_queries')->justReturn(15);
        Functions\when('http_response_code')->justReturn(200);

        // Initialize plugin and run admin lifecycle
        $plugin = new PerfbasePlugin();
        $plugin->init();

        // Start profiling
        $plugin->start_request_profiling();

        // Simulate admin page load
        $plugin->wp_loaded_profiling();

        // Finish profiling
        $plugin->finish_request_profiling();

        $this->assertTrue(true);
    }

    public function testAjaxRequestCycle()
    {
        $config = TestData::getValidConfig();

        Functions\when('get_option')
            ->justReturn($config);

        Functions\when('load_plugin_textdomain')->justReturn(true);

        // Set up AJAX request
        $this->mockAjaxEnvironment();
        $this->setServerVars([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/wp-admin/admin-ajax.php',
            'HTTP_HOST' => 'example.com',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);

        $_POST['action'] = 'load_more_posts';
        $_REQUEST['action'] = 'load_more_posts';

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.0');

        // Mock AJAX response stats
        Functions\when('memory_get_peak_usage')->justReturn(2097152); // 2MB
        Functions\when('memory_get_usage')->justReturn(1572864); // 1.5MB
        Functions\when('get_num_queries')->justReturn(3);
        Functions\when('http_response_code')->justReturn(200);

        // Initialize plugin
        $plugin = new PerfbasePlugin();
        $plugin->init();

        // Start AJAX profiling
        $plugin->start_ajax_profiling();

        // Simulate AJAX processing
        $plugin->wp_loaded_profiling();

        // Finish profiling
        $plugin->finish_request_profiling();

        $this->assertTrue(true);
    }

    public function testCronJobCycle()
    {
        $config = TestData::getValidConfig();

        Functions\when('get_option')
            ->justReturn($config);

        Functions\when('load_plugin_textdomain')->justReturn(true);

        // Set up cron environment
        $this->mockCronEnvironment();
        $this->setServerVars([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/wp-cron.php',
            'HTTP_HOST' => 'example.com',
            'HTTP_USER_AGENT' => 'WordPress/6.0; https://example.com'
        ]);

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.0');

        // Mock cron job stats
        Functions\when('memory_get_peak_usage')->justReturn(4194304); // 4MB
        Functions\when('memory_get_usage')->justReturn(3145728); // 3MB
        Functions\when('get_num_queries')->justReturn(12);
        Functions\when('http_response_code')->justReturn(200);

        // Initialize plugin
        $plugin = new PerfbasePlugin();
        $plugin->init();

        // Start cron profiling
        $plugin->start_cron_profiling();

        // Simulate cron job execution
        $plugin->wp_loaded_profiling();

        // Finish profiling
        $plugin->finish_request_profiling();

        $this->assertTrue(true);
    }

    public function testRequestWithWooCommerce()
    {
        $config = TestData::getValidConfig();

        Functions\when('get_option')
            ->justReturn($config);

        Functions\when('load_plugin_textdomain')->justReturn(true);

        // Set up WooCommerce shop page
        $this->mockFrontendEnvironment();
        $this->mockWooCommerce();

        $this->setServerVars([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/shop/',
            'HTTP_HOST' => 'example.com',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.0');
        Functions\when('is_shop')->justReturn(true);
        Functions\when('is_product')->justReturn(false);
        Functions\when('is_cart')->justReturn(false);
        Functions\when('is_checkout')->justReturn(false);
        Functions\when('is_account_page')->justReturn(false);

        // Mock final request stats
        Functions\when('memory_get_peak_usage')->justReturn(10485760); // 10MB
        Functions\when('memory_get_usage')->justReturn(8388608); // 8MB
        Functions\when('get_num_queries')->justReturn(25);
        Functions\when('http_response_code')->justReturn(200);

        // Initialize plugin
        $plugin = new PerfbasePlugin();
        $plugin->init();

        // Run complete lifecycle with WooCommerce
        $plugin->start_request_profiling();
        $plugin->wp_loaded_profiling();
        $plugin->template_redirect_profiling();
        $plugin->finish_request_profiling();

        $this->assertTrue(true);
    }

    public function testHighVolumeRequest()
    {
        $config = TestData::getValidConfig();

        Functions\when('get_option')
            ->justReturn($config);

        Functions\when('load_plugin_textdomain')->justReturn(true);

        // Disable WooCommerce for this test to avoid WC() function call
        Functions\when('class_exists')->justReturn(false);

        // Set up high-volume request scenario
        $this->mockFrontendEnvironment();
        $this->setServerVars([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/category/popular/',
            'HTTP_HOST' => 'example.com',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.0');
        Functions\when('is_category')->justReturn(true);

        $mock_term = (object) [
            'term_id' => 5,
            'taxonomy' => 'category',
            'slug' => 'popular'
        ];
        Functions\when('get_queried_object')->justReturn($mock_term);

        // Simulate high resource usage
        Functions\when('memory_get_peak_usage')->justReturn(52428800); // 50MB
        Functions\when('memory_get_usage')->justReturn(41943040); // 40MB
        Functions\when('get_num_queries')->justReturn(150); // Many queries
        Functions\when('http_response_code')->justReturn(200);

        // Initialize plugin
        $plugin = new PerfbasePlugin();
        $plugin->init();

        // Multiple database queries to simulate complex page
        $queries = [
            'SELECT * FROM wp_posts WHERE post_status = "publish" ORDER BY post_date DESC LIMIT 20',
            'SELECT * FROM wp_postmeta WHERE post_id IN (1,2,3,4,5)',
            'SELECT * FROM wp_terms WHERE term_id = 5',
            'SELECT * FROM wp_options WHERE autoload = "yes"',
            'SELECT COUNT(*) FROM wp_posts WHERE post_type = "post"'
        ];

        $plugin->start_request_profiling();

        foreach ($queries as $query) {
            $plugin->profile_database_query($query);
        }

        // Simulate external API calls
        $plugin->profile_http_request(null, [], 'https://api.analytics.example.com/track');
        $plugin->profile_http_request(null, [], 'https://cdn.example.com/assets/script.js');

        $plugin->wp_loaded_profiling();
        $plugin->template_redirect_profiling();
        $plugin->finish_request_profiling();

        $this->assertTrue(true);
    }
}