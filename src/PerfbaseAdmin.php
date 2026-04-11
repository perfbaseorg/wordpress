<?php
/**
 * Perfbase Admin Interface
 *
 * @package Perfbase\WordPress
 */

namespace Perfbase\WordPress;

use Perfbase\SDK\FeatureFlags;

/**
 * Admin interface for Perfbase plugin
 */
class PerfbaseAdmin {

    /**
     * Plugin instance
     *
     * @var PerfbasePlugin
     */
    private $plugin;

    /**
     * Constructor
     *
     * @param PerfbasePlugin $plugin
     */
    public function __construct(PerfbasePlugin $plugin) {
        $this->plugin = $plugin;
        $this->init();
    }

    /**
     * Initialize admin interface
     *
     * @return void
     */
    private function init() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_filter('plugin_action_links_' . plugin_basename(PERFBASE_PLUGIN_FILE), [$this, 'add_plugin_action_links']);
    }

    /**
     * Add admin menu
     *
     * @return void
     */
    public function add_admin_menu() {
        add_options_page(
            __('Perfbase Settings', 'perfbase'),
            __('Perfbase', 'perfbase'),
            'manage_options',
            'perfbase-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings
     *
     * @return void
     */
    public function register_settings() {
        register_setting('perfbase_settings', 'perfbase_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);

        // General settings section
        add_settings_section(
            'perfbase_general',
            __('General Settings', 'perfbase'),
            [$this, 'render_general_section'],
            'perfbase-settings'
        );

        // API Configuration
        add_settings_field(
            'api_key',
            __('API Key', 'perfbase'),
            [$this, 'render_api_key_field'],
            'perfbase-settings',
            'perfbase_general'
        );

        add_settings_field(
            'enabled',
            __('Enable Profiling', 'perfbase'),
            [$this, 'render_enabled_field'],
            'perfbase-settings',
            'perfbase_general'
        );

        add_settings_field(
            'sample_rate',
            __('Sample Rate', 'perfbase'),
            [$this, 'render_sample_rate_field'],
            'perfbase-settings',
            'perfbase_general'
        );

        // Advanced settings section
        add_settings_section(
            'perfbase_advanced',
            __('Advanced Settings', 'perfbase'),
            [$this, 'render_advanced_section'],
            'perfbase-settings'
        );

        add_settings_field(
            'api_url',
            __('API URL', 'perfbase'),
            [$this, 'render_api_url_field'],
            'perfbase-settings',
            'perfbase_advanced'
        );

        add_settings_field(
            'timeout',
            __('Timeout (seconds)', 'perfbase'),
            [$this, 'render_timeout_field'],
            'perfbase-settings',
            'perfbase_advanced'
        );

        add_settings_field(
            'proxy',
            __('Proxy Server', 'perfbase'),
            [$this, 'render_proxy_field'],
            'perfbase-settings',
            'perfbase_advanced'
        );

        // Profiling options section
        add_settings_section(
            'perfbase_profiling',
            __('Profiling Options', 'perfbase'),
            [$this, 'render_profiling_section'],
            'perfbase-settings'
        );

        add_settings_field(
            'profile_admin',
            __('Profile Admin Area', 'perfbase'),
            [$this, 'render_profile_admin_field'],
            'perfbase-settings',
            'perfbase_profiling'
        );

        add_settings_field(
            'profile_ajax',
            __('Profile AJAX Requests', 'perfbase'),
            [$this, 'render_profile_ajax_field'],
            'perfbase-settings',
            'perfbase_profiling'
        );

        add_settings_field(
            'profile_cron',
            __('Profile Cron Jobs', 'perfbase'),
            [$this, 'render_profile_cron_field'],
            'perfbase-settings',
            'perfbase_profiling'
        );

        add_settings_field(
            'profile_cli',
            __('Profile WP-CLI Commands', 'perfbase'),
            [$this, 'render_profile_cli_field'],
            'perfbase-settings',
            'perfbase_profiling'
        );

        add_settings_field(
            'flags',
            __('Feature Flags', 'perfbase'),
            [$this, 'render_flags_field'],
            'perfbase-settings',
            'perfbase_profiling'
        );

        // Exclusions section
        add_settings_section(
            'perfbase_exclusions',
            __('Exclusions', 'perfbase'),
            [$this, 'render_exclusions_section'],
            'perfbase-settings'
        );

        add_settings_field(
            'include_http',
            __('Included HTTP Paths', 'perfbase'),
            [$this, 'render_include_http_field'],
            'perfbase-settings',
            'perfbase_exclusions'
        );

        add_settings_field(
            'exclude_http',
            __('Excluded HTTP Paths', 'perfbase'),
            [$this, 'render_exclude_http_field'],
            'perfbase-settings',
            'perfbase_exclusions'
        );

        add_settings_field(
            'exclude_user_agents',
            __('Excluded User Agents', 'perfbase'),
            [$this, 'render_exclude_user_agents_field'],
            'perfbase-settings',
            'perfbase_exclusions'
        );
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook
     * @return void
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_perfbase-settings') {
            return;
        }

        wp_enqueue_style(
            'perfbase-admin',
            PERFBASE_PLUGIN_URL . 'assets/admin.css',
            [],
            PERFBASE_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'perfbase-admin',
            PERFBASE_PLUGIN_URL . 'assets/admin.js',
            ['jquery'],
            PERFBASE_PLUGIN_VERSION,
            true
        );
    }

    /**
     * Add plugin action links
     *
     * @param array $links
     * @return array
     */
    public function add_plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=perfbase-settings'),
            __('Settings', 'perfbase')
        );

        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $config = $this->plugin->get_config();
        $perfbase = $this->plugin->get_perfbase();
        $extension_available = $perfbase ? $perfbase->isExtensionAvailable() : false;

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if (!$extension_available): ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php _e('Warning:', 'perfbase'); ?></strong>
                        <?php _e('The Perfbase PHP extension is not installed or not available. Profiling will not work without it.', 'perfbase'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!empty(sanitize_text_field(wp_unslash($_GET['settings-updated'] ?? '')))): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Settings saved.', 'perfbase'); ?></p>
                </div>
            <?php endif; ?>

            <form action="options.php" method="post">
                <?php
                settings_fields('perfbase_settings');
                do_settings_sections('perfbase-settings');
                submit_button();
                ?>
            </form>

            <div class="perfbase-info">
                <h2><?php _e('System Information', 'perfbase'); ?></h2>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <th><?php _e('Plugin Version', 'perfbase'); ?></th>
                            <td><?php echo esc_html(PERFBASE_PLUGIN_VERSION); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('PHP Version', 'perfbase'); ?></th>
                            <td><?php echo esc_html(PHP_VERSION); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('WordPress Version', 'perfbase'); ?></th>
                            <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Perfbase Extension', 'perfbase'); ?></th>
                            <td>
                                <?php if ($extension_available): ?>
                                    <span style="color: green;"><?php _e('Available', 'perfbase'); ?></span>
                                <?php else: ?>
                                    <span style="color: red;"><?php _e('Not Available', 'perfbase'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Profiling Status', 'perfbase'); ?></th>
                            <td>
                                <?php if ($config['enabled'] && !empty($config['api_key']) && $extension_available): ?>
                                    <span style="color: green;"><?php _e('Active', 'perfbase'); ?></span>
                                <?php else: ?>
                                    <span style="color: red;"><?php _e('Inactive', 'perfbase'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Render general section
     *
     * @return void
     */
    public function render_general_section() {
        echo '<p>' . __('Configure basic Perfbase settings.', 'perfbase') . '</p>';
    }

    /**
     * Render advanced section
     *
     * @return void
     */
    public function render_advanced_section() {
        echo '<p>' . __('Advanced configuration options.', 'perfbase') . '</p>';
    }

    /**
     * Render profiling section
     *
     * @return void
     */
    public function render_profiling_section() {
        echo '<p>' . __('Configure what should be profiled.', 'perfbase') . '</p>';
    }

    /**
     * Render exclusions section
     *
     * @return void
     */
    public function render_exclusions_section() {
        echo '<p>' . __('Configure what should be excluded from profiling.', 'perfbase') . '</p>';
    }

    /**
     * Render API key field
     *
     * @return void
     */
    public function render_api_key_field() {
        $config = $this->plugin->get_config();
        $value = $config['api_key'] ?? '';
        ?>
        <input type="password" name="perfbase_settings[api_key]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description">
            <?php _e('Your Perfbase API key. You can find this in your Perfbase project settings.', 'perfbase'); ?>
        </p>
        <?php
    }

    /**
     * Render enabled field
     *
     * @return void
     */
    public function render_enabled_field() {
        $config = $this->plugin->get_config();
        $checked = !empty($config['enabled']);
        ?>
        <input type="checkbox" name="perfbase_settings[enabled]" value="1" <?php checked($checked); ?> />
        <p class="description">
            <?php _e('Enable or disable Perfbase profiling.', 'perfbase'); ?>
        </p>
        <?php
    }

    /**
     * Render sample rate field
     *
     * @return void
     */
    public function render_sample_rate_field() {
        $config = $this->plugin->get_config();
        $value = $config['sample_rate'] ?? 0.1;
        ?>
        <input type="number" name="perfbase_settings[sample_rate]" value="<?php echo esc_attr($value); ?>"
               min="0" max="1" step="0.01" class="small-text" />
        <p class="description">
            <?php _e('Percentage of requests to profile (0.0 = none, 1.0 = all).', 'perfbase'); ?>
        </p>
        <?php
    }

    /**
     * Render API URL field
     *
     * @return void
     */
    public function render_api_url_field() {
        $config = $this->plugin->get_config();
        $value = $config['api_url'] ?? 'https://ingress.perfbase.cloud';
        ?>
        <input type="url" name="perfbase_settings[api_url]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description">
            <?php _e('Perfbase API endpoint URL.', 'perfbase'); ?>
        </p>
        <?php
    }

    /**
     * Render timeout field
     *
     * @return void
     */
    public function render_timeout_field() {
        $config = $this->plugin->get_config();
        $value = $config['timeout'] ?? 10;
        ?>
        <input type="number" name="perfbase_settings[timeout]" value="<?php echo esc_attr($value); ?>"
               min="1" max="60" class="small-text" />
        <p class="description">
            <?php _e('API request timeout in seconds.', 'perfbase'); ?>
        </p>
        <?php
    }

    /**
     * Render proxy field
     *
     * @return void
     */
    public function render_proxy_field() {
        $config = $this->plugin->get_config();
        $value = $config['proxy'] ?? '';
        ?>
        <input type="text" name="perfbase_settings[proxy]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description">
            <?php _e('Proxy server URL (optional). Format: http://username:password@proxy.example.com:8080', 'perfbase'); ?>
        </p>
        <?php
    }

    /**
     * Render profile admin field
     *
     * @return void
     */
    public function render_profile_admin_field() {
        $config = $this->plugin->get_config();
        $checked = !empty($config['profile_admin']);
        ?>
        <input type="checkbox" name="perfbase_settings[profile_admin]" value="1" <?php checked($checked); ?> />
        <p class="description">
            <?php _e('Profile requests in the WordPress admin area.', 'perfbase'); ?>
        </p>
        <?php
    }

    /**
     * Render profile AJAX field
     *
     * @return void
     */
    public function render_profile_ajax_field() {
        $config = $this->plugin->get_config();
        $checked = !empty($config['profile_ajax']);
        ?>
        <input type="checkbox" name="perfbase_settings[profile_ajax]" value="1" <?php checked($checked); ?> />
        <p class="description">
            <?php _e('Profile AJAX requests.', 'perfbase'); ?>
        </p>
        <?php
    }

    /**
     * Render profile cron field
     *
     * @return void
     */
    public function render_profile_cron_field() {
        $config = $this->plugin->get_config();
        $checked = !empty($config['profile_cron']);
        ?>
        <input type="checkbox" name="perfbase_settings[profile_cron]" value="1" <?php checked($checked); ?> />
        <p class="description">
            <?php _e('Profile WordPress cron jobs.', 'perfbase'); ?>
        </p>
        <?php
    }

    /**
     * Render profile CLI field
     *
     * @return void
     */
    public function render_profile_cli_field() {
        $config = $this->plugin->get_config();
        $checked = !empty($config['profile_cli']);
        ?>
        <input type="checkbox" name="perfbase_settings[profile_cli]" value="1" <?php checked($checked); ?> />
        <p class="description">
            <?php _e('Profile WP-CLI command execution.', 'perfbase'); ?>
        </p>
        <?php
    }

    /**
     * Render flags field
     *
     * @return void
     */
    public function render_flags_field() {
        $config = $this->plugin->get_config();
        $current_flags = (int) ($config['flags'] ?? FeatureFlags::DefaultFlags);

        $available_flags = [
            FeatureFlags::UseCoarseClock => __('Use Coarse Clock (faster, less accurate)', 'perfbase'),
            FeatureFlags::TrackCpuTime => __('Track CPU Time', 'perfbase'),
            FeatureFlags::TrackPdo => __('Track Database Operations', 'perfbase'),
            FeatureFlags::TrackHttp => __('Track HTTP Requests', 'perfbase'),
            FeatureFlags::TrackCaches => __('Track Cache Operations', 'perfbase'),
            FeatureFlags::TrackMongodb => __('Track MongoDB Operations', 'perfbase'),
            FeatureFlags::TrackElasticsearch => __('Track Elasticsearch Operations', 'perfbase'),
            FeatureFlags::TrackQueues => __('Track Queue Operations', 'perfbase'),
            FeatureFlags::TrackFileOperations => __('Track File Operations', 'perfbase'),
        ];

        echo '<fieldset>';
        foreach ($available_flags as $flag => $label) {
            $checked = ($current_flags & $flag) !== 0;
            echo '<label>';
            echo '<input type="checkbox" name="perfbase_settings[flags][]" value="' . esc_attr($flag) . '" ' . checked($checked, true, false) . ' />';
            echo ' ' . esc_html($label);
            echo '</label><br>';
        }
        echo '</fieldset>';

        echo '<p class="description">';
        _e('Select which features to enable during profiling. Note that some features may impact performance.', 'perfbase');
        echo '</p>';
    }

    /**
     * Render excluded paths field
     *
     * @return void
     */
    public function render_include_http_field() {
        $config = $this->plugin->get_config();
        $include = isset($config['include']['http']) && is_array($config['include']['http'])
            ? $config['include']['http']
            : ['*'];
        $value = implode("\n", $include);
        ?>
        <textarea name="perfbase_settings[include_http]" rows="5" class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php _e('Enter one HTTP include pattern per line. Use * to include everything.', 'perfbase'); ?>
        </p>
        <?php
    }

    /**
     * Render excluded HTTP paths/patterns field.
     *
     * @return void
     */
    public function render_exclude_http_field() {
        $config = $this->plugin->get_config();
        $exclude = isset($config['exclude']['http']) && is_array($config['exclude']['http'])
            ? $config['exclude']['http']
            : [];
        $value = implode("\n", $exclude);
        ?>
        <textarea name="perfbase_settings[exclude_http]" rows="5" class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php _e('Enter one HTTP exclude pattern per line. Matching requests will not be profiled.', 'perfbase'); ?>
        </p>
        <?php
    }

    /**
     * Render excluded user agents field
     *
     * @return void
     */
    public function render_exclude_user_agents_field() {
        $config = $this->plugin->get_config();
        $value = implode("\n", $config['exclude_user_agents'] ?? []);
        ?>
        <textarea name="perfbase_settings[exclude_user_agents]" rows="3" class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php _e('Enter one user agent pattern per line. Requests from matching user agents will not be profiled.', 'perfbase'); ?>
        </p>
        <?php
    }

    /**
     * Sanitize settings
     *
     * @param array $input
     * @return array
     */
    public function sanitize_settings($input) {
        $sanitized = [];
        $existing = $this->plugin->get_config();

        // Basic fields
        $sanitized['enabled'] = !empty($input['enabled']);
        $sanitized['api_key'] = sanitize_text_field($input['api_key'] ?? '');
        $sanitized['api_url'] = esc_url_raw($input['api_url'] ?? 'https://ingress.perfbase.cloud');
        $sanitized['sample_rate'] = max(0, min(1, (float) ($input['sample_rate'] ?? 0.1)));
        $sanitized['timeout'] = max(1, min(60, (int) ($input['timeout'] ?? 10)));
        $sanitized['proxy'] = sanitize_text_field($input['proxy'] ?? '');

        // Profiling options
        $sanitized['profile_admin'] = !empty($input['profile_admin']);
        $sanitized['profile_ajax'] = !empty($input['profile_ajax']);
        $sanitized['profile_cron'] = !empty($input['profile_cron']);
        $sanitized['profile_cli'] = !empty($input['profile_cli']);

        // Flags
        $flags = 0;
        if (!empty($input['flags']) && is_array($input['flags'])) {
            foreach ($input['flags'] as $flag) {
                $flags |= (((int) $flag) & FeatureFlags::AllFlags);
            }
        }
        $sanitized['flags'] = $flags & FeatureFlags::AllFlags;

        $sanitized['include'] = $this->sanitizeContextFilters(
            is_array($existing['include'] ?? null) ? $existing['include'] : [],
            'http',
            $input['include_http'] ?? null,
            ['*']
        );
        $sanitized['exclude'] = $this->sanitizeContextFilters(
            is_array($existing['exclude'] ?? null) ? $existing['exclude'] : [],
            'http',
            $input['exclude_http'] ?? ($input['excluded_paths'] ?? null),
            []
        );
        $sanitized['exclude_user_agents'] = $this->sanitizeLineList(
            $input['exclude_user_agents'] ?? ($input['excluded_user_agents'] ?? null)
        );

        return $sanitized;
    }

    /**
     * Sanitize per-context filter arrays while preserving untouched contexts.
     *
     * @param array<string, mixed> $existing
     * @param string $contextKey
     * @param mixed $inputValue
     * @param array<int, string> $default
     * @return array<string, array<int, string>>
     */
    private function sanitizeContextFilters(array $existing, string $contextKey, $inputValue, array $default): array
    {
        $filters = [];

        foreach (['http', 'ajax', 'cron', 'cli'] as $key) {
            if (isset($existing[$key]) && is_array($existing[$key])) {
                $filters[$key] = array_values(array_filter($existing[$key], 'is_string'));
            } else {
                $filters[$key] = $key === $contextKey ? $default : [];
            }
        }

        $sanitizedInput = $this->sanitizeLineList($inputValue);
        $filters[$contextKey] = !empty($sanitizedInput) ? $sanitizedInput : $default;

        return $filters;
    }

    /**
     * Sanitize textarea line input into a string list.
     *
     * @param mixed $value
     * @return array<int, string>
     */
    private function sanitizeLineList($value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $value);
        if (!is_array($lines)) {
            return [];
        }

        $sanitized = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $sanitized[] = sanitize_text_field($line);
            }
        }

        return $sanitized;
    }
}
