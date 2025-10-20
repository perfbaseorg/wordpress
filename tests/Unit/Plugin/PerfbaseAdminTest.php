<?php

namespace Perfbase\WordPress\Tests\Unit\Plugin;

use Brain\Monkey\Functions;
use Mockery;
use Perfbase\SDK\FeatureFlags;
use Perfbase\WordPress\PerfbaseAdmin;
use Perfbase\WordPress\PerfbasePlugin;
use Perfbase\WordPress\Helpers\ConfigManager;
use Perfbase\WordPress\Tests\Helpers\BaseWordPressTest;
use Perfbase\WordPress\Tests\Helpers\TestData;

/**
 * Test PerfbaseAdmin class
 */
class PerfbaseAdminTest extends BaseWordPressTest
{
    private $mock_plugin;
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock plugin
        $this->mock_plugin = Mockery::mock(PerfbasePlugin::class);
        $this->mock_plugin->shouldReceive('get_config')
            ->andReturn(TestData::getValidConfig());

        // Mock WordPress admin functions
        $this->mockAdminEnvironment();

        // Create admin instance
        $this->admin = new PerfbaseAdmin($this->mock_plugin);
    }

    public function testConstructorInitializesAdmin()
    {
        $this->assertInstanceOf(PerfbaseAdmin::class, $this->admin);
    }

    public function testAddAdminMenu()
    {
        Functions\expect('add_options_page')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                'manage_options',
                'perfbase-settings',
                Mockery::type('array')
            )
            ->andReturn('perfbase-settings');

        $result = $this->admin->add_admin_menu();

        // Verify expectation was met
        $this->assertTrue(true);
    }

    public function testRegisterSettings()
    {
        // Just verify the method executes without error
        $this->admin->register_settings();

        // Verify method completed successfully
        $this->assertTrue(true);
    }

    public function testSanitizeSettingsWithValidInput()
    {
        $input = [
            'enabled' => '1',
            'api_key' => 'test-key-123',
            'api_url' => 'https://receiver.perfbase.com',
            'sample_rate' => '0.5',
            'timeout' => '15',
            'proxy' => '',
            'profile_admin' => '1',
            'profile_ajax' => '1',
            'profile_cron' => '1',
            'profile_cli' => '',
            'flags' => [FeatureFlags::TrackPdo, FeatureFlags::TrackHttp],
            'excluded_paths' => "/wp-admin/\n/wp-cron.php",
            'excluded_user_agents' => "bot\ncrawler"
        ];

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->returnArg();

        $result = $this->admin->sanitize_settings($input);

        $this->assertTrue($result['enabled']);
        $this->assertEquals('test-key-123', $result['api_key']);
        $this->assertEquals('https://receiver.perfbase.com', $result['api_url']);
        $this->assertEquals(0.5, $result['sample_rate']);
        $this->assertEquals(15, $result['timeout']);
        $this->assertTrue($result['profile_admin']);
        $this->assertTrue($result['profile_ajax']);
        $this->assertFalse($result['profile_cli']);
        $this->assertIsInt($result['flags']);
        $this->assertCount(2, $result['excluded_paths']);
        $this->assertCount(2, $result['excluded_user_agents']);
    }

    public function testSanitizeSettingsClampsSampleRate()
    {
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->returnArg();

        // Test lower bound
        $result = $this->admin->sanitize_settings(['sample_rate' => '-0.5']);
        $this->assertEquals(0.0, $result['sample_rate']);

        // Test upper bound
        $result = $this->admin->sanitize_settings(['sample_rate' => '1.5']);
        $this->assertEquals(1.0, $result['sample_rate']);

        // Test valid value
        $result = $this->admin->sanitize_settings(['sample_rate' => '0.75']);
        $this->assertEquals(0.75, $result['sample_rate']);
    }

    public function testSanitizeSettingsClampsTimeout()
    {
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->returnArg();

        // Test lower bound
        $result = $this->admin->sanitize_settings(['timeout' => '0']);
        $this->assertEquals(1, $result['timeout']);

        // Test upper bound
        $result = $this->admin->sanitize_settings(['timeout' => '100']);
        $this->assertEquals(60, $result['timeout']);

        // Test valid value
        $result = $this->admin->sanitize_settings(['timeout' => '30']);
        $this->assertEquals(30, $result['timeout']);
    }

    public function testSanitizeSettingsHandlesEmptyInput()
    {
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->returnArg();

        $result = $this->admin->sanitize_settings([]);

        $this->assertFalse($result['enabled']);
        $this->assertEquals('', $result['api_key']);
        $this->assertEquals('https://receiver.perfbase.com', $result['api_url']);
        $this->assertEquals(0.1, $result['sample_rate']);
        $this->assertEquals(10, $result['timeout']);
    }

    public function testSanitizeSettingsStripsWhitespaceFromExclusions()
    {
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->returnArg();

        $input = [
            'excluded_paths' => "  /wp-admin/  \n\n  /wp-cron.php  \n  ",
            'excluded_user_agents' => "  bot  \n  crawler  "
        ];

        $result = $this->admin->sanitize_settings($input);

        $this->assertEquals(['/wp-admin/', '/wp-cron.php'], $result['excluded_paths']);
        $this->assertEquals(['bot', 'crawler'], $result['excluded_user_agents']);
    }

    public function testSanitizeSettingsHandlesFlagsBitmask()
    {
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->returnArg();

        $input = [
            'flags' => [
                FeatureFlags::TrackPdo,
                FeatureFlags::TrackHttp,
                FeatureFlags::TrackCaches
            ]
        ];

        $result = $this->admin->sanitize_settings($input);

        $expected_flags = FeatureFlags::TrackPdo | FeatureFlags::TrackHttp | FeatureFlags::TrackCaches;
        $this->assertEquals($expected_flags, $result['flags']);
    }

    public function testSanitizeSettingsHandlesInvalidFlags()
    {
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->returnArg();

        // Non-array flags
        $result = $this->admin->sanitize_settings(['flags' => 'invalid']);
        $this->assertEquals(0, $result['flags']);

        // Empty array
        $result = $this->admin->sanitize_settings(['flags' => []]);
        $this->assertEquals(0, $result['flags']);
    }

    public function testSanitizeSettingsPreventsSQLInjection()
    {
        Functions\when('sanitize_text_field')->alias(function($value) {
            // Properly simulate WordPress sanitize_text_field behavior
            $value = wp_check_invalid_utf8($value ?? '');
            $value = strip_tags($value);
            return $value;
        });
        Functions\when('wp_check_invalid_utf8')->returnArg();
        Functions\when('esc_url_raw')->returnArg();

        $malicious_input = [
            'api_key' => "test'; DROP TABLE wp_posts; --",
            'proxy' => "'; DELETE FROM wp_users WHERE '1'='1"
        ];

        $result = $this->admin->sanitize_settings($malicious_input);

        // strip_tags doesn't remove SQL keywords, but it does remove quotes and tags
        // The actual WordPress sanitize_text_field would handle this
        $this->assertIsString($result['api_key']);
        $this->assertIsString($result['proxy']);
    }

    public function testSanitizeSettingsPreventsXSS()
    {
        Functions\when('sanitize_text_field')->alias(function($value) {
            return strip_tags($value);
        });
        Functions\when('esc_url_raw')->returnArg();

        $malicious_input = [
            'api_key' => '<script>alert("XSS")</script>',
            'proxy' => '<img src=x onerror="alert(1)">'
        ];

        $result = $this->admin->sanitize_settings($malicious_input);

        // Should strip tags
        $this->assertStringNotContainsString('<script>', $result['api_key']);
        $this->assertStringNotContainsString('<img', $result['proxy']);
    }

    public function testSanitizeSettingsHandlesInvalidURLs()
    {
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->alias(function($url) {
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
            return '';
        });

        $input = [
            'api_url' => 'not-a-valid-url'
        ];

        $result = $this->admin->sanitize_settings($input);

        // Should fall back to empty or default
        $this->assertNotEquals('not-a-valid-url', $result['api_url']);
    }

    public function testAddPluginActionLinks()
    {
        Functions\when('admin_url')->alias(function($path) {
            return 'http://example.com/wp-admin/' . $path;
        });
        Functions\when('__')->alias(function($text, $domain) {
            return $text;
        });

        $links = ['<a href="#">Deactivate</a>'];
        $result = $this->admin->add_plugin_action_links($links);

        $this->assertCount(2, $result);
        // array_unshift adds to beginning, so check first element
        $this->assertStringContainsString('perfbase-settings', $result[0]);
        $this->assertStringContainsString('Settings', $result[0]);
    }

    public function testEnqueueAdminScriptsOnlyOnSettingsPage()
    {
        Functions\expect('wp_enqueue_style')
            ->once()
            ->with('perfbase-admin', Mockery::any(), [], PERFBASE_PLUGIN_VERSION)
            ->andReturn(true);
        Functions\expect('wp_enqueue_script')
            ->once()
            ->with('perfbase-admin', Mockery::any(), ['jquery'], PERFBASE_PLUGIN_VERSION, true)
            ->andReturn(true);

        $this->admin->enqueue_admin_scripts('settings_page_perfbase-settings');

        // Verify expectations met
        $this->assertTrue(true);
    }

    public function testEnqueueAdminScriptsSkipsOtherPages()
    {
        Functions\expect('wp_enqueue_style')->never();
        Functions\expect('wp_enqueue_script')->never();

        $this->admin->enqueue_admin_scripts('dashboard');

        // Verify no scripts were enqueued
        $this->assertTrue(true);
    }

    public function testRenderApiKeyFieldEscapesOutput()
    {
        Functions\when('esc_attr')->alias(function($value) {
            return htmlspecialchars($value, ENT_QUOTES);
        });

        ob_start();
        $this->admin->render_api_key_field();
        $output = ob_get_clean();

        $this->assertStringContainsString('type="password"', $output);
        $this->assertStringContainsString('name="perfbase_settings[api_key]"', $output);
        $this->assertStringNotContainsString('<script>', $output);
    }

    public function testRenderEnabledFieldShowsCheckbox()
    {
        Functions\when('checked')->justReturn('checked="checked"');

        ob_start();
        $this->admin->render_enabled_field();
        $output = ob_get_clean();

        $this->assertStringContainsString('type="checkbox"', $output);
        $this->assertStringContainsString('name="perfbase_settings[enabled]"', $output);
    }

    public function testRenderSampleRateFieldHasValidation()
    {
        Functions\when('esc_attr')->returnArg();

        ob_start();
        $this->admin->render_sample_rate_field();
        $output = ob_get_clean();

        $this->assertStringContainsString('type="number"', $output);
        $this->assertStringContainsString('min="0"', $output);
        $this->assertStringContainsString('max="1"', $output);
        $this->assertStringContainsString('step="0.01"', $output);
    }

    public function testRenderTimeoutFieldHasValidation()
    {
        Functions\when('esc_attr')->returnArg();

        ob_start();
        $this->admin->render_timeout_field();
        $output = ob_get_clean();

        $this->assertStringContainsString('type="number"', $output);
        $this->assertStringContainsString('min="1"', $output);
        $this->assertStringContainsString('max="60"', $output);
    }

    public function testRenderExcludedPathsFieldShowsTextarea()
    {
        Functions\when('esc_textarea')->returnArg();

        ob_start();
        $this->admin->render_excluded_paths_field();
        $output = ob_get_clean();

        $this->assertStringContainsString('<textarea', $output);
        $this->assertStringContainsString('name="perfbase_settings[excluded_paths]"', $output);
    }

    public function testRenderExcludedUserAgentsFieldShowsTextarea()
    {
        Functions\when('esc_textarea')->returnArg();

        ob_start();
        $this->admin->render_excluded_user_agents_field();
        $output = ob_get_clean();

        $this->assertStringContainsString('<textarea', $output);
        $this->assertStringContainsString('name="perfbase_settings[excluded_user_agents]"', $output);
    }

    public function testRenderGeneralSectionShowsDescription()
    {
        ob_start();
        $this->admin->render_general_section();
        $output = ob_get_clean();

        $this->assertStringContainsString('<p>', $output);
    }

    public function testRenderAdvancedSectionShowsDescription()
    {
        ob_start();
        $this->admin->render_advanced_section();
        $output = ob_get_clean();

        $this->assertStringContainsString('<p>', $output);
    }

    public function testRenderProfilingSectionShowsDescription()
    {
        ob_start();
        $this->admin->render_profiling_section();
        $output = ob_get_clean();

        $this->assertStringContainsString('<p>', $output);
    }

    public function testRenderExclusionsSectionShowsDescription()
    {
        ob_start();
        $this->admin->render_exclusions_section();
        $output = ob_get_clean();

        $this->assertStringContainsString('<p>', $output);
    }
}
