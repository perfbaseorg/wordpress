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
        $this->action = self::normalizeAction($action);
        parent::__construct("ajax.{$this->action}", $plugin);
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

    /**
     * Normalize AJAX action names for low-cardinality tracing.
     *
     * @param string $action
     * @return string
     */
    private static function normalizeAction(string $action): string
    {
        $normalized = trim($action);
        if ($normalized === '') {
            return 'unknown';
        }

        $normalized = sanitize_key($normalized);
        if ($normalized === '') {
            return 'unknown';
        }

        return substr($normalized, 0, 64);
    }
}
