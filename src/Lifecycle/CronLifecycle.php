<?php

namespace Perfbase\WordPress\Lifecycle;

use Perfbase\WordPress\PerfbasePlugin;
use Perfbase\WordPress\Support\FilterMatcher;

/**
 * Lifecycle class for WordPress cron job requests.
 */
class CronLifecycle extends AbstractWordPressProfiler
{
    public function __construct(PerfbasePlugin $plugin)
    {
        parent::__construct('cron', $plugin);
    }

    protected function shouldProfile(): bool
    {
        if (empty($this->config['profile_cron'])) {
            return false;
        }

        return FilterMatcher::passesFilters(
            ['cron.execution'],
            $this->config['include'] ?? [],
            $this->config['exclude'] ?? [],
            'cron'
        );
    }

    protected function setDefaultAttributes(): void
    {
        parent::setDefaultAttributes();

        $this->setAttributes([
            'source' => 'cron',
            'action' => 'cron.execution',
        ]);
    }
}
