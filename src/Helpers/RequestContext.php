<?php

namespace Perfbase\WordPress\Helpers;

/**
 * Handles WordPress request context detection and attribute collection
 */
class RequestContext
{
    /**
     * Get span name for the current request
     *
     * @return string
     */
    public function getSpanName(): string
    {
        if ($this->isAdmin()) {
            return 'wordpress.admin';
        }

        if ($this->isDoingAjax()) {
            return 'wordpress.ajax';
        }

        if ($this->isDoingCron()) {
            return 'wordpress.cron';
        }

        if ($this->isWpCli()) {
            return 'wordpress.cli';
        }

        return 'wordpress.request';
    }

    /**
     * Check if we're in admin context
     *
     * @return bool
     */
    protected function isAdmin(): bool
    {
        return is_admin();
    }

    /**
     * Check if we're doing AJAX
     *
     * @return bool
     */
    protected function isDoingAjax(): bool
    {
        return defined('DOING_AJAX') && DOING_AJAX;
    }

    /**
     * Check if we're doing cron
     *
     * @return bool
     */
    protected function isDoingCron(): bool
    {
        return defined('DOING_CRON') && DOING_CRON;
    }

    /**
     * Check if we're in WP CLI
     *
     * @return bool
     */
    protected function isWpCli(): bool
    {
        return defined('WP_CLI') && WP_CLI;
    }

    /**
     * Get attributes for the current request
     *
     * @return array
     */
    public function getRequestAttributes(): array
    {
        // Get action in format "GET /path" (without query parameters)
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

        // Strip query parameters from action to avoid cardinality explosion
        $path = parse_url($requestUri, PHP_URL_PATH) ?: $requestUri;
        $action = sprintf('%s %s', $method, $path);

        // Get hostname
        $hostname = gethostname();
        if (!is_string($hostname)) {
            $hostname = '';
        }

        // Get environment
        $environment = $this->getEnvironment();

        // Get PHP version
        $phpVersion = phpversion();
        if (!is_string($phpVersion)) {
            $phpVersion = '';
        }

        $attributes = [
            // Core attributes matching ClickHouse schema
            'action' => $action,
            'user_ip' => \Perfbase\SDK\Utils\EnvironmentUtils::getUserIp() ?? '',
            'user_agent' => \Perfbase\SDK\Utils\EnvironmentUtils::getUserUserAgent() ?? '',
            'hostname' => $hostname,
            'environment' => $environment,

            // HTTP attributes (ClickHouse schema without dots)
            'http_method' => $method,
            'http_url' => $this->getCurrentUrl(),

            // Version information
            'app_version' => $this->getAppVersion(),
            'php_version' => $phpVersion,

            // Additional WordPress info (with dots for namespacing)
            'wordpress.version' => get_bloginfo('version'),
            'perfbase.version' => PERFBASE_PLUGIN_VERSION,
        ];

        // Add user_id if logged in
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $attributes['user_id'] = (string) $user->ID;
        }

        // Add important WordPress query parameters as separate attributes
        $this->addImportantQueryParams($attributes, $requestUri);

        // Add query variables if available
        if (function_exists('get_query_var')) {
            if ($post_id = get_query_var('p')) {
                $attributes['wordpress.post_id'] = (string) $post_id;
            }
            if ($page_id = get_query_var('page_id')) {
                $attributes['wordpress.page_id'] = (string) $page_id;
            }
        }

