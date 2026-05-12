<?php
/**
 * Test Bootstrap File
 *
 * Sets up the testing environment for Perfbase WordPress plugin tests
 */

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define WordPress constants that might not be available in testing
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

// Define plugin constants
define('PERFBASE_PLUGIN_FILE', dirname(__DIR__) . '/perfbase.php');
define('PERFBASE_PLUGIN_DIR', dirname(__DIR__) . '/');
define('PERFBASE_PLUGIN_URL', 'http://example.com/wp-content/plugins/perfbase/');
define('PERFBASE_PLUGIN_VERSION', '0.0.0-dev');
define('PERFBASE_MIN_PHP_VERSION', '7.4');
define('PERFBASE_MIN_WP_VERSION', '5.0');

// Initialize Brain Monkey for WordPress function mocking
Brain\Monkey\setUp();

// Set up WordPress globals
$GLOBALS['wp_version'] = '6.0';

// Initialize WP Mock
WP_Mock::setUp();

// Register global teardown
register_shutdown_function(function() {
    Brain\Monkey\tearDown();
    WP_Mock::tearDown();
});

// Set timezone to avoid warnings
date_default_timezone_set('UTC');
