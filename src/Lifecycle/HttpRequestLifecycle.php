<?php

namespace Perfbase\WordPress\Lifecycle;

use Perfbase\WordPress\Helpers\RequestContext;
use Perfbase\WordPress\PerfbasePlugin;

/**
 * Lifecycle class for standard WordPress HTTP requests (frontend + admin).
 */
class HttpRequestLifecycle extends AbstractWordPressProfiler
{
    /** @var RequestContext */
    private RequestContext $requestContext;

    public function __construct(PerfbasePlugin $plugin, RequestContext $requestContext)
    {
        parent::__construct($requestContext->getSpanName(), $plugin);
        $this->requestContext = $requestContext;
    }

    protected function shouldProfile(): bool
    {
        if (!$this->requestContext->shouldProfileRequest($this->config)) {
            return false;
        }

        if ($this->perfbase && !$this->perfbase->isExtensionAvailable()) {
            return false;
        }

        return true;
    }

    protected function setDefaultAttributes(): void
    {
        parent::setDefaultAttributes();

        // RequestContext provides all HTTP-specific attributes:
        // action, user_ip, user_agent, hostname, environment, http_method, http_url,
        // app_version, php_version, wordpress.version, perfbase.version, user_id
        $this->setAttributes($this->requestContext->getRequestAttributes());

        $this->setAttribute('source', 'http');
    }

    /**
     * Add WordPress context attributes (called on template_redirect).
     *
     * @return void
     */
    public function addWordPressContext(): void
    {
        $this->setAttributes($this->requestContext->getTemplateContext());
        $this->setAttributes($this->requestContext->getWordPressContext());
    }

    /**
     * Add final attributes before submission (called on shutdown).
     *
     * @return void
     */
    public function addFinalAttributes(): void
    {
        $this->setAttributes($this->requestContext->getFinalAttributes());
    }
}