        return $attributes;
    }

    /**
     * Add important WordPress query parameters as separate attributes
     *
     * @param array $attributes
     * @param string $requestUri
     * @return void
     */
    private function addImportantQueryParams(array &$attributes, string $requestUri): void
    {
        $queryString = parse_url($requestUri, PHP_URL_QUERY);
        if (!$queryString) {
            return;
        }

        parse_str($queryString, $params);

        // Track AJAX action
        if (isset($params['action'])) {
            $attributes['wordpress.ajax_action'] = (string) $params['action'];
        }

        // Track REST API route
        if (isset($params['rest_route'])) {
            $attributes['wordpress.rest_route'] = (string) $params['rest_route'];
        }

        // Track admin page
        if (isset($params['page'])) {
            $attributes['wordpress.admin_page'] = (string) $params['page'];
        }
    }

    /**
     * Get the environment name
     *
     * @return string
     */
    private function getEnvironment(): string
    {
        // Check for WP_ENVIRONMENT_TYPE (WordPress 5.5+)
        if (function_exists('wp_get_environment_type')) {
            return wp_get_environment_type();
        }

        // Check for custom constant
        if (defined('PERFBASE_ENVIRONMENT')) {
            return PERFBASE_ENVIRONMENT;
        }

        // Check for WP_DEBUG as fallback
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return 'development';
        }

        return 'production';
    }

    /**
     * Get the application version
     *
     * @return string
     */
    private function getAppVersion(): string
    {
        // Check for custom constant
        if (defined('PERFBASE_APP_VERSION')) {
            return PERFBASE_APP_VERSION;
        }

        // Fall back to WordPress version
        return get_bloginfo('version');
    }

    /**
     * Get WordPress-specific context attributes
     *
     * @return array
     */
    public function getWordPressContext(): array
    {
        $attributes = [];

        // WordPress conditional tags
        $conditionals = [
            'is_front_page' => is_front_page(),
            'is_home' => is_home(),
            'is_admin' => is_admin(),
            'is_single' => is_single(),
            'is_page' => is_page(),
            'is_404' => is_404(),
            'is_search' => is_search(),
            'is_archive' => is_archive(),
            'is_attachment' => is_attachment(),
            'is_feed' => is_feed(),
        ];

        foreach ($conditionals as $condition => $value) {
            if ($value) {
                $attributes["wordpress.{$condition}"] = 'true';
            }
        }

        // Add current user context
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $attributes['user.id'] = (string) $user->ID;
            $attributes['user.role'] = implode(',', $user->roles);
        }

        // Add post/page context
        if (is_singular()) {
            $post = get_queried_object();
            if ($post) {
                $attributes['wordpress.post.id'] = (string) $post->ID;
                $attributes['wordpress.post.type'] = $post->post_type;
                $attributes['wordpress.post.status'] = $post->post_status;
            }
        }

        // Add category/taxonomy context
        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term) {
                $attributes['wordpress.term.id'] = (string) $term->term_id;
                $attributes['wordpress.term.taxonomy'] = $term->taxonomy;
                $attributes['wordpress.term.slug'] = $term->slug;
            }
        }

        return $attributes;
    }

    /**
     * Get template and theme information
     *
     * @return array
     */
    public function getTemplateContext(): array
    {
        $attributes = [];

        // Add template information
        $template = get_page_template_slug();
        if ($template) {
            $attributes['wordpress.template'] = $template;
        }

        // Add theme information
        $theme = wp_get_theme();
        $attributes['wordpress.theme.name'] = $theme->get('Name');
        $attributes['wordpress.theme.version'] = $theme->get('Version');

        return $attributes;
    }

    /**
     * Get final attributes to add before finishing profiling
     *
     * @return array
     */
    public function getFinalAttributes(): array
    {
        $attributes = [];

        // Memory usage
        $attributes['memory.peak'] = (string) memory_get_peak_usage(true);
        $attributes['memory.current'] = (string) memory_get_usage(true);

        // Database queries
        if (function_exists('get_num_queries')) {
            $attributes['database.queries'] = (string) get_num_queries();
        }

        // HTTP response code (ClickHouse schema without dot)
        $statusCode = http_response_code();
        if ($statusCode !== false) {
            $attributes['http_status_code'] = (string) $statusCode;
        }

        return $attributes;
    }

    /**
     * Get the current URL without query string.
     *
     * Query parameters are excluded to avoid leaking sensitive values
     * (nonces, tokens, session IDs). Important WordPress query params
     * are extracted as separate attributes via addImportantQueryParams().
     *
     * @return string
     */
    public function getCurrentUrl(): string
    {
        $protocol = is_ssl() ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        // Strip query string to avoid leaking sensitive params
        $path = parse_url($uri, PHP_URL_PATH) ?: $uri;

        return $protocol . $host . $path;
    }

    /**
     * Check if the current request should be profiled based on context.
     *
     * Uses FilterMatcher for include/exclude pattern matching against the
     * request path, and checks user agent exclusions.
     *
     * @param array<string, mixed> $config
     * @return bool
     */
    public function shouldProfileRequest(array $config): bool
    {
        // Skip if in admin area (unless enabled)
        if (is_admin() && empty($config['profile_admin'])) {
            return false;
        }

        // Check path-based include/exclude filters
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH) ?: $requestUri;
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $components = [
            $path,
            sprintf('%s %s', $method, $path),
        ];

        $include = $config['include'] ?? [];
        $exclude = $config['exclude'] ?? [];

        if (!empty($include) && !empty($exclude)) {
            if (!\Perfbase\WordPress\Support\FilterMatcher::passesFilters(
                $components,
                is_array($include) ? $include : [],
                is_array($exclude) ? $exclude : [],
                'http'
            )) {
                return false;
            }
        }

        // Check excluded user agents
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $excludeAgents = $config['exclude_user_agents'] ?? [];
        if (is_array($excludeAgents)) {
            foreach ($excludeAgents as $excludedAgent) {
                if (is_string($excludedAgent) && stripos($userAgent, $excludedAgent) !== false) {
                    return false;
                }
            }
        }

        return true;
    }
}