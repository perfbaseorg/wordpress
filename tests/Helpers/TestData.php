<?php

namespace Perfbase\WordPress\Tests\Helpers;

use Perfbase\SDK\FeatureFlags;

/**
 * Test data provider for consistent test data across test suites
 */
class TestData
{
    /**
     * Get valid plugin configuration data
     *
     * @return array
     */
    public static function getValidConfig(): array
    {
        return [
            'enabled' => true,
            'api_key' => 'test-api-key-12345678901234567890',
            'api_url' => 'https://ingress.perfbase.cloud',
            'sample_rate' => 0.1,
            'flags' => FeatureFlags::DefaultFlags,
            'timeout' => 10,
            'proxy' => '',
            'profile_http_status_codes' => array_merge(range(200, 299), range(500, 599)),
            'profile_admin' => false,
            'profile_ajax' => true,
            'profile_cron' => true,
            'profile_cli' => false,
            'include' => [
                'http' => ['*'],
                'ajax' => ['*'],
                'cron' => ['*'],
                'cli' => ['*'],
            ],
            'exclude' => [
                'http' => [
                    '/wp-content/uploads/*',
                    '/favicon.ico',
                ],
                'ajax' => [],
                'cron' => [],
                'cli' => [],
            ],
            'exclude_user_agents' => [
                'bot',
                'crawler',
                'spider'
            ],
            'debug' => false,
            'log_errors' => true
        ];
    }

    /**
     * Get invalid plugin configuration data
     *
     * @return array
     */
    public static function getInvalidConfigs(): array
    {
        return [
            'missing_api_key' => [
                'enabled' => true,
                'api_key' => '',
                'sample_rate' => 0.1
            ],
            'invalid_sample_rate_negative' => [
                'enabled' => true,
                'api_key' => 'test-key',
                'sample_rate' => -0.1
            ],
            'invalid_sample_rate_too_high' => [
                'enabled' => true,
                'api_key' => 'test-key',
                'sample_rate' => 1.5
            ],
            'invalid_timeout' => [
                'enabled' => true,
                'api_key' => 'test-key',
                'timeout' => 0
            ],
            'invalid_flags' => [
                'enabled' => true,
                'api_key' => 'test-key',
                'flags' => -1
            ]
        ];
    }

    /**
     * Get test WordPress request contexts
     *
     * @return array
     */
    public static function getWordPressContexts(): array
    {
        return [
            'frontend_home' => [
                'is_admin' => false,
                'is_front_page' => true,
                'is_home' => true,
                'context_type' => 'frontend'
            ],
            'frontend_single_post' => [
                'is_admin' => false,
                'is_single' => true,
                'post_id' => 123,
                'post_type' => 'post',
                'context_type' => 'frontend'
            ],
            'admin_dashboard' => [
                'is_admin' => true,
                'pagenow' => 'index.php',
                'context_type' => 'admin'
            ],
            'admin_perfbase_settings' => [
                'is_admin' => true,
                'pagenow' => 'options-general.php',
                'page' => 'perfbase-settings',
                'context_type' => 'admin'
            ],
            'ajax_request' => [
                'is_admin' => true,
                'doing_ajax' => true,
                'action' => 'test_action',
                'context_type' => 'ajax'
            ],
            'cron_job' => [
                'is_admin' => false,
                'doing_cron' => true,
                'context_type' => 'cron'
            ],
            'cli_command' => [
                'is_admin' => false,
                'wp_cli' => true,
                'context_type' => 'cli'
            ]
        ];
    }

    /**
     * Get test user agents and their expected exclusion status
     *
     * @return array
     */
    public static function getUserAgentTestCases(): array
    {
        return [
            'normal_browser' => [
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'should_exclude' => false
            ],
            'google_bot' => [
                'user_agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
                'should_exclude' => true
            ],
            'bing_crawler' => [
                'user_agent' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
                'should_exclude' => true
            ],
            'facebook_crawler' => [
                'user_agent' => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
                'should_exclude' => false // not in default exclusions
            ],
            'site_spider' => [
                'user_agent' => 'Mozilla/5.0 (compatible; SiteSpider/1.0)',
                'should_exclude' => true
            ]
        ];
    }

