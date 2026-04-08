<?php

namespace Perfbase\WordPress\Lifecycle;

use Perfbase\WordPress\PerfbasePlugin;
use Perfbase\WordPress\Support\FilterMatcher;

/**
 * Lifecycle class for WordPress AJAX requests.
 */
class AjaxRequestLifecycle extends AbstractWordPressProfiler
{
    /** @var string */
    private string $action;

    public function __construct(string $action, PerfbasePlugin $plugin)
    {
        parent::__construct("ajax.{$action}", $plugin);
        $this->action = $action;
    }

    protected function shouldProfile(): bool
    {
        if (empty($this->config['profile_ajax'])) {
            return false;
        }

        return FilterMatcher::passesFilters(
            [$this->action],
            $this->config['include'] ?? [],
            $this->config['exclude'] ?? [],
            'ajax'
        );
    }

    protected function setDefaultAttributes(): void
    {
        parent::setDefaultAttributes();

        $this->setAttributes([
            'source' => 'ajax',
            'action' => "ajax.{$this->action}",
            'ajax.action' => $this->action,
        ]);
    }
}
