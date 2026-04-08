<?php

namespace Perfbase\WordPress\Helpers;

use Perfbase\SDK\FeatureFlags;

/**
 * Handles WordPress configuration management for Perfbase
 */
class ConfigManager
{
    /**
     * Get plugin configuration from WordPress options
     *
     * @return array
     */
    public function getConfig(): array
    {
        $defaults = $this->getDefaultConfig();
        $saved_settings = get_option('perfbase_settings', []);

        // wp_parse_args merges saved settings over defaults
        $config = wp_parse_args($saved_settings, $defaults);

        // wp-config.php constants override everything (highest priority)
        $config = $this->applyConstants($config);

        return $config;
    }

    /**
     * Apply wp-config.php constants over the merged config.
     *
     * Constants take highest priority: defaults < WordPress options < constants.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function applyConstants(array $config): array
    {
        $constantMap = [
            'PERFBASE_ENABLED'     => 'enabled',
            'PERFBASE_DEBUG'       => 'debug',
            'PERFBASE_LOG_ERRORS'  => 'log_errors',
            'PERFBASE_API_KEY'     => 'api_key',
            'PERFBASE_API_URL'     => 'api_url',
            'PERFBASE_SAMPLE_RATE' => 'sample_rate',
            'PERFBASE_FLAGS'       => 'flags',
            'PERFBASE_TIMEOUT'     => 'timeout',
            'PERFBASE_PROXY'       => 'proxy',
        ];

        foreach ($constantMap as $constant => $key) {
            if (defined($constant)) {
                $config[$key] = constant($constant);
            }
        }

        return $config;
    }

    /**
     * Update plugin configuration
     *
     * @param array $config
     * @return bool
     */
    public function updateConfig(array $config): bool
    {
        return update_option('perfbase_settings', $config);
    }

    /**
     * Get default configuration values
     *
     * @return array
     */
    public function getDefaultConfig(): array
    {
        return [
            'enabled' => false,
            'debug' => false,
            'log_errors' => true,
            'api_key' => '',
            'api_url' => 'https://ingress.perfbase.cloud',
            'sample_rate' => 0.1,
            'flags' => FeatureFlags::DefaultFlags,
            'timeout' => 10,
            'proxy' => '',
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
                'spider',
            ]
        ];
    }

    /**
     * Check if profiling is enabled
     *
     * @param array $config
     * @return bool
     */
    public function isEnabled(array $config): bool
    {
        return (bool) $config['enabled'] && !empty($config['api_key']);
    }

    /**
     * Validate configuration
     *
     * @param array $config
     * @return array Array of validation errors (empty if valid)
     */
    public function validateConfig(array $config): array
    {
        $errors = [];

        if (empty($config['api_key'])) {
            $errors['api_key'] = 'API key is required';
        }

        if (!filter_var($config['api_url'] ?? '', FILTER_VALIDATE_URL)) {
            $errors['api_url'] = 'Invalid API URL';
        }

        $sample_rate = (float) ($config['sample_rate'] ?? 0);
        if ($sample_rate < 0 || $sample_rate > 1) {
            $errors['sample_rate'] = 'Sample rate must be between 0.0 and 1.0';
        }

        $timeout = (int) ($config['timeout'] ?? 0);
        if ($timeout <= 0) {
            $errors['timeout'] = 'Timeout must be greater than 0';
        }

        $flags = (int) ($config['flags'] ?? 0);
        if ($flags < 0 || $flags > FeatureFlags::AllFlags) {
            $errors['flags'] = 'Invalid flags value';
        }

        return $errors;
    }
}
