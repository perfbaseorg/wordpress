<?php

namespace Perfbase\WordPress\Lifecycle;

use Perfbase\WordPress\PerfbasePlugin;
use Perfbase\WordPress\Support\FilterMatcher;

/**
 * Lifecycle class for WP-CLI command requests.
 */
class CliLifecycle extends AbstractWordPressProfiler
{
    /** @var string */
    private string $command;

    public function __construct(string $command, PerfbasePlugin $plugin)
    {
        parent::__construct('cli', $plugin);
        $this->command = $command;
    }

    protected function shouldProfile(): bool
    {
        if (empty($this->config['profile_cli'])) {
            return false;
        }

        return FilterMatcher::passesFilters(
            [$this->command],
            $this->config['include'] ?? [],
            $this->config['exclude'] ?? [],
            'cli'
        );
    }

    protected function setDefaultAttributes(): void
    {
        parent::setDefaultAttributes();

        $this->setAttributes([
            'source' => 'cli',
            'action' => $this->command,
            'cli.command' => $this->command,
        ]);
    }
}
