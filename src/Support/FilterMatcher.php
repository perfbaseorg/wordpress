<?php

namespace Perfbase\WordPress\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared filter matching for include/exclude pattern lists.
 *
 * Supports wildcards (*, .*), regex (/pattern/), and glob patterns via fnmatch().
 */
class FilterMatcher
{
    /**
     * Check if any component matches any filter pattern.
     *
     * @param array<string> $components Values to test
     * @param array<string> $filters Patterns to match against
     * @return bool
     */
    public static function matches(array $components, array $filters): bool
    {
        foreach ($filters as $filter) {
            if ($filter === '*' || $filter === '.*') {
                return true;
            }

            // Regex patterns enclosed in forward slashes
            if (preg_match('/^\/.*\/$/', $filter)) {
                if (!self::isValidRegex($filter)) {
                    continue;
                }

                foreach ($components as $component) {
                    if (@preg_match($filter, $component) === 1) {
                        return true;
                    }
                }
                continue;
            }

            // Glob matching via fnmatch
            foreach ($components as $component) {
                if (fnmatch($filter, $component)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Validate a regex filter without emitting warnings into request-time logs.
     *
     * @param string $filter
     * @return bool
     */
    private static function isValidRegex(string $filter): bool
    {
        return @preg_match($filter, '') !== false;
    }

    /**
     * Check if a value passes include/exclude filters from a config array.
     *
     * @param array<string> $components Values to test
     * @param array<string, array<string>> $includeConfig The include config (e.g. ['http' => ['*']])
     * @param array<string, array<string>> $excludeConfig The exclude config
     * @param string $key Config key (e.g. 'http', 'ajax', 'cron')
     * @return bool
     */
    public static function passesFilters(
        array $components,
        array $includeConfig,
        array $excludeConfig,
        string $key
    ): bool {
        $includes = $includeConfig[$key] ?? [];
        if (empty($includes)) {
            return false;
        }

        if (!self::matches($components, $includes)) {
            return false;
        }

        $excludes = $excludeConfig[$key] ?? [];
        if (!empty($excludes) && self::matches($components, $excludes)) {
            return false;
        }

        return true;
    }
}
