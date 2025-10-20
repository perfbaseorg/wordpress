<?php

namespace Perfbase\WordPress\Helpers;

/**
 * Handles request sampling decisions
 */
class SamplingStrategy
{
    /**
     * Check if the current request should be sampled
     *
     * @param float $sampleRate
     * @return bool
     */
    public function shouldSample(float $sampleRate): bool
    {
        if ($sampleRate >= 1.0) {
            return true;
        }

        if ($sampleRate <= 0.0) {
            return false;
        }

        return (mt_rand() / mt_getrandmax()) < $sampleRate;
    }

    /**
     * Get sampling decision based on request characteristics
     *
     * @param array $config
     * @param array $context
     * @return bool
     */
    public function getSamplingDecision(array $config, array $context = []): bool
    {
        $baseSampleRate = (float) $config['sample_rate'];

        // For now, just use the base sample rate
        // In the future, we could implement more sophisticated sampling
        // based on user roles, request types, etc.
        return $this->shouldSample($baseSampleRate);
    }

    /**
     * Get adaptive sampling rate based on system conditions
     *
     * @param float $baseSampleRate
     * @param array $systemMetrics
     * @return float
     */
    public function getAdaptiveSampleRate(float $baseSampleRate, array $systemMetrics = []): float
    {
        // For future implementation - could adjust sample rate based on:
        // - Memory usage
        // - CPU load
        // - Request volume
        // - Error rates

        return $baseSampleRate;
    }

    /**
     * Check if a request should be force-sampled (e.g., for debugging)
     *
     * @param array $context
     * @return bool
     */
    public function shouldForceSample(array $context = []): bool
    {
        // Force sample if debug parameter is present
        if (isset($_GET['perfbase_debug']) && current_user_can('manage_options')) {
            return true;
        }

        // Force sample for admin users in development
        if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
            // Could add additional logic here
        }

        return false;
    }
}