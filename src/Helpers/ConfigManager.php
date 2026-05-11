<?php

namespace Perfbase\WordPress\Helpers;

use Perfbase\SDK\FeatureFlags;

/**
 * Handles WordPress configuration management for Perfbase
 */
class ConfigManager
{
    /**
     * Get the default HTTP status codes that should be submitted.
     *
     * @return array<int, int>
     */
    public static function getDefaultHttpStatusCodes(): array
    {
        return array_merge(range(200, 299), range(500, 599));
    }

    /**
     * Get plugin configuration from WordPress options
     *
     * @return array
     */
    public function getConfig(): array
    {
        $defaults = $this->getDefaultConfig();
        $saved_settings = get_option('perfbase_settings', []);

        if (!is_array($saved_settings)) {
            $saved_settings = [];
        }

        // wp_parse_args merges saved settings over defaults
        $config = wp_parse_args($saved_settings, $defaults);

        $config = $this->migrateLegacyConfig($config);

        // wp-config.php constants override everything (highest priority)
        $config = $this->applyConstants($config);
        $config['profile_http_status_codes'] = self::normalizeHttpStatusCodes(
            $config['profile_http_status_codes'] ?? self::getDefaultHttpStatusCodes(),
            $defaults['profile_http_status_codes']
        );

        return $config;
    }

    /**
     * Normalize configured HTTP status codes into a unique ascending integer list.
     *
     * Supports arrays, comma/newline-separated strings, and ranges like 200-299.
     *
     * @param mixed $value
     * @param array<int, int> $fallback
     * @return array<int, int>
     */
    public static function normalizeHttpStatusCodes($value, array $fallback = []): array
    {
        if ($value === null) {
            return $fallback;
        }

        if (is_array($value)) {
            $tokens = $value;
        } elseif (is_int($value)) {
            $tokens = [$value];
        } elseif (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            $trimmed = trim($trimmed, '[]');
            $tokens = preg_split('/[\r\n,]+/', $trimmed);
            if (!is_array($tokens)) {
                return $fallback;
            }
        } else {
            return $fallback;
        }

        $statusCodes = [];

        foreach ($tokens as $token) {
            if (is_int($token)) {
                if ($token >= 100 && $token <= 599) {
                    $statusCodes[] = $token;
                }
                continue;
            }

            if (!is_string($token)) {
                continue;
            }

            $token = trim($token);
            if ($token === '') {
                continue;
            }

            if (preg_match('/^(\d{3})\s*-\s*(\d{3})$/', $token, $matches) === 1) {
                $start = (int) $matches[1];
                $end = (int) $matches[2];

                if ($start >= 100 && $end <= 599 && $start <= $end) {
                    foreach (range($start, $end) as $statusCode) {
                        $statusCodes[] = $statusCode;
                    }
                }

                continue;
            }

            if (ctype_digit($token)) {
                $statusCode = (int) $token;
                if ($statusCode >= 100 && $statusCode <= 599) {
                    $statusCodes[] = $statusCode;
                }
            }
        }

        $statusCodes = array_values(array_unique($statusCodes));
        sort($statusCodes);

        return $statusCodes;
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
            'PERFBASE_PROFILE_HTTP_STATUS_CODES' => 'profile_http_status_codes',
        ];

        foreach ($constantMap as $constant => $key) {
            if (defined($constant)) {
                $config[$key] = constant($constant);
            }
        }

        return $config;
    }

    /**
     * Migrate legacy saved config keys into the canonical runtime shape.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function migrateLegacyConfig(array $config): array
    {
        $include = isset($config['include']) && is_array($config['include'])
            ? $config['include']
            : [];
        $exclude = isset($config['exclude']) && is_array($config['exclude'])
            ? $config['exclude']
            : [];

        foreach (['http', 'ajax', 'cron', 'cli'] as $context) {
            if (!isset($include[$context]) || !is_array($include[$context])) {
                $include[$context] = $this->getDefaultConfig()['include'][$context];
            }

            if (!isset($exclude[$context]) || !is_array($exclude[$context])) {
                $exclude[$context] = $this->getDefaultConfig()['exclude'][$context];
            }
        }

        if (!empty($config['excluded_paths']) && is_array($config['excluded_paths'])) {
            foreach ($config['excluded_paths'] as $path) {
                if (is_string($path) && $path !== '' && !in_array($path, $exclude['http'], true)) {
                    $exclude['http'][] = $path;
                }
            }
        }

        $config['include'] = $include;
        $config['exclude'] = $exclude;

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
            'profile_http_status_codes' => self::getDefaultHttpStatusCodes(),
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
        if ($flags < 0 || ($flags & ~FeatureFlags::ValidFlagsMask) !== 0) {
            $errors['flags'] = 'Invalid flags value';
        }

        return $errors;
    }
}
