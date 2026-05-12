<?php
/**
 * Perfbase uninstall handler.
 *
 * Removes plugin options on uninstall. Multisite-aware: iterates every
 * sub-site so an API key stored on a sub-site is not left behind in the
 * database after the plugin is deleted.
 *
 * @package Perfbase\WordPress
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (function_exists('is_multisite') && is_multisite()) {
    $perfbase_site_ids = function_exists('get_sites') ? get_sites(['fields' => 'ids']) : [];
    foreach ((array) $perfbase_site_ids as $perfbase_blog_id) {
        switch_to_blog((int) $perfbase_blog_id);
        delete_option('perfbase_settings');
        restore_current_blog();
    }
    if (function_exists('delete_site_option')) {
        delete_site_option('perfbase_settings');
    }
} else {
    delete_option('perfbase_settings');
}
