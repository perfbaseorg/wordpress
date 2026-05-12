<?php

namespace Perfbase\WordPress\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles WordPress request context detection and attribute collection
 */
class RequestContext
{
    private const MAX_METHOD_LENGTH = 16;
    private const MAX_HOST_LENGTH = 255;
    private const MAX_PATH_LENGTH = 2048;
    private const MAX_USER_AGENT_LENGTH = 512;
    private const MAX_QUERY_VALUE_LENGTH = 512;
    private const MAX_QUERY_KEY_LENGTH = 128;

    /**
     * Get span name for the current request
     *
     * @return string
     */
    public function getSpanName(): string
    {
        if ($this->isDoingCron()) {
            return 'cron';
        }

        if ($this->isDoingAjax()) {
            return 'ajax';
        }

        if ($this->isWpCli()) {
            return 'cli';
        }

        return 'http';
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
        $method = $this->getRequestMethod();
        $requestUri = $this->getServerValue('REQUEST_URI', '/');

        // Strip query parameters from action to avoid cardinality explosion
        $path = $this->extractRequestPath($requestUri);
        $action = sprintf('%s %s', $method, $path);

        // Get hostname
        $hostname = $this->sanitizeAttributeValue(gethostname(), self::MAX_HOST_LENGTH);

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
            'user_ip' => $this->getUserIp(),
            'user_agent' => $this->sanitizeAttributeValue(
                $this->getServerValue('HTTP_USER_AGENT', ''),
                self::MAX_USER_AGENT_LENGTH
            ),
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
        $queryString = wp_parse_url($requestUri, PHP_URL_QUERY);
        if (!$queryString) {
            return;
        }

        parse_str($queryString, $params);

        // Track AJAX action
        if (isset($params['action'])) {
            $attributes['wordpress.ajax_action'] = $this->sanitizeKeyAttribute($params['action']);
        }

        // Track REST API route
        if (isset($params['rest_route'])) {
            $attributes['wordpress.rest_route'] = $this->sanitizePathAttribute($params['rest_route']);
        }

        // Track admin page
        if (isset($params['page'])) {
            $attributes['wordpress.admin_page'] = $this->sanitizeKeyAttribute($params['page']);
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
        $host = $this->sanitizeHost($this->getServerValue('HTTP_HOST', ''));
        $uri = $this->getServerValue('REQUEST_URI', '');
        if ($uri === '') {
            return $protocol . $host;
        }

        // Strip query string to avoid leaking sensitive params
        $path = $this->extractRequestPath($uri);

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
        $requestUri = $this->getServerValue('REQUEST_URI', '/');
        $path = $this->extractRequestPath($requestUri);
        $method = $this->getRequestMethod();

        $components = [
            $path,
            sprintf('%s %s', $method, $path),
        ];

        $include = $config['include'] ?? [];
        $exclude = $config['exclude'] ?? [];

        if (!empty($include)) {
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
        $userAgent = $this->sanitizeAttributeValue(
            $this->getServerValue('HTTP_USER_AGENT', ''),
            self::MAX_USER_AGENT_LENGTH
        );
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

    /**
     * Read and unslash a server value when WordPress helpers are available.
     */
    private function getServerValue(string $key, string $default = ''): string
    {
        if (!isset($_SERVER[$key]) || !is_scalar($_SERVER[$key])) {
            return $default;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Centralized unslash happens immediately below.
        $value = $_SERVER[$key];

        return $this->unslash((string) $value);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function unslash($value)
    {
        if (function_exists('wp_unslash')) {
            return wp_unslash($value);
        }

        if (is_array($value)) {
            return array_map([$this, 'unslash'], $value);
        }

        return is_string($value) ? stripslashes($value) : $value;
    }

    private function getRequestMethod(): string
    {
        $method = strtoupper($this->getServerValue('REQUEST_METHOD', 'GET'));
        $method = preg_replace('/[^A-Z]/', '', $method);
        $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

        if (
            !is_string($method) ||
            $method === '' ||
            strlen($method) > self::MAX_METHOD_LENGTH ||
            !in_array($method, $allowedMethods, true)
        ) {
            return 'GET';
        }

        return $method;
    }

    private function extractRequestPath(string $requestUri): string
    {
        $path = wp_parse_url($requestUri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        $path = $this->sanitizeAttributeValue($path, self::MAX_PATH_LENGTH);
        $path = preg_replace('/[^A-Za-z0-9\-._~!$&\'()*+,;=:@\/%]/', '', $path);
        $path = is_string($path) ? $path : '/';

        if ($path === '' || $path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }

        return $this->truncate($path, self::MAX_PATH_LENGTH);
    }

    private function sanitizeHost(string $host): string
    {
        $host = $this->sanitizeAttributeValue($host, self::MAX_HOST_LENGTH);
        $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $host);

        return is_string($host) ? $this->truncate($host, self::MAX_HOST_LENGTH) : '';
    }

    /**
     * @param mixed $value
     */
    private function sanitizeAttributeValue($value, int $maxLength): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $value = $this->unslash((string) $value);
        if (function_exists('sanitize_text_field')) {
            $value = sanitize_text_field($value);
        } else {
            $value = wp_strip_all_tags($value);
            $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value);
            $value = is_string($value) ? trim($value) : '';
        }

        return $this->truncate((string) $value, $maxLength);
    }

    /**
     * @param mixed $value
     */
    private function sanitizePathAttribute($value): string
    {
        $value = $this->sanitizeAttributeValue($value, self::MAX_QUERY_VALUE_LENGTH);
        $value = preg_replace('/[^A-Za-z0-9\-._~!$&\'()*+,;=:@\/%]/', '', $value);

        return is_string($value) ? $this->truncate($value, self::MAX_QUERY_VALUE_LENGTH) : '';
    }

    /**
     * @param mixed $value
     */
    private function sanitizeKeyAttribute($value): string
    {
        $value = $this->sanitizeAttributeValue($value, self::MAX_QUERY_KEY_LENGTH);

        if (function_exists('sanitize_key')) {
            return $this->truncate(sanitize_key($value), self::MAX_QUERY_KEY_LENGTH);
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9_\-]/', '_', $value);
        $value = preg_replace('/_+/', '_', is_string($value) ? $value : '');

        return $this->truncate(trim((string) $value, '_'), self::MAX_QUERY_KEY_LENGTH);
    }

    private function getUserIp(): string
    {
        $userIp = \Perfbase\SDK\Utils\EnvironmentUtils::getUserIp();
        if (!is_string($userIp) || filter_var($userIp, FILTER_VALIDATE_IP) === false) {
            return '';
        }

        return $userIp;
    }

    private function truncate(string $value, int $maxLength): string
    {
        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength);
    }
}
