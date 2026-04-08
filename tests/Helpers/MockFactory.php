<?php

namespace Perfbase\WordPress\Tests\Helpers;

use Mockery;
use Perfbase\SDK\Config;
use Perfbase\SDK\Extension\ExtensionInterface;
use Perfbase\SDK\Perfbase;
use Perfbase\SDK\SubmitResult;

/**
 * Factory for creating test mocks
 */
class MockFactory
{
    /**
     * Create a mock Perfbase SDK instance
     *
     * @param array $methods
     * @return Mockery\MockInterface|Perfbase
     */
    public static function createMockPerfbase(array $methods = []): Mockery\MockInterface
    {
        $mock = Mockery::mock(Perfbase::class);

        // Default method expectations
        $defaults = [
            'startTraceSpan' => true,
            'stopTraceSpan' => true,
            'setAttribute' => true,
            'submitTrace' => SubmitResult::success(),
            'reset' => true,
            'getTraceData' => '{}',
            'isExtensionAvailable' => true
        ];

        $methods = array_merge($defaults, $methods);

        foreach ($methods as $method => $returnValue) {
            if ($returnValue === true) {
                $mock->shouldReceive($method)->byDefault()->andReturn();
            } elseif ($returnValue === false) {
                $mock->shouldReceive($method)->never();
            } else {
                $mock->shouldReceive($method)->byDefault()->andReturn($returnValue);
            }
        }

        return $mock;
    }

    /**
     * Create a mock Perfbase Config instance
     *
     * @param array $configData
     * @return Mockery\MockInterface|Config
     */
    public static function createMockConfig(array $configData = []): Mockery\MockInterface
    {
        $mock = Mockery::mock(Config::class);

        $defaults = [
            'api_key' => 'test-api-key',
            'api_url' => 'https://ingress.perfbase.cloud',
            'flags' => 0,
            'timeout' => 10,
            'proxy' => null
        ];

        $configData = array_merge($defaults, $configData);

        foreach ($configData as $property => $value) {
            $mock->$property = $value;
        }

        $mock->shouldReceive('validate')->andReturn();

        return $mock;
    }

    /**
     * Create a mock Perfbase Extension instance
     *
     * @param bool $available
     * @return Mockery\MockInterface|ExtensionInterface
     */
    public static function createMockExtension(bool $available = true): Mockery\MockInterface
    {
        $mock = Mockery::mock(ExtensionInterface::class);

        $mock->shouldReceive('isAvailable')->andReturn($available);

        if ($available) {
            $mock->shouldReceive('startSpan')->andReturn();
            $mock->shouldReceive('stopSpan')->andReturn();
            $mock->shouldReceive('getSpanData')->andReturn('{"test": "data"}');
            $mock->shouldReceive('setAttribute')->andReturn();
            $mock->shouldReceive('reset')->andReturn();
        }

        return $mock;
    }

    /**
     * Create a complete mock setup for testing
     *
     * @param array $options
     * @return array
     */
    public static function createCompleteSetup(array $options = []): array
    {
        $defaults = [
            'extension_available' => true,
            'config_data' => [],
            'perfbase_methods' => []
        ];

        $options = array_merge($defaults, $options);

        $extension = self::createMockExtension($options['extension_available']);
        $config = self::createMockConfig($options['config_data']);
        $perfbase = self::createMockPerfbase($options['perfbase_methods']);

        return [
            'extension' => $extension,
            'config' => $config,
            'perfbase' => $perfbase
        ];
    }

    /**
     * Create mock WordPress plugin configuration
     *
     * @param array $overrides
     * @return array
     */
    public static function createMockWordPressConfig(array $overrides = []): array
    {
        $defaults = [
            'enabled' => true,
            'api_key' => 'test-api-key',
            'api_url' => 'https://ingress.perfbase.cloud',
            'sample_rate' => 1.0,
            'flags' => 0,
            'timeout' => 10,
            'proxy' => '',
            'profile_admin' => false,
            'profile_ajax' => true,
            'profile_cron' => true,
            'profile_cli' => false,
            'include' => [
                'http' => ['*'],
                'ajax' => ['*'],
                'cron' => ['*'],
                'cli' => ['*'],
            ],
            'exclude' => [
                'http' => [
                    '/wp-content/uploads/*',
                    '/favicon.ico',
                ],
                'ajax' => [],
                'cron' => [],
                'cli' => [],
            ],
            'exclude_user_agents' => [
                'bot',
                'crawler',
                'spider'
            ],
            'debug' => false,
            'log_errors' => true
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create mock WooCommerce product
     *
     * @param array $data
     * @return Mockery\MockInterface
     */
    public static function createMockWooCommerceProduct(array $data = []): Mockery\MockInterface
    {
        $mock = Mockery::mock('WC_Product');

        $defaults = [
            'id' => 1,
            'type' => 'simple'
        ];

        $data = array_merge($defaults, $data);

        $mock->shouldReceive('get_id')->andReturn($data['id']);
        $mock->shouldReceive('get_type')->andReturn($data['type']);

        return $mock;
    }

    /**
     * Create mock WordPress job for queue testing
     *
     * @param array $payload
     * @return Mockery\MockInterface
     */
    public static function createMockJob(array $payload = []): Mockery\MockInterface
    {
        $mock = Mockery::mock('Illuminate\Queue\Jobs\Job');

        $defaultPayload = [
            'displayName' => 'TestJob',
            'job' => 'TestJob',
            'data' => [
                'commandName' => 'TestCommand'
            ]
        ];

        $payload = array_merge($defaultPayload, $payload);

        $mock->shouldReceive('payload')->andReturn($payload);
        $mock->shouldReceive('getQueue')->andReturn('default');

        return $mock;
    }

    /**
     * Create mock WordPress AJAX request
     *
     * @param string $action
     * @return void
     */
    public static function mockAjaxRequest(string $action = 'test_action'): void
    {
        $_REQUEST['action'] = $action;
        $_POST['action'] = $action;

        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }
    }

    /**
     * Create mock WordPress admin page context
     *
     * @param string $page
     * @return void
     */
    public static function mockAdminPage(string $page = 'perfbase-settings'): void
    {
        $_GET['page'] = $page;
        $GLOBALS['pagenow'] = 'options-general.php';
    }

    /**
     * Create mock HTTP request
     *
     * @param array $server
     * @return void
     */
    public static function mockHttpRequest(array $server = []): void
    {
        $defaults = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'example.com',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Test Browser',
            'REMOTE_ADDR' => '127.0.0.1'
        ];

        $_SERVER = array_merge($_SERVER ?? [], $defaults, $server);
    }

    /**
     * Reset all global state for clean testing
     */
    public static function resetGlobals(): void
    {
        // Reset superglobals
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        $_SERVER = [];
        $_SESSION = [];

        // Reset WordPress globals
        unset($GLOBALS['wp_query']);
        unset($GLOBALS['wpdb']);
        unset($GLOBALS['wp']);
        unset($GLOBALS['post']);
        unset($GLOBALS['current_user']);

        // Reset defined constants that might interfere
        $constants = ['DOING_AJAX', 'DOING_CRON', 'WP_CLI', 'WP_ADMIN'];
        foreach ($constants as $constant) {
            if (defined($constant)) {
                // We can't undefine constants, but we can track them
                // In real tests, these would be handled by test isolation
            }
        }
    }
}
