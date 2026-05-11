<?php

namespace Perfbase\WordPress\Lifecycle;

use Perfbase\WordPress\Helpers\RequestContext;
use Perfbase\WordPress\PerfbasePlugin;
use Perfbase\WordPress\Support\FilterMatcher;

/**
 * Lifecycle class for WordPress AJAX requests.
 */
class AjaxRequestLifecycle extends AbstractWordPressProfiler
{
    /** @var string */
    private string $action;

    /** @var RequestContext|null */
    private ?RequestContext $requestContext;

    public function __construct(string $action, PerfbasePlugin $plugin, ?RequestContext $requestContext = null)
    {
        $this->action = self::normalizeAction($action);
        $this->requestContext = $requestContext;
        parent::__construct('ajax', $plugin);
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
     * Add final request attributes before submission.
     *
     * @return void
     */
    public function addFinalAttributes(): void
    {
        if (!$this->requestContext) {
            return;
        }

        $this->setFinalAttributes($this->requestContext->getFinalAttributes());
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
