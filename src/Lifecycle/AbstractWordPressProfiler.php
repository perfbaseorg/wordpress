<?php

namespace Perfbase\WordPress\Lifecycle;

use Perfbase\SDK\Exception\PerfbaseException;
use Perfbase\SDK\Perfbase;
use Perfbase\WordPress\PerfbasePlugin;
use Perfbase\WordPress\Support\ErrorHandler;

/**
 * Base lifecycle class for WordPress trace profiling.
 *
 * WordPress adaptation of the Laravel AbstractProfiler pattern.
 * Each concrete class represents one request context (HTTP, AJAX, cron)
 * with its own shouldProfile() logic and attributes.
 */
abstract class AbstractWordPressProfiler
{
    use ErrorHandler;

    /** @var Perfbase|null */
    protected ?Perfbase $perfbase;

    /** @var array<string, string> */
    protected array $attributes = [];

    /** @var string */
    protected string $spanName;

    /** @var array<string, mixed> */
    protected array $config;

    /**
     * @param string $spanName
     * @param PerfbasePlugin $plugin
     */
    public function __construct(string $spanName, PerfbasePlugin $plugin)
    {
        $this->spanName = $spanName;
        $this->perfbase = $plugin->get_perfbase();
        $this->config = $plugin->get_config();
    }

    /**
     * Start profiling if conditions are met.
     *
     * @return void
     */
    public function startProfiling(): void
    {
        if (!$this->perfbase) {
            return;
        }

        try {
            if (!$this->passesSampleRateCheck() || !$this->shouldProfile()) {
                return;
            }

            $this->perfbase->startTraceSpan($this->spanName);
            $this->setDefaultAttributes();
        } catch (\Throwable $e) {
            $this->handleProfilingError($e, $this->config, 'start');
        }
    }

    /**
     * Stop profiling and submit the trace.
     *
     * @return void
     */
    public function stopProfiling(): void
    {
        if (!$this->perfbase) {
            return;
        }

        try {
            // Apply accumulated attributes
            foreach ($this->attributes as $key => $value) {
                $this->perfbase->setAttribute($key, $value);
            }

            if (!$this->perfbase->stopTraceSpan($this->spanName)) {
                return;
            }

            $result = $this->perfbase->submitTrace();

            if (!$result->isSuccess()) {
                $this->handleProfilingError(
                    new PerfbaseException(sprintf(
                        'Trace submission failed (%s): %s',
                        $result->getStatus(),
                        $result->getMessage()
                    )),
                    $this->config,
                    'submit'
                );
            }
        } catch (\Throwable $e) {
            $this->handleProfilingError($e, $this->config, 'stop');
        }
    }

    /**
     * Set an attribute for the current trace.
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    public function setAttribute(string $key, string $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Set multiple attributes at once.
     *
     * @param array<string, string> $attributes
     * @return void
     */
    public function setAttributes(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, (string) $value);
        }
    }

    /**
     * Set default attributes common to all WordPress contexts.
     *
     * Subclasses should call parent and add context-specific attributes.
     *
     * @return void
     */
    protected function setDefaultAttributes(): void
    {
        $hostname = gethostname();

        $this->setAttributes([
            'hostname' => is_string($hostname) ? $hostname : '',
            'php_version' => phpversion() ?: '',
        ]);
    }

    /**
     * Check if the sample rate allows this request to be profiled.
     *
     * @return bool
     */
    protected function passesSampleRateCheck(): bool
    {
        $sampleRate = $this->config['sample_rate'] ?? 0.1;

        if (!is_numeric($sampleRate) || $sampleRate < 0 || $sampleRate > 1) {
            return false;
        }

        if ((float) $sampleRate >= 1.0) {
            return true;
        }

        if ((float) $sampleRate <= 0.0) {
            return false;
        }

        return (mt_rand() / mt_getrandmax()) <= (float) $sampleRate;
    }

    /**
     * Get the span name.
     *
     * @return string
     */
    public function getSpanName(): string
    {
        return $this->spanName;
    }

    /**
     * Determine if the current context should be profiled.
     *
     * @return bool
     */
    abstract protected function shouldProfile(): bool;
}
