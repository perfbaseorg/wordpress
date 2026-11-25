<?php
/**
 * Loads the plugin main file the way WordPress does.
 *
 * perfbase.php declares constants and functions that tests/bootstrap.php
 * also declares, so it cannot be loaded inside the PHPUnit process. This
 * script runs in its own PHP process with only the WordPress functions the
 * file touches before the plugin classes are needed, and with a copy of the
 * file in an empty directory so no Composer autoloader can be found.
 *
 * Usage: php load-plugin-file.php <path-to-perfbase.php> <init|activate>
 */

define('ABSPATH', '/tmp/wordpress/');
$GLOBALS['wp_version'] = '7.1';
$GLOBALS['perfbase_fixture_hooks'] = [];
$GLOBALS['perfbase_fixture_activation_hook'] = null;
$GLOBALS['perfbase_fixture_options'] = [];

function plugin_dir_path($file)
{
    return dirname($file) . '/';
}

function plugin_dir_url($file)
{
    return 'http://example.com/wp-content/plugins/perfbase/';
}

function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
{
    $GLOBALS['perfbase_fixture_hooks'][$hook][] = $callback;

    return true;
}

function register_activation_hook($file, $callback)
{
    $GLOBALS['perfbase_fixture_activation_hook'] = $callback;
}

function esc_html__($text, $domain = 'default')
{
    return $text;
}

function esc_html($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES);
}

function add_option($name, $value)
{
    $GLOBALS['perfbase_fixture_options'][$name] = $value;

    return true;
}

$fixture_dir = sys_get_temp_dir() . '/perfbase-plugin-file-' . uniqid('', true);
mkdir($fixture_dir);
$plugin_file = $fixture_dir . '/perfbase.php';
copy($argv[1], $plugin_file);

require $plugin_file;

unlink($plugin_file);
rmdir($fixture_dir);

if ($argv[2] === 'activate') {
    call_user_func($GLOBALS['perfbase_fixture_activation_hook']);
    echo 'OPTIONS:' . json_encode($GLOBALS['perfbase_fixture_options']) . "\n";
} else {
    perfbase_init();
    foreach ($GLOBALS['perfbase_fixture_hooks']['admin_notices'] ?? [] as $callback) {
        $callback();
    }
}

echo "\nLOADED\n";
