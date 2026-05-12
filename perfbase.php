<?php
/**
 * Plugin Name: Perfbase
 * Plugin URI: https://perfbase.com
 * Description: WordPress integration for the Perfbase APM platform. Provides comprehensive performance monitoring and profiling for WordPress applications.
 * Version: 0.0.0-dev
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Perfbase Team
 * Author URI: https://perfbase.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: perfbase
 *
 * @package Perfbase\WordPress
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PERFBASE_PLUGIN_FILE', __FILE__);
define('PERFBASE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PERFBASE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PERFBASE_PLUGIN_VERSION', '0.0.0-dev');
define('PERFBASE_MIN_PHP_VERSION', '7.4');
define('PERFBASE_MIN_WP_VERSION', '5.0');

// Check PHP version compatibility
if (version_compare(PHP_VERSION, PERFBASE_MIN_PHP_VERSION, '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        printf(
            /* translators: 1: required PHP version, 2: current PHP version. */
            esc_html__('Perfbase requires PHP version %1$s or higher. You are running PHP %2$s.', 'perfbase'),
            esc_html(PERFBASE_MIN_PHP_VERSION),
            esc_html(PHP_VERSION)
        );
        echo '</p></div>';
    });
    return;
}

// Check WordPress version compatibility
if (version_compare($GLOBALS['wp_version'], PERFBASE_MIN_WP_VERSION, '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        printf(
            /* translators: 1: required WordPress version, 2: current WordPress version. */
            esc_html__('Perfbase requires WordPress version %1$s or higher. You are running WordPress %2$s.', 'perfbase'),
            esc_html(PERFBASE_MIN_WP_VERSION),
            esc_html($GLOBALS['wp_version'])
        );
        echo '</p></div>';
    });
    return;
}

// Load Composer autoloader if available
$perfbase_autoloader = PERFBASE_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($perfbase_autoloader)) {
    require_once $perfbase_autoloader;
}

/**
 * Initialize the plugin
 *
 * @return void
 */
function perfbase_init() {
    try {
        $plugin = new Perfbase\WordPress\PerfbasePlugin();
        $plugin->init();
    } catch (Exception $e) {
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error"><p>';
            printf(
                /* translators: %s: initialization error message. */
                esc_html__('Perfbase initialization failed: %s', 'perfbase'),
                esc_html($e->getMessage())
            );
            echo '</p></div>';
        });
    }
}

// Initialize the plugin
add_action('plugins_loaded', 'perfbase_init');

/**
 * Plugin activation hook
 */
function perfbase_activate() {
    // Check system requirements during activation
    if (version_compare(PHP_VERSION, PERFBASE_MIN_PHP_VERSION, '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(sprintf(
            /* translators: 1: required PHP version, 2: current PHP version. */
            esc_html__('Perfbase requires PHP version %1$s or higher. You are running PHP %2$s.', 'perfbase'),
            esc_html(PERFBASE_MIN_PHP_VERSION),
            esc_html(PHP_VERSION)
        ));
    }

    if (version_compare($GLOBALS['wp_version'], PERFBASE_MIN_WP_VERSION, '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(sprintf(
            /* translators: 1: required WordPress version, 2: current WordPress version. */
            esc_html__('Perfbase requires WordPress version %1$s or higher. You are running WordPress %2$s.', 'perfbase'),
            esc_html(PERFBASE_MIN_WP_VERSION),
            esc_html($GLOBALS['wp_version'])
        ));
    }

    // Create default options
    $defaults = (new Perfbase\WordPress\Helpers\ConfigManager())->getDefaultConfig();
    add_option('perfbase_settings', $defaults);
}
register_activation_hook(__FILE__, 'perfbase_activate');

// Uninstall handling lives in uninstall.php (loaded by WordPress when the
// plugin is deleted). Using uninstall.php instead of register_uninstall_hook
// keeps the callback discoverable and lets us be multisite-aware.
