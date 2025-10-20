<?php
/**
 * Main Perfbase Plugin Class
 *
 * @package Perfbase\WordPress
 */

namespace Perfbase\WordPress;

use Perfbase\SDK\Config;
use Perfbase\SDK\FeatureFlags;
use Perfbase\SDK\Perfbase;
use Perfbase\WordPress\Helpers\ConfigManager;
use Perfbase\WordPress\Helpers\RequestContext;
use Perfbase\WordPress\Helpers\SamplingStrategy;

/**
 * Main plugin class that handles WordPress integration
 */
class PerfbasePlugin {

    /**
     * Plugin version
     */
    public const VERSION = '1.0.0';

    /**
     * Perfbase SDK instance
     *
     * @var Perfbase|null
     */
    private $perfbase;

    /**
     * Plugin configuration
     *
     * @var array
     */
    private $config;

    /**
     * Active profiling spans
     *
     * @var array
     */
    private $active_spans = [];

    /**
     * Admin interface handler
     *
     * @var PerfbaseAdmin|null
     */
    private $admin;

    /**
     * Profiler instance
     *
     * @var PerfbaseProfiler|null
     */
    private $profiler;

    /**
     * Configuration manager
     *
     * @var ConfigManager
     */
    private $config_manager;

    /**
     * Request context helper
     *
     * @var RequestContext
     */
    private $request_context;

    /**
     * Sampling strategy
     *
     * @var SamplingStrategy
     */
    private $sampling_strategy;

