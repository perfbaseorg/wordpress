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
use Perfbase\WordPress\Lifecycle\AbstractWordPressProfiler;
use Perfbase\WordPress\Lifecycle\AjaxRequestLifecycle;
use Perfbase\WordPress\Lifecycle\CliLifecycle;
use Perfbase\WordPress\Lifecycle\CronLifecycle;
use Perfbase\WordPress\Lifecycle\HttpRequestLifecycle;
use Perfbase\WordPress\Support\ErrorHandler;

/**
 * Main plugin class that orchestrates WordPress integration.
 *
 * This class is the thin adapter layer — it creates lifecycle instances
 * for each request context and delegates profiling to them.
 */
class PerfbasePlugin {
    use ErrorHandler;

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
     * @var array<string, mixed>
     */
    private $config = [];

    /**
     * The active lifecycle instance for the current request.
     *
     * @var AbstractWordPressProfiler|null
     */
    private $active_lifecycle;

    /**
     * Admin interface handler
     *
     * @var PerfbaseAdmin|null
     */
    private $admin;

    /**
     * Profiler instance (WordPress hook-based attribute collection)
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
     * Constructor with dependency injection
     *
     * @param ConfigManager|null $config_manager
     * @param RequestContext|null $request_context
     */
    public function __construct(
        ?ConfigManager $config_manager = null,
        ?RequestContext $request_context = null
    ) {
        $this->config_manager = $config_manager ?? new ConfigManager();
        $this->request_context = $request_context ?? new RequestContext();
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init(): void
    {
        $this->load_config();
        $this->init_admin();
        $this->load_textdomain();

        if (!$this->is_enabled()) {
            return;
        }

        $this->init_perfbase_sdk();
        $this->init_profiler();
        $this->register_hooks();
    }

    /**
     * Load plugin configuration
     *
     * @return void
     */
    private function load_config(): void
    {
        $this->config = $this->config_manager->getConfig();
    }

    /**
     * Check if profiling is enabled
     *
     * @return bool
     */
    private function is_enabled(): bool
    {
        return $this->config_manager->isEnabled($this->config);
    }

    /**
     * Initialize the Perfbase SDK.
     *
     * Degrades gracefully if the extension is unavailable.
     *
     * @return void
     */
    private function init_perfbase_sdk(): void
    {
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
            // $this->perfbase remains null — lifecycle classes check for it.
            $this->handleProfilingError($e, $this->config, 'sdk_init');
        }
    }

    /**
     * Initialize admin interface
     *
     * @return void
     */
    private function init_admin(): void
    {
        if (is_admin()) {
            require_once PERFBASE_PLUGIN_DIR . 'src/PerfbaseAdmin.php';
            $this->admin = new PerfbaseAdmin($this);
        }
    }

    /**
     * Initialize profiler (WordPress hook-based attribute collection)
     *
     * @return void
     */
    private function init_profiler(): void
    {
        require_once PERFBASE_PLUGIN_DIR . 'src/PerfbaseProfiler.php';
        $this->profiler = new PerfbaseProfiler($this);
    }

    /**
     * Register WordPress hooks for profiling lifecycle.
     *
     * Context detection happens in on_init() — we register one entry point
     * and detect AJAX/cron/CLI there, rather than relying on wildcard hooks
     * that WordPress does not support.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        // Single entry point — detects context and creates the right lifecycle
        add_action('init', [$this, 'on_init'], -999);
        add_action('template_redirect', [$this, 'on_template_redirect']);
        add_action('shutdown', [$this, 'on_shutdown'], 999);

        // Attribute collection hooks (lightweight, set directly on SDK)
        if (($this->config['flags'] & FeatureFlags::TrackPdo) !== 0) {
            add_filter('query', [$this, 'on_database_query'], 10, 1);
        }
        add_filter('pre_http_request', [$this, 'on_http_request'], 10, 3);
        add_filter('wp_die_handler', [$this, 'on_wp_die']);
    }

    // ------------------------------------------------------------------
    // Hook handlers — create and manage lifecycle instances
    // ------------------------------------------------------------------

    /**
     * Main profiling entry point (init hook, priority -999).
     *
     * Detects the request context and creates the appropriate lifecycle.
     * WordPress doesn't support wildcard hook registration, so we detect
     * AJAX/cron/CLI here rather than registering separate hooks.
     *
     * @return void
     */
    public function on_init(): void
    {
        $lifecycle = $this->createLifecycleForContext();
        $lifecycle->startProfiling();
        $this->active_lifecycle = $lifecycle;
    }

