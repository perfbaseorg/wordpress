<?php

namespace Perfbase\WordPress\Tests\Helpers;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use WP_Mock;

/**
 * Base test class with WordPress-specific testing utilities
 */
abstract class BaseWordPressTest extends TestCase
{
    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Initialize Brain Monkey and WP Mock for each test
        \Brain\Monkey\setUp();
        WP_Mock::setUp();

        // Set up common WordPress function mocks
        $this->setUpWordPressMocks();
    }

    /**
     * Tear down test environment
     */
    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        WP_Mock::tearDown();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Set up common WordPress function mocks
     */
    protected function setUpWordPressMocks(): void
    {
        // Common WordPress function mocks
        Functions\when('__')->returnArg();
        Functions\when('esc_html__')->returnArg();
        Functions\when('esc_html_e')->alias(function($text) { echo $text; });
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_attr')->returnArg();
        Functions\when('esc_url_raw')->returnArg();
        Functions\when('esc_textarea')->returnArg();
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_unslash')->returnArg();
        Functions\when('wp_strip_all_tags')->alias(function($value) {
            return strip_tags((string) $value);
        });
        Functions\when('wp_parse_url')->alias(function($url, $component = -1) {
            return parse_url($url, $component);
        });
        Functions\when('wp_rand')->alias(function($min = null, $max = null) {
            if ($min === null && $max === null) {
                return mt_rand();
            }

            return mt_rand((int) $min, (int) $max);
        });
        Functions\when('sanitize_key')->alias(function($value) {
            $value = strtolower((string) $value);
            $value = preg_replace('/[^a-z0-9_\-]/', '_', $value);
            $value = preg_replace('/_+/', '_', (string) $value);
            return trim((string) $value, '_');
        });
        Functions\when('error_log')->justReturn(true);
        Functions\when('wp_parse_args')->alias(function($args, $defaults) {
            return array_merge($defaults, $args);
        });

        // WordPress core functions
        Functions\when('plugin_basename')->returnArg();
        Functions\when('plugin_dir_path')->returnArg();
        Functions\when('plugin_dir_url')->returnArg();
        Functions\when('get_bloginfo')->justReturn('Test Site');
        Functions\when('wp_get_environment_type')->justReturn('production');
        Functions\when('admin_url')->returnArg();
        Functions\when('wp_cache_delete')->justReturn(true);
        Functions\when('flush_rewrite_rules')->justReturn();
        Functions\when('load_plugin_textdomain')->justReturn(true);

        // WordPress option functions
        Functions\when('get_option')->alias(function($option, $default = false) {
            return $default;
        });
        Functions\when('add_option')->justReturn(true);
        Functions\when('update_option')->justReturn(true);
        Functions\when('delete_option')->justReturn(true);

        // WordPress hook functions
        Functions\when('add_action')->justReturn();
        Functions\when('add_filter')->justReturn();
        Functions\when('remove_action')->justReturn();
        Functions\when('remove_filter')->justReturn();
        Functions\when('do_action')->justReturn();
        Functions\when('apply_filters')->returnArg();

        // WordPress conditional functions
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('is_ssl')->justReturn(false);
        Functions\when('is_front_page')->justReturn(false);
        Functions\when('is_home')->justReturn(false);
        Functions\when('is_single')->justReturn(false);
        Functions\when('is_page')->justReturn(false);
        Functions\when('is_404')->justReturn(false);
        Functions\when('is_search')->justReturn(false);
        Functions\when('is_archive')->justReturn(false);
        Functions\when('is_attachment')->justReturn(false);
        Functions\when('is_feed')->justReturn(false);
        Functions\when('is_category')->justReturn(false);
        Functions\when('is_tag')->justReturn(false);
        Functions\when('is_tax')->justReturn(false);
        Functions\when('is_singular')->justReturn(false);

        // WordPress user functions
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_get_current_user')->justReturn((object) [
            'ID' => 1,
            'roles' => ['administrator']
        ]);

        // WordPress query functions
        Functions\when('get_queried_object')->justReturn(null);
        Functions\when('get_query_var')->justReturn('');
        Functions\when('get_page_template_slug')->justReturn('');
        Functions\when('get_template')->justReturn('twentytwentyone');
        Functions\when('get_stylesheet')->justReturn('twentytwentyone');

        // WordPress theme functions - create proper mock with Mockery
        $theme_mock = Mockery::mock('WP_Theme');
        $theme_mock->shouldReceive('get')
            ->with('Name')
            ->andReturn('Test Theme');
        $theme_mock->shouldReceive('get')
            ->with('Version')
            ->andReturn('1.0');
        Functions\when('wp_get_theme')->justReturn($theme_mock);

        // WordPress database functions
        Functions\when('get_num_queries')->justReturn(0);

        // WordPress error functions
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_die')->justReturn();

        // WordPress HTTP functions
        Functions\when('wp_remote_request')->justReturn([]);
        Functions\when('wp_remote_get')->justReturn([]);
        Functions\when('wp_remote_post')->justReturn([]);

        // WordPress cache functions
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\when('wp_cache_add')->justReturn(true);
    }

    /**
     * Mock WordPress admin environment
     */
    protected function mockAdminEnvironment(): void
    {
        Functions\when('is_admin')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);

        // Admin-specific functions
        Functions\when('admin_url')->alias(function($path = '') {
            return 'http://example.com/wp-admin/' . $path;
        });
        Functions\when('get_admin_page_title')->justReturn('Perfbase Settings');
        Functions\when('settings_fields')->justReturn();
        Functions\when('do_settings_sections')->justReturn();
        Functions\when('submit_button')->justReturn();
        Functions\when('register_setting')->justReturn();
        Functions\when('add_settings_section')->justReturn();
        Functions\when('add_settings_field')->justReturn();
        Functions\when('checked')->alias(function($checked, $current = true, $echo = true) {
            $result = checked($checked, $current, false);
            return $echo ? print($result) : $result;
        });
    }

    /**
     * Mock WordPress frontend environment
     */
    protected function mockFrontendEnvironment(): void
    {
        Functions\when('is_admin')->justReturn(false);

        // Set up typical frontend globals
        $GLOBALS['wp_query'] = (object) [
            'is_front_page' => false,
            'is_home' => false,
            'is_single' => false,
            'is_page' => false
        ];
    }

    /**
     * Mock AJAX environment
     */
    protected function mockAjaxEnvironment(): void
    {
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }

        $_REQUEST['action'] = 'test_action';
        Functions\when('is_admin')->justReturn(true);
    }

    /**
     * Mock cron environment
     */
    protected function mockCronEnvironment(): void
    {
        if (!defined('DOING_CRON')) {
            define('DOING_CRON', true);
        }

        Functions\when('is_admin')->justReturn(false);
    }

    /**
     * Mock CLI environment
     */
    protected function mockCliEnvironment(): void
    {
        if (!defined('WP_CLI')) {
            define('WP_CLI', true);
        }

        Functions\when('is_admin')->justReturn(false);
    }

    /**
     * Get the value of a private or protected property
     *
     * @param object $object
     * @param string $propertyName
     * @return mixed
     * @throws ReflectionException
     */
    protected function getPrivateProperty(object $object, string $propertyName)
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * Set the value of a private or protected property
     *
     * @param object $object
     * @param string $propertyName
     * @param mixed $value
     * @throws ReflectionException
     */
    protected function setPrivateProperty(object $object, string $propertyName, $value): void
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * Invoke a private or protected method
     *
     * @param object $object
     * @param string $methodName
     * @param array $parameters
     * @return mixed
     * @throws ReflectionException
     */
    protected function invokePrivateMethod(object $object, string $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Assert that a WordPress hook was added
     *
     * @param string $hook
     * @param callable|array|string $callback
     * @param int $priority
     * @param int $acceptedArgs
     */
    protected function assertHookAdded(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        WP_Mock::expectActionAdded($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * Assert that a WordPress filter was added
     *
     * @param string $hook
     * @param callable|array|string $callback
     * @param int $priority
     * @param int $acceptedArgs
     */
    protected function assertFilterAdded(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        WP_Mock::expectFilterAdded($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * Create a mock WordPress post object
     *
     * @param array $data
     * @return object
     */
    protected function createMockPost(array $data = []): object
    {
        $defaults = [
            'ID' => 1,
            'post_title' => 'Test Post',
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_author' => 1
        ];

        return (object) array_merge($defaults, $data);
    }

    /**
     * Create a mock WordPress user object
     *
     * @param array $data
     * @return object
     */
    protected function createMockUser(array $data = []): object
    {
        $defaults = [
            'ID' => 1,
            'user_login' => 'testuser',
            'user_email' => 'test@example.com',
            'roles' => ['subscriber']
        ];

        return (object) array_merge($defaults, $data);
    }

    /**
     * Create a mock WordPress term object
     *
     * @param array $data
     * @return object
     */
    protected function createMockTerm(array $data = []): object
    {
        $defaults = [
            'term_id' => 1,
            'name' => 'Test Term',
            'slug' => 'test-term',
            'taxonomy' => 'category'
        ];

        return (object) array_merge($defaults, $data);
    }

    /**
     * Mock WordPress database global
     */
    protected function mockWpdb(): object
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->queries = [];
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($query) {
            return $query;
        });

        $GLOBALS['wpdb'] = $wpdb;
        return $wpdb;
    }

    /**
     * Set up $_SERVER variables for testing
     *
     * @param array $server
     */
    protected function setServerVars(array $server): void
    {
        $_SERVER = array_merge([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'example.com',
            'HTTP_USER_AGENT' => 'Test User Agent',
            'SERVER_NAME' => 'example.com',
            'HTTPS' => ''
        ], $server);
    }

    /**
     * Mock the presence of WooCommerce
     */
    protected function mockWooCommerce(): void
    {
        if (!class_exists('WooCommerce')) {
            $mockWC = Mockery::mock('WooCommerce');
            $mockWC->version = '6.0.0';

            // Create a mock class for WooCommerce
            Functions\when('WC')->justReturn($mockWC);

            // Mock WooCommerce functions
            Functions\when('is_shop')->justReturn(false);
            Functions\when('is_product')->justReturn(false);
            Functions\when('is_cart')->justReturn(false);
            Functions\when('is_checkout')->justReturn(false);
            Functions\when('is_account_page')->justReturn(false);
            Functions\when('wc_get_product')->justReturn(null);
        }
    }
}