    /**
     * Constructor with dependency injection
     *
     * @param ConfigManager|null $config_manager
     * @param RequestContext|null $request_context
     * @param SamplingStrategy|null $sampling_strategy
     */
    public function __construct(
        ?ConfigManager $config_manager = null,
        ?RequestContext $request_context = null,
        ?SamplingStrategy $sampling_strategy = null
    ) {
        $this->config_manager = $config_manager ?? new ConfigManager();
        $this->request_context = $request_context ?? new RequestContext();
        $this->sampling_strategy = $sampling_strategy ?? new SamplingStrategy();
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init() {
        // Load configuration
        $this->load_config();

        // Always initialize admin interface (needed for configuration)
        $this->init_admin();

        // Load text domain
        $this->load_textdomain();

        // Early return if not enabled
        if (!$this->is_enabled()) {
            return;
        }

        // Initialize profiling components
        $this->init_perfbase_sdk();
        $this->init_profiler();

        // Register hooks
        $this->register_hooks();
    }

    /**
     * Load plugin configuration
     *
     * @return void
     */
    private function load_config() {
        $this->config = $this->config_manager->getConfig();
    }

    /**
     * Check if profiling is enabled
     *
     * @return bool
     */
    private function is_enabled() {
        return $this->config_manager->isEnabled($this->config);
    }

    /**
     * Initialize the Perfbase SDK
     *
     * @return void
     * @throws \Exception
     */
    private function init_perfbase_sdk() {
        try {
            $config = Config::fromArray([
                'api_key' => $this->config['api_key'],
                'api_url' => $this->config['api_url'],
                'flags' => (int) $this->config['flags'],
                'timeout' => (int) $this->config['timeout'],
                'proxy' => $this->config['proxy'] ?: null,
            ]);

            $this->perfbase = new Perfbase($config);
        } catch (\Exception $e) {
            error_log('Perfbase SDK initialization failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Initialize admin interface
     *
     * @return void
     */
    private function init_admin() {
        if (is_admin()) {
            require_once PERFBASE_PLUGIN_DIR . 'src/PerfbaseAdmin.php';
            $this->admin = new PerfbaseAdmin($this);
        }
    }

    /**
     * Initialize profiler
     *
     * @return void
     */
    private function init_profiler() {
        require_once PERFBASE_PLUGIN_DIR . 'src/PerfbaseProfiler.php';
        $this->profiler = new PerfbaseProfiler($this);
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    private function register_hooks() {
        // Core profiling hooks
        add_action('init', [$this, 'start_request_profiling'], -999);
        add_action('wp_loaded', [$this, 'wp_loaded_profiling']);
        add_action('shutdown', [$this, 'finish_request_profiling'], 999);

        // Template hooks
        add_action('template_redirect', [$this, 'template_redirect_profiling']);

        // AJAX hooks
        if ($this->config['profile_ajax']) {
            add_action('wp_ajax_nopriv_*', [$this, 'start_ajax_profiling'], -999);
            add_action('wp_ajax_*', [$this, 'start_ajax_profiling'], -999);
        }

        // Cron hooks
        if ($this->config['profile_cron']) {
            add_action('wp_cron', [$this, 'start_cron_profiling'], -999);
        }

        // Database query profiling
        if ($this->should_profile_queries()) {
            add_filter('query', [$this, 'profile_database_query'], 10, 1);
        }

        // HTTP request profiling
        add_filter('pre_http_request', [$this, 'profile_http_request'], 10, 3);

        // Error handling
        add_action('wp_die_handler', [$this, 'handle_wp_die']);
    }

    /**
     * Load plugin text domain
     *
     * @return void
     */
    private function load_textdomain() {
        load_plugin_textdomain(
            'perfbase',
            false,
            dirname(plugin_basename(PERFBASE_PLUGIN_FILE)) . '/languages'
        );
    }

    /**
     * Start profiling for the current request
     *
     * @return void
     */
    public function start_request_profiling() {
        if (!$this->request_context->shouldProfileRequest($this->config)) {
            return;
        }

        $span_name = $this->request_context->getSpanName();
        $attributes = $this->request_context->getRequestAttributes();

        try {
            $this->perfbase->startTraceSpan($span_name, $attributes);
            $this->active_spans[] = $span_name;
        } catch (\Exception $e) {
            error_log('Failed to start Perfbase profiling: ' . $e->getMessage());
        }
    }

    /**
     * Profile wp_loaded hook
     *
     * @return void
     */
    public function wp_loaded_profiling() {
        if ($this->perfbase && !empty($this->active_spans)) {
            $this->perfbase->setAttribute('wp_loaded', 'true');
        }
    }

    /**
     * Profile template_redirect hook
     *
     * @return void
     */
    public function template_redirect_profiling() {
        if ($this->perfbase && !empty($this->active_spans)) {
            $template_context = $this->request_context->getTemplateContext();
            foreach ($template_context as $key => $value) {
                $this->perfbase->setAttribute($key, $value);
            }

            $wordpress_context = $this->request_context->getWordPressContext();
            foreach ($wordpress_context as $key => $value) {
                $this->perfbase->setAttribute($key, $value);
            }
        }
    }

    /**
     * Finish profiling for the current request
     *
     * @return void
     */
    public function finish_request_profiling() {
        if (!$this->perfbase || empty($this->active_spans)) {
            return;
        }

        try {
            // Add final attributes
            $final_attributes = $this->request_context->getFinalAttributes();
            foreach ($final_attributes as $key => $value) {
                $this->perfbase->setAttribute($key, $value);
            }

            // Stop all active spans
            foreach ($this->active_spans as $span_name) {
                $this->perfbase->stopTraceSpan($span_name);
            }

            // Submit trace if we should sample this request
            if ($this->sampling_strategy->getSamplingDecision($this->config)) {
                $this->perfbase->submitTrace();
            }

            $this->active_spans = [];
        } catch (\Exception $e) {
            error_log('Failed to finish Perfbase profiling: ' . $e->getMessage());
        }
    }

    /**
     * Start AJAX profiling
     *
     * @return void
     */
    public function start_ajax_profiling() {
        $action = $_REQUEST['action'] ?? 'unknown';
        $span_name = "ajax.{$action}";

        $attributes = [
            'request.type' => 'ajax',
            'ajax.action' => $action,
        ];

        try {
            $this->perfbase->startTraceSpan($span_name, $attributes);
            $this->active_spans[] = $span_name;
        } catch (\Exception $e) {
            error_log('Failed to start AJAX profiling: ' . $e->getMessage());
        }
    }

    /**
     * Start cron profiling
     *
     * @return void
     */
    public function start_cron_profiling() {
        $span_name = 'cron.execution';

        $attributes = [
            'request.type' => 'cron',
        ];

        try {
            $this->perfbase->startTraceSpan($span_name, $attributes);
            $this->active_spans[] = $span_name;
        } catch (\Exception $e) {
            error_log('Failed to start cron profiling: ' . $e->getMessage());
        }
    }


    /**
     * Check if database queries should be profiled
     *
     * @return bool
     */
    private function should_profile_queries() {
        return ($this->config['flags'] & FeatureFlags::TrackPdo) !== 0;
    }

    /**
     * Profile database query
     *
     * @param string $query
     * @return string
     */
    public function profile_database_query($query) {
        // This is a simplified implementation
        // In a full implementation, you'd want to measure query execution time
        if ($this->perfbase) {
            $this->perfbase->setAttribute('database.last_query', substr($query, 0, 100));
        }

        return $query;
    }

    /**
     * Profile HTTP request
     *
     * @param mixed $preempt
     * @param array $args
     * @param string $url
     * @return mixed
     */
    public function profile_http_request($preempt, $args, $url) {
        if ($this->perfbase && ($this->config['flags'] & FeatureFlags::TrackHttp)) {
            $this->perfbase->setAttribute('http.external_request', $url);
        }

        return $preempt;
    }

    /**
     * Handle wp_die
     *
     * @param callable $handler
     * @return callable
     */
    public function handle_wp_die($handler) {
        if ($this->perfbase) {
            $this->perfbase->setAttribute('wordpress.wp_die', 'true');
        }

        return $handler;
    }

    /**
     * Get plugin configuration
     *
     * @return array
     */
    public function get_config() {
        return $this->config;
    }

    /**
     * Get Perfbase SDK instance
     *
     * @return Perfbase|null
     */
    public function get_perfbase() {
        return $this->perfbase;
    }

    /**
     * Update plugin configuration
     *
     * @param array $new_config
     * @return bool
     */
    public function update_config($new_config) {
        $this->config = array_merge($this->config, $new_config);
        return $this->config_manager->updateConfig($this->config);
    }
}