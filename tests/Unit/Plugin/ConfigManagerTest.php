<?php

namespace Perfbase\WordPress\Tests\Unit\Plugin;

use Brain\Monkey\Functions;
use Perfbase\SDK\FeatureFlags;
use Perfbase\WordPress\Helpers\ConfigManager;
use Perfbase\WordPress\Tests\Helpers\BaseWordPressTest;
use Perfbase\WordPress\Tests\Helpers\TestData;

/**
 * Test ConfigManager class
 */
class ConfigManagerTest extends BaseWordPressTest
{
    private $config_manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config_manager = new ConfigManager();
    }

    public function testGetDefaultConfig()
    {
        $default_config = $this->config_manager->getDefaultConfig();

        $this->assertIsArray($default_config);
        $this->assertArrayHasKey('enabled', $default_config);
        $this->assertArrayHasKey('api_key', $default_config);
        $this->assertArrayHasKey('api_url', $default_config);
        $this->assertArrayHasKey('sample_rate', $default_config);
        $this->assertArrayHasKey('flags', $default_config);
        $this->assertArrayHasKey('timeout', $default_config);
        $this->assertArrayHasKey('proxy', $default_config);
        $this->assertArrayHasKey('profile_admin', $default_config);
        $this->assertArrayHasKey('profile_ajax', $default_config);
        $this->assertArrayHasKey('profile_cron', $default_config);
        $this->assertArrayHasKey('profile_cli', $default_config);
        $this->assertArrayHasKey('profile_http_status_codes', $default_config);
        $this->assertArrayHasKey('include', $default_config);
        $this->assertArrayHasKey('exclude', $default_config);
        $this->assertArrayHasKey('exclude_user_agents', $default_config);
        $this->assertArrayHasKey('debug', $default_config);
        $this->assertArrayHasKey('log_errors', $default_config);

        // Test default values
        $this->assertFalse($default_config['enabled']);
        $this->assertEquals('', $default_config['api_key']);
        $this->assertEquals('https://ingress.perfbase.cloud', $default_config['api_url']);
        $this->assertEquals(0.1, $default_config['sample_rate']);
        $this->assertEquals(FeatureFlags::DefaultFlags, $default_config['flags']);
        $this->assertEquals(10, $default_config['timeout']);
        $this->assertEquals('', $default_config['proxy']);
        $this->assertFalse($default_config['profile_admin']);
        $this->assertTrue($default_config['profile_ajax']);
        $this->assertTrue($default_config['profile_cron']);
        $this->assertFalse($default_config['profile_cli']);
        $this->assertEquals(array_merge(range(200, 299), range(500, 599)), $default_config['profile_http_status_codes']);
        $this->assertFalse($default_config['debug']);
        $this->assertTrue($default_config['log_errors']);
        $this->assertIsArray($default_config['include']);
        $this->assertIsArray($default_config['exclude']);
        $this->assertIsArray($default_config['exclude_user_agents']);
        $this->assertEquals(['*'], $default_config['include']['http']);
        $this->assertContains('/favicon.ico', $default_config['exclude']['http']);
    }

    public function testGetConfigWithDefaults()
    {
        Functions\when('get_option')->justReturn([]);

        $config = $this->config_manager->getConfig();

        $this->assertIsArray($config);
        $this->assertFalse($config['enabled']);
        $this->assertEquals('', $config['api_key']);
    }

    public function testGetConfigWithSavedSettings()
    {
        $saved_settings = [
            'enabled' => true,
            'api_key' => 'test-key-123',
            'sample_rate' => 0.5
        ];

        Functions\when('get_option')
            ->justReturn($saved_settings);

        $config = $this->config_manager->getConfig();

        $this->assertTrue($config['enabled']);
        $this->assertEquals('test-key-123', $config['api_key']);
        $this->assertEquals(0.5, $config['sample_rate']);
        // Should still have defaults for other values
        $this->assertEquals('https://ingress.perfbase.cloud', $config['api_url']);
    }

    public function testGetConfigNormalizesProfileHttpStatusCodes()
    {
        Functions\when('get_option')->justReturn([
            'profile_http_status_codes' => '200-202, 404',
        ]);

        $config = $this->config_manager->getConfig();

        $this->assertEquals([200, 201, 202, 404], $config['profile_http_status_codes']);
    }

    public function testGetConfigMigratesLegacyExcludedPaths()
    {
        Functions\when('get_option')->justReturn([
            'excluded_paths' => ['/legacy-one', '/legacy-two'],
        ]);

        $config = $this->config_manager->getConfig();

        $this->assertContains('/legacy-one', $config['exclude']['http']);
        $this->assertContains('/legacy-two', $config['exclude']['http']);
    }

    public function testUpdateConfig()
    {
        $new_config = TestData::getValidConfig();

        Functions\when('update_option')
            ->justReturn(true);

        $result = $this->config_manager->updateConfig($new_config);

        $this->assertTrue($result);
    }

    public function testUpdateConfigFailure()
    {
        $new_config = TestData::getValidConfig();

        Functions\when('update_option')
            ->justReturn(false);

        $result = $this->config_manager->updateConfig($new_config);

        $this->assertFalse($result);
    }

    public function testIsEnabledWithValidConfig()
    {
        $config = TestData::getValidConfig();

        $result = $this->config_manager->isEnabled($config);

        $this->assertTrue($result);
    }

    public function testIsEnabledWithDisabledConfig()
    {
        $config = TestData::getValidConfig();
        $config['enabled'] = false;

        $result = $this->config_manager->isEnabled($config);

        $this->assertFalse($result);
    }

    public function testIsEnabledWithMissingApiKey()
    {
        $config = TestData::getValidConfig();
        $config['api_key'] = '';

        $result = $this->config_manager->isEnabled($config);

        $this->assertFalse($result);
    }

    public function testValidateConfigValid()
    {
        $config = TestData::getValidConfig();

        $errors = $this->config_manager->validateConfig($config);

        $this->assertEmpty($errors);
    }

    public function testValidateConfigMissingApiKey()
    {
        $config = TestData::getValidConfig();
        $config['api_key'] = '';

        $errors = $this->config_manager->validateConfig($config);

        $this->assertArrayHasKey('api_key', $errors);
        $this->assertEquals('API key is required', $errors['api_key']);
    }

    public function testValidateConfigInvalidApiUrl()
    {
        $config = TestData::getValidConfig();
        $config['api_url'] = 'not-a-url';

        $errors = $this->config_manager->validateConfig($config);

        $this->assertArrayHasKey('api_url', $errors);
        $this->assertEquals('Invalid API URL', $errors['api_url']);
    }

    public function testValidateConfigInvalidSampleRateNegative()
    {
        $config = TestData::getValidConfig();
        $config['sample_rate'] = -0.1;

        $errors = $this->config_manager->validateConfig($config);

        $this->assertArrayHasKey('sample_rate', $errors);
        $this->assertEquals('Sample rate must be between 0.0 and 1.0', $errors['sample_rate']);
    }

    public function testValidateConfigInvalidSampleRateTooHigh()
    {
        $config = TestData::getValidConfig();
        $config['sample_rate'] = 1.5;

        $errors = $this->config_manager->validateConfig($config);

        $this->assertArrayHasKey('sample_rate', $errors);
        $this->assertEquals('Sample rate must be between 0.0 and 1.0', $errors['sample_rate']);
    }

    public function testValidateConfigInvalidTimeout()
    {
        $config = TestData::getValidConfig();
        $config['timeout'] = 0;

        $errors = $this->config_manager->validateConfig($config);

        $this->assertArrayHasKey('timeout', $errors);
        $this->assertEquals('Timeout must be greater than 0', $errors['timeout']);
    }

    public function testValidateConfigInvalidFlags()
    {
        $config = TestData::getValidConfig();
        $config['flags'] = -1;

        $errors = $this->config_manager->validateConfig($config);

        $this->assertArrayHasKey('flags', $errors);
        $this->assertEquals('Invalid flags value', $errors['flags']);
    }

    public function testValidateConfigInvalidFlagsTooHigh()
    {
        $config = TestData::getValidConfig();
        $config['flags'] = FeatureFlags::AllFlags + 1;

        $errors = $this->config_manager->validateConfig($config);

        $this->assertArrayHasKey('flags', $errors);
        $this->assertEquals('Invalid flags value', $errors['flags']);
    }

    public function testValidateConfigMultipleErrors()
    {
        $config = [
            'enabled' => true,
            'api_key' => '',
            'api_url' => 'invalid-url',
            'sample_rate' => 2.0,
            'timeout' => 0,
            'flags' => -1
        ];

        $errors = $this->config_manager->validateConfig($config);

        $this->assertCount(5, $errors);
        $this->assertArrayHasKey('api_key', $errors);
        $this->assertArrayHasKey('api_url', $errors);
        $this->assertArrayHasKey('sample_rate', $errors);
        $this->assertArrayHasKey('timeout', $errors);
        $this->assertArrayHasKey('flags', $errors);
    }

    public function testValidateConfigWithValidSampleRateBoundaries()
    {
        $config = TestData::getValidConfig();

        // Test 0.0 (valid)
        $config['sample_rate'] = 0.0;
        $errors = $this->config_manager->validateConfig($config);
        $this->assertArrayNotHasKey('sample_rate', $errors);

        // Test 1.0 (valid)
        $config['sample_rate'] = 1.0;
        $errors = $this->config_manager->validateConfig($config);
        $this->assertArrayNotHasKey('sample_rate', $errors);
    }

    public function testApplyConstantsOverridesConfig()
    {
        // PERFBASE_ENABLED is already potentially defined, so test with a fresh approach.
        // applyConstants() is private, so we test through getConfig().
        // Define a constant that isn't already defined.
        if (!defined('PERFBASE_TIMEOUT')) {
            define('PERFBASE_TIMEOUT', 42);
        }

        Functions\when('get_option')->justReturn([]);

        $config = $this->config_manager->getConfig();

        // The constant should override the default
        $this->assertEquals(42, $config['timeout']);
    }

    public function testApplyConstantsOverridesProfileHttpStatusCodes()
    {
        if (!defined('PERFBASE_PROFILE_HTTP_STATUS_CODES')) {
            define('PERFBASE_PROFILE_HTTP_STATUS_CODES', '204, 400-401');
        }

        Functions\when('get_option')->justReturn([]);

        $config = $this->config_manager->getConfig();

        $this->assertEquals([204, 400, 401], $config['profile_http_status_codes']);
    }

    public function testGetDefaultConfigHasNewKeys()
    {
        $defaults = $this->config_manager->getDefaultConfig();

        // Verify new config shape
        $this->assertArrayHasKey('include', $defaults);
        $this->assertArrayHasKey('exclude', $defaults);
        $this->assertArrayHasKey('exclude_user_agents', $defaults);
        $this->assertArrayHasKey('debug', $defaults);
        $this->assertArrayHasKey('log_errors', $defaults);

        // Verify nested structure
        $this->assertArrayHasKey('http', $defaults['include']);
        $this->assertArrayHasKey('ajax', $defaults['include']);
        $this->assertArrayHasKey('cron', $defaults['include']);
        $this->assertArrayHasKey('cli', $defaults['include']);

        $this->assertArrayHasKey('http', $defaults['exclude']);
        $this->assertContains('/wp-content/uploads/*', $defaults['exclude']['http']);
        $this->assertContains('/favicon.ico', $defaults['exclude']['http']);

        // Verify old keys no longer present
        $this->assertArrayNotHasKey('excluded_paths', $defaults);
        $this->assertArrayNotHasKey('excluded_user_agents', $defaults);
    }

    public function testValidateConfigWithValidFlagsBoundaries()
    {
        $config = TestData::getValidConfig();

        // Test 0 (valid)
        $config['flags'] = 0;
        $errors = $this->config_manager->validateConfig($config);
        $this->assertArrayNotHasKey('flags', $errors);

        // Test AllFlags (valid)
        $config['flags'] = FeatureFlags::AllFlags;
        $errors = $this->config_manager->validateConfig($config);
        $this->assertArrayNotHasKey('flags', $errors);
    }
}