    /**
     * Add WordPress template/theme context (template_redirect hook).
     *
     * @return void
     */
    public function on_template_redirect(): void
    {
        if ($this->active_lifecycle instanceof HttpRequestLifecycle) {
            $this->active_lifecycle->addWordPressContext();
        }
    }

    /**
     * Finish profiling and submit (shutdown hook, priority 999).
     *
     * @return void
     */
    public function on_shutdown(): void
    {
        if (!$this->active_lifecycle) {
            return;
        }

        try {
            if ($this->active_lifecycle instanceof HttpRequestLifecycle) {
                $this->active_lifecycle->addFinalAttributes();
            }

            $this->active_lifecycle->stopProfiling();
        } catch (\Throwable $e) {
            $this->handleProfilingError($e, $this->config, 'shutdown');
        }

        $this->active_lifecycle = null;
    }

    /**
     * Detect the current WordPress context and create the appropriate lifecycle.
     *
     * @return AbstractWordPressProfiler
     */
    private function createLifecycleForContext(): AbstractWordPressProfiler
    {
        // AJAX requests (detected via DOING_AJAX constant)
        if (defined('DOING_AJAX') && DOING_AJAX && !empty($this->config['profile_ajax'])) {
            $action = $_REQUEST['action'] ?? 'unknown';
            return new AjaxRequestLifecycle($action, $this);
        }

        // Cron requests (detected via DOING_CRON constant)
        if (defined('DOING_CRON') && DOING_CRON && !empty($this->config['profile_cron'])) {
            return new CronLifecycle($this);
        }

        // WP-CLI requests (detected via WP_CLI constant)
        if (defined('WP_CLI') && WP_CLI && !empty($this->config['profile_cli'])) {
            $command = $_SERVER['argv'][1] ?? 'unknown';
            return new CliLifecycle($command, $this);
        }

        // Default: standard HTTP request
        return new HttpRequestLifecycle($this, $this->request_context);
    }

    // ------------------------------------------------------------------
    // Attribute collection hooks (lightweight — set on SDK directly)
    // ------------------------------------------------------------------

    /**
     * Profile database query.
     *
     * @param string $query
     * @return string
     */
    public function on_database_query(string $query): string
    {
        if ($this->perfbase) {
            $this->perfbase->setAttribute('database.last_query', substr($query, 0, 100));
        }
        return $query;
    }

    /**
     * Profile HTTP request.
     *
     * @param mixed $preempt
     * @param mixed $args
     * @param string $url
     * @return mixed
     */
    public function on_http_request($preempt, $args, string $url)
    {
        if ($this->perfbase && ($this->config['flags'] & FeatureFlags::TrackHttp)) {
            $this->perfbase->setAttribute('http.external_request', $url);
        }
        return $preempt;
    }

    /**
     * Handle wp_die.
     *
     * @param callable $handler
     * @return callable
     */
    public function on_wp_die(callable $handler): callable
    {
        if ($this->perfbase) {
            $this->perfbase->setAttribute('wordpress.wp_die', 'true');
        }
        return $handler;
    }

    // ------------------------------------------------------------------
    // Textdomain
    // ------------------------------------------------------------------

    /**
     * Load plugin text domain
     *
     * @return void
     */
    private function load_textdomain(): void
    {
        load_plugin_textdomain(
            'perfbase',
            false,
            dirname(plugin_basename(PERFBASE_PLUGIN_FILE)) . '/languages'
        );
    }

    // ------------------------------------------------------------------
    // Getters and setters
    // ------------------------------------------------------------------

    /**
     * Get plugin configuration
     *
     * @return array<string, mixed>
     */
    public function get_config(): array
    {
        return $this->config;
    }

    /**
     * Get Perfbase SDK instance
     *
     * @return Perfbase|null
     */
    public function get_perfbase(): ?Perfbase
    {
        return $this->perfbase;
    }

    /**
     * Get the active lifecycle instance (for testing).
     *
     * @return AbstractWordPressProfiler|null
     */
    public function get_active_lifecycle(): ?AbstractWordPressProfiler
    {
        return $this->active_lifecycle;
    }

    /**
     * Update plugin configuration
     *
     * @param array<string, mixed> $new_config
     * @return bool
     */
    public function update_config(array $new_config): bool
    {
        $this->config = array_merge($this->config, $new_config);
        return $this->config_manager->updateConfig($this->config);
    }
}
