<?php

namespace Perfbase\WordPress\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Error handling trait for Perfbase WordPress components.
 *
 * In debug mode, exceptions are re-thrown to surface issues during development.
 * In production mode, errors are logged (if enabled) and silenced so profiling
 * never disrupts the site.
 *
 * @phpstan-type PerfbaseConfig array{debug?: bool, log_errors?: bool}
 */
trait ErrorHandler
{
    /**
     * Handle a profiling error according to the current config.
     *
     * @param \Throwable $e
     * @param array<string, mixed> $config Plugin config array
     * @param string $context Description of where the error occurred
     * @return void
     */
    protected function handleProfilingError(\Throwable $e, array $config, string $context = ''): void
    {
        if (!empty($config['debug'])) {
            throw $e;
        }

        if ($config['log_errors'] ?? true) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Optional site-owner diagnostics controlled by the log_errors setting.
            error_log(sprintf(
                'Perfbase profiling error in %s: %s',
                $context ?: 'unknown',
                $e->getMessage()
            ));
        }
    }
}
