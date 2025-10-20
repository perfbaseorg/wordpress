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
        $this->assertArrayHasKey('excluded_paths', $default_config);
        $this->assertArrayHasKey('excluded_user_agents', $default_config);

        // Test default values
        $this->assertFalse($default_config['enabled']);
        $this->assertEquals('', $default_config['api_key']);
        $this->assertEquals('https://receiver.perfbase.com', $default_config['api_url']);
        $this->assertEquals(0.1, $default_config['sample_rate']);
        $this->assertEquals(FeatureFlags::DefaultFlags, $default_config['flags']);
        $this->assertEquals(10, $default_config['timeout']);
        $this->assertEquals('', $default_config['proxy']);
        $this->assertFalse($default_config['profile_admin']);
        $this->assertTrue($default_config['profile_ajax']);
        $this->assertTrue($default_config['profile_cron']);
        $this->assertFalse($default_config['profile_cli']);
        $this->assertIsArray($default_config['excluded_paths']);
        $this->assertIsArray($default_config['excluded_user_agents']);
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
        $this->assertEquals('https://receiver.perfbase.com', $config['api_url']);
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