    /**
     * Get test request paths and their expected exclusion status
     *
     * @return array
     */
    public static function getRequestPathTestCases(): array
    {
        return [
            'home_page' => [
                'path' => '/',
                'should_exclude' => false
            ],
            'regular_post' => [
                'path' => '/sample-post/',
                'should_exclude' => false
            ],
            'ajax_endpoint' => [
                'path' => '/wp-admin/admin-ajax.php',
                'should_exclude' => true
            ],
            'uploaded_file' => [
                'path' => '/wp-content/uploads/2023/01/image.jpg',
                'should_exclude' => true
            ],
            'favicon' => [
                'path' => '/favicon.ico',
                'should_exclude' => true
            ],
            'api_endpoint' => [
                'path' => '/wp-json/wp/v2/posts',
                'should_exclude' => false
            ]
        ];
    }

    /**
     * Get test database queries with their expected types
     *
     * @return array
     */
    public static function getDatabaseQueryTestCases(): array
    {
        return [
            'select_posts' => [
                'query' => 'SELECT * FROM wp_posts WHERE post_status = "publish"',
                'expected_type' => 'select'
            ],
            'insert_post' => [
                'query' => 'INSERT INTO wp_posts (post_title, post_content) VALUES ("Test", "Content")',
                'expected_type' => 'insert'
            ],
            'update_post' => [
                'query' => 'UPDATE wp_posts SET post_title = "Updated" WHERE ID = 1',
                'expected_type' => 'update'
            ],
            'delete_post' => [
                'query' => 'DELETE FROM wp_posts WHERE ID = 1',
                'expected_type' => 'delete'
            ],
            'replace_option' => [
                'query' => 'REPLACE INTO wp_options (option_name, option_value) VALUES ("test", "value")',
                'expected_type' => 'replace'
            ],
            'create_table' => [
                'query' => 'CREATE TABLE wp_test (id INT PRIMARY KEY)',
                'expected_type' => 'create'
            ],
            'alter_table' => [
                'query' => 'ALTER TABLE wp_test ADD COLUMN name VARCHAR(255)',
                'expected_type' => 'alter'
            ],
            'drop_table' => [
                'query' => 'DROP TABLE wp_test',
                'expected_type' => 'drop'
            ],
            'complex_select' => [
                'query' => '  SELECT p.*, m.meta_value FROM wp_posts p JOIN wp_postmeta m ON p.ID = m.post_id',
                'expected_type' => 'select'
            ]
        ];
    }

    /**
     * Get test WordPress hooks that should be registered
     *
     * @return array
     */
    public static function getExpectedWordPressHooks(): array
    {
        return [
            'actions' => [
                'init' => ['priority' => -999, 'callback' => 'start_request_profiling'],
                'wp_loaded' => ['priority' => 10, 'callback' => 'wp_loaded_profiling'],
                'shutdown' => ['priority' => 999, 'callback' => 'finish_request_profiling'],
                'template_redirect' => ['priority' => 10, 'callback' => 'template_redirect_profiling'],
                'wp_die_handler' => ['priority' => 10, 'callback' => 'handle_wp_die']
            ],
            'filters' => [
                'query' => ['priority' => 10, 'callback' => 'profile_database_query'],
                'pre_http_request' => ['priority' => 10, 'callback' => 'profile_http_request']
            ]
        ];
    }

    /**
     * Get test WooCommerce scenarios
     *
     * @return array
     */
    public static function getWooCommerceTestCases(): array
    {
        return [
            'shop_page' => [
                'is_shop' => true,
                'expected_attributes' => ['woocommerce.page' => 'shop']
            ],
            'single_product' => [
                'is_product' => true,
                'product_id' => 123,
                'product_type' => 'simple',
                'expected_attributes' => [
                    'woocommerce.page' => 'product',
                    'woocommerce.product.id' => '123',
                    'woocommerce.product.type' => 'simple'
                ]
            ],
            'cart_page' => [
                'is_cart' => true,
                'expected_attributes' => ['woocommerce.page' => 'cart']
            ],
            'checkout_page' => [
                'is_checkout' => true,
                'expected_attributes' => ['woocommerce.page' => 'checkout']
            ],
            'account_page' => [
                'is_account_page' => true,
                'expected_attributes' => ['woocommerce.page' => 'account']
            ]
        ];
    }

    /**
     * Get test admin settings form data
     *
     * @return array
     */
    public static function getAdminFormTestData(): array
    {
        return [
            'valid_settings' => [
                'enabled' => '1',
                'api_key' => 'test-api-key-12345678901234567890',
                'api_url' => 'https://ingress.perfbase.cloud',
                'sample_rate' => '0.1',
                'timeout' => '10',
                'proxy' => '',
                'profile_admin' => '1',
                'profile_ajax' => '1',
                'profile_cron' => '1',
                'flags' => [
                    (string) FeatureFlags::UseCoarseClock,
                    (string) FeatureFlags::TrackCpuTime,
                    (string) FeatureFlags::TrackPdo
                ],
                'excluded_paths' => "/wp-admin/admin-ajax.php\n/wp-content/uploads/\n/favicon.ico",
                'excluded_user_agents' => "bot\ncrawler\nspider"
            ],
            'minimal_settings' => [
                'enabled' => '1',
                'api_key' => 'test-key',
                'sample_rate' => '1.0'
            ],
            'invalid_settings' => [
                'enabled' => '1',
                'api_key' => '', // Invalid: empty API key
                'sample_rate' => '2.0', // Invalid: > 1.0
                'timeout' => '0' // Invalid: <= 0
            ]
        ];
    }

    /**
     * Get test sampling scenarios
     *
     * @return array
     */
    public static function getSamplingTestCases(): array
    {
        return [
            'always_sample' => ['rate' => 1.0, 'expected_samples' => 100, 'tolerance' => 0],
            'never_sample' => ['rate' => 0.0, 'expected_samples' => 0, 'tolerance' => 0],
            'fifty_percent' => ['rate' => 0.5, 'expected_samples' => 50, 'tolerance' => 10],
            'ten_percent' => ['rate' => 0.1, 'expected_samples' => 10, 'tolerance' => 5],
            'one_percent' => ['rate' => 0.01, 'expected_samples' => 1, 'tolerance' => 2]
        ];
    }

    /**
     * Get test feature flag combinations
     *
     * @return array
     */
    public static function getFeatureFlagTestCases(): array
    {
        return [
            'no_flags' => [
                'flags' => 0,
                'expected' => []
            ],
            'default_flags' => [
                'flags' => FeatureFlags::DefaultFlags,
                'expected' => [
                    'UseCoarseClock',
                    'TrackCpuTime',
                    'TrackPdo',
                    'TrackHttp',
                    'TrackCaches',
                    'TrackMongodb',
                    'TrackElasticsearch',
                    'TrackQueues',
                    'TrackAwsSdk'
                ]
            ],
            'all_flags' => [
                'flags' => FeatureFlags::AllFlags,
                'expected' => [
                    'UseCoarseClock',
                    'TrackExceptions',
                    'TrackFileCompilation',
                    'TrackMemoryAllocation',
                    'TrackCpuTime',
                    'TrackFileDefinitions',
                    'TrackPdo',
                    'TrackHttp',
                    'TrackCaches',
                    'TrackMongodb',
                    'TrackElasticsearch',
                    'TrackQueues',
                    'TrackAwsSdk',
                    'TrackFileOperations'
                ]
            ],
            'single_flag' => [
                'flags' => FeatureFlags::TrackPdo,
                'expected' => ['TrackPdo']
            ]
        ];
    }
}
