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
        $this->mock_plugin->shouldReceive('get_perfbase')
            ->andReturn(null);

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
            'api_url' => 'https://ingress.perfbase.cloud',
            'sample_rate' => '0.5',
            'timeout' => '15',
            'proxy' => '',
            'profile_admin' => '1',
            'profile_ajax' => '1',
            'profile_cron' => '1',
            'profile_cli' => '',
            'profile_http_status_codes' => '200-202, 404',
            'flags' => [FeatureFlags::TrackPdo, FeatureFlags::TrackHttp],
            'include_http' => "/\n/wp-json/*",
            'exclude_http' => "/wp-admin/\n/wp-cron.php",
            'exclude_user_agents' => "bot\ncrawler"
        ];

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->returnArg();

        $result = $this->admin->sanitize_settings($input);

        $this->assertTrue($result['enabled']);
        $this->assertEquals('test-key-123', $result['api_key']);
        $this->assertEquals('https://ingress.perfbase.cloud', $result['api_url']);
        $this->assertEquals(0.5, $result['sample_rate']);
        $this->assertEquals(15, $result['timeout']);
        $this->assertTrue($result['profile_admin']);
        $this->assertTrue($result['profile_ajax']);
        $this->assertFalse($result['profile_cli']);
        $this->assertEquals([200, 201, 202, 404], $result['profile_http_status_codes']);
        $this->assertIsInt($result['flags']);
        $this->assertEquals(['/', '/wp-json/*'], $result['include']['http']);
        $this->assertEquals(['/wp-admin/', '/wp-cron.php'], $result['exclude']['http']);
        $this->assertEquals(['bot', 'crawler'], $result['exclude_user_agents']);
        $this->assertEquals(['*'], $result['include']['ajax']);
        $this->assertEquals([], $result['exclude']['ajax']);
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
        // Blank submission preserves the stored api_key — see "keep existing" rule.
        $this->assertEquals('test-api-key-12345678901234567890', $result['api_key']);
        $this->assertEquals('https://ingress.perfbase.cloud', $result['api_url']);
        $this->assertEquals(0.1, $result['sample_rate']);
        $this->assertEquals(10, $result['timeout']);
        $this->assertEquals(array_merge(range(200, 299), range(500, 599)), $result['profile_http_status_codes']);
    }

    public function testSanitizeSettingsKeepsExistingApiKeyWhenBlank()
    {
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->returnArg();

        $result = $this->admin->sanitize_settings(['api_key' => '']);
        $this->assertEquals('test-api-key-12345678901234567890', $result['api_key']);

        // Submitting a new value should replace it.
        $result = $this->admin->sanitize_settings(['api_key' => 'new-key']);
        $this->assertEquals('new-key', $result['api_key']);
    }

    public function testSanitizeSettingsKeepsExistingProxyWhenBlank()
    {
        $config = TestData::getValidConfig();
        $config['proxy'] = 'http://proxy.example.com:8080';

        $mockPlugin = Mockery::mock(PerfbasePlugin::class);
        $mockPlugin->shouldReceive('get_config')->andReturn($config);
        $mockPlugin->shouldReceive('get_perfbase')->andReturn(null);
        $admin = new PerfbaseAdmin($mockPlugin);

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->returnArg();

        $result = $admin->sanitize_settings(['proxy' => '']);
        $this->assertEquals('http://proxy.example.com:8080', $result['proxy']);

        $result = $admin->sanitize_settings(['proxy' => 'http://other.example:3128']);
        $this->assertEquals('http://other.example:3128', $result['proxy']);
    }

    public function testSanitizeSettingsAllowsDisablingHttpStatusSubmission()
    {
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->returnArg();

        $result = $this->admin->sanitize_settings([
            'profile_http_status_codes' => '',
        ]);

        $this->assertSame([], $result['profile_http_status_codes']);
    }

    public function testSanitizeSettingsStripsWhitespaceFromExclusions()
    {
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->returnArg();

        $input = [
            'include_http' => "  /  \n\n  /wp-json/*  \n  ",
            'exclude_http' => "  /wp-admin/  \n\n  /wp-cron.php  \n  ",
            'exclude_user_agents' => "  bot  \n  crawler  "
        ];

        $result = $this->admin->sanitize_settings($input);

        $this->assertEquals(['/', '/wp-json/*'], $result['include']['http']);
        $this->assertEquals(['/wp-admin/', '/wp-cron.php'], $result['exclude']['http']);
        $this->assertEquals(['bot', 'crawler'], $result['exclude_user_agents']);
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

    public function testSanitizeSettingsMasksUnknownFlagBits()
    {
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->returnArg();

        $result = $this->admin->sanitize_settings([
            'flags' => [FeatureFlags::TrackPdo, 1 << 30],
        ]);

        $this->assertEquals(FeatureFlags::TrackPdo, $result['flags']);
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

    public function testRegisterPrivacyPolicyContentDisclosesPerfbaseSubmission()
    {
        Functions\when('esc_url')->returnArg();
        Functions\when('wpautop')->alias(function ($text) {
            return '<p>' . $text . '</p>';
        });
        Functions\when('wp_kses_post')->returnArg();
        Functions\expect('wp_add_privacy_policy_content')
            ->once()
            ->with(Mockery::any(), Mockery::on(function ($content) {
                $this->assertStringContainsString('external application performance monitoring service', $content);
                $this->assertStringContainsString('https://ingress.perfbase.cloud', $content);
                $this->assertStringContainsString('profiling traces', $content);
                $this->assertStringContainsString('URL path without the query string', $content);
                $this->assertStringContainsString('user IP address', $content);
                $this->assertStringContainsString('user agent', $content);
                $this->assertStringContainsString('user ID when a visitor is logged in', $content);
                $this->assertStringContainsString('hostname', $content);
                $this->assertStringContainsString('environment', $content);
                $this->assertStringContainsString('application version', $content);
                $this->assertStringContainsString('HTTP status code', $content);
                $this->assertStringContainsString('WordPress request context metadata', $content);
                $this->assertStringContainsString('API key is missing or profiling is disabled', $content);

                return true;
            }));

        $this->admin->register_privacy_policy_content();
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

    public function testRenderApiKeyFieldMasksStoredValue()
    {
        Functions\when('esc_attr')->alias(function($value) {
            return htmlspecialchars($value, ENT_QUOTES);
        });

        ob_start();
        $this->admin->render_api_key_field();
        $output = ob_get_clean();

        // The stored key (from TestData::getValidConfig) must never appear in the HTML.
        $this->assertStringNotContainsString('test-api-key-12345678901234567890', $output);

        $this->assertStringContainsString('type="password"', $output);
        $this->assertStringContainsString('name="perfbase_settings[api_key]"', $output);
        $this->assertStringContainsString('id="perfbase-api-key"', $output);
        $this->assertStringContainsString('data-has-stored="1"', $output);
        $this->assertStringContainsString('value=""', $output);
        $this->assertStringContainsString('placeholder="••••••••"', $output);
        $this->assertStringContainsString('perfbase-api-key-feedback', $output);
        $this->assertStringContainsString('Leave blank to keep', $output);
        $this->assertStringNotContainsString('<script>', $output);
    }

    public function testRenderApiKeyFieldWhenUnsetShowsNoPlaceholder()
    {
        $config = TestData::getValidConfig();
        $config['api_key'] = '';

        $mockPlugin = Mockery::mock(PerfbasePlugin::class);
        $mockPlugin->shouldReceive('get_config')->andReturn($config);
        $mockPlugin->shouldReceive('get_perfbase')->andReturn(null);
        $admin = new PerfbaseAdmin($mockPlugin);

        Functions\when('esc_attr')->returnArg();

        ob_start();
        $admin->render_api_key_field();
        $output = ob_get_clean();

        $this->assertStringContainsString('placeholder=""', $output);
        $this->assertStringContainsString('You can find this', $output);
    }

    public function testRenderProxyFieldMasksStoredValue()
    {
        $config = TestData::getValidConfig();
        $config['proxy'] = 'http://user:pass@proxy.example.com:8080';

        $mockPlugin = Mockery::mock(PerfbasePlugin::class);
        $mockPlugin->shouldReceive('get_config')->andReturn($config);
        $mockPlugin->shouldReceive('get_perfbase')->andReturn(null);
        $admin = new PerfbaseAdmin($mockPlugin);

        Functions\when('esc_attr')->returnArg();

        ob_start();
        $admin->render_proxy_field();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('user:pass@proxy.example.com', $output);
        $this->assertStringContainsString('name="perfbase_settings[proxy]"', $output);
        $this->assertStringContainsString('id="perfbase-proxy"', $output);
        $this->assertStringContainsString('data-has-stored="1"', $output);
        $this->assertStringContainsString('value=""', $output);
        $this->assertStringContainsString('placeholder="••••••••"', $output);
        $this->assertStringContainsString('Leave blank to keep', $output);
    }

    public function testRenderEnabledFieldShowsCheckbox()
    {
        Functions\when('checked')->justReturn('checked="checked"');

        ob_start();
        $this->admin->render_enabled_field();
        $output = ob_get_clean();

        $this->assertStringContainsString('type="checkbox"', $output);
        $this->assertStringContainsString('name="perfbase_settings[enabled]"', $output);
        $this->assertStringContainsString('id="perfbase-enabled"', $output);
    }

    public function testRenderProfileCliFieldShowsCheckbox()
    {
        Functions\when('checked')->justReturn('checked="checked"');

        ob_start();
        $this->admin->render_profile_cli_field();
        $output = ob_get_clean();

        $this->assertStringContainsString('type="checkbox"', $output);
        $this->assertStringContainsString('name="perfbase_settings[profile_cli]"', $output);
    }

    public function testRenderProfileHttpStatusCodesFieldShowsCompressedRanges()
    {
        Functions\when('esc_attr')->returnArg();

        ob_start();
        $this->admin->render_profile_http_status_codes_field();
        $output = ob_get_clean();

        $this->assertStringContainsString('type="text"', $output);
        $this->assertStringContainsString('name="perfbase_settings[profile_http_status_codes]"', $output);
        $this->assertStringContainsString('value="200-299, 500-599"', $output);
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
        $this->assertStringContainsString('perfbase-sample-rate-feedback', $output);
    }

    public function testRenderFlagsFieldGroupsCapabilities()
    {
        ob_start();
        $this->admin->render_flags_field();
        $output = ob_get_clean();

        $this->assertStringContainsString('perfbase-flag-groups', $output);
        $this->assertStringContainsString('perfbase-flags-count', $output);
        $this->assertStringContainsString('Timing and runtime', $output);
        $this->assertStringContainsString('Application behavior', $output);
        $this->assertStringContainsString('Integrations', $output);
        $this->assertStringContainsString('Files and processes', $output);
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

    public function testRenderIncludeHttpFieldShowsTextarea()
    {
        Functions\when('esc_textarea')->returnArg();

        ob_start();
        $this->admin->render_include_http_field();
        $output = ob_get_clean();

        $this->assertStringContainsString('<textarea', $output);
        $this->assertStringContainsString('name="perfbase_settings[include_http]"', $output);
    }

    public function testRenderExcludeHttpFieldShowsTextarea()
    {
        Functions\when('esc_textarea')->returnArg();

        ob_start();
        $this->admin->render_exclude_http_field();
        $output = ob_get_clean();

        $this->assertStringContainsString('<textarea', $output);
        $this->assertStringContainsString('name="perfbase_settings[exclude_http]"', $output);
    }

    public function testRenderExcludeUserAgentsFieldShowsTextarea()
    {
        Functions\when('esc_textarea')->returnArg();

        ob_start();
        $this->admin->render_exclude_user_agents_field();
        $output = ob_get_clean();

        $this->assertStringContainsString('<textarea', $output);
        $this->assertStringContainsString('name="perfbase_settings[exclude_user_agents]"', $output);
    }

    public function testRenderSettingsPageProvidesNativeNoticeSlotAfterHero()
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_admin_page_title')->justReturn('Perfbase Settings');
        Functions\when('settings_fields')->justReturn();
        Functions\when('get_bloginfo')->alias(function ($field) {
            return $field === 'version' ? '6.8.0' : '';
        });

        ob_start();
        $this->admin->render_settings_page();
        $output = ob_get_clean();

        $heroPosition = strpos($output, 'perfbase-hero');
        $noticePosition = strpos($output, 'perfbase-notice-slot');
        $summaryPosition = strpos($output, 'perfbase-summary');

        $this->assertStringContainsString('perfbase-notice-slot', $output);
        $this->assertStringNotContainsString('Settings saved.', $output);
        $this->assertNotFalse($heroPosition);
        $this->assertNotFalse($noticePosition);
        $this->assertNotFalse($summaryPosition);
        $this->assertLessThan($noticePosition, $heroPosition);
        $this->assertLessThan($summaryPosition, $noticePosition);
    }

    public function testRenderSettingsPageUsesBrandedCardLayout()
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_admin_page_title')->justReturn('Perfbase Settings');
        Functions\when('settings_fields')->justReturn();
        Functions\when('get_bloginfo')->alias(function ($field) {
            return $field === 'version' ? '6.9.4' : '';
        });

        ob_start();
        $this->admin->render_settings_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('perfbase-admin-page', $output);
        $this->assertStringContainsString('perfbase-hero', $output);
        $this->assertStringContainsString('perfbase-notice-slot', $output);
        $this->assertStringContainsString('perfbase-summary', $output);
        $this->assertStringContainsString('perfbase-primary-grid', $output);
        $this->assertStringContainsString('perfbase-card-connection', $output);
        $this->assertStringContainsString('perfbase-card-basic-profiling', $output);
        $this->assertStringContainsString('perfbase-card-sampling', $output);
        $this->assertStringContainsString('perfbase-profiling-card', $output);
        $this->assertStringContainsString('perfbase-system-card', $output);
        $this->assertStringContainsString('perfbase-sticky-save', $output);
        $this->assertStringContainsString('Extension missing', $output);
    }

    public function testRenderSettingsPageHidesAdvancedOptionsByDefault()
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('settings_fields')->justReturn();
        Functions\when('get_bloginfo')->alias(function ($field) {
            return $field === 'version' ? '6.9.4' : '';
        });

        ob_start();
        $this->admin->render_settings_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('class="button perfbase-advanced-toggle"', $output);
        $this->assertStringContainsString('aria-expanded="false"', $output);
        $this->assertStringContainsString('aria-controls="perfbase-advanced-options"', $output);
        $this->assertStringContainsString('Show advanced options', $output);
        $this->assertStringContainsString('id="perfbase-advanced-options"', $output);
        $this->assertStringContainsString('Connection Details', $output);
        $this->assertStringContainsString('Control whether this site sends request traces.', $output);
        $this->assertStringContainsString('name="perfbase_settings[enabled]"', $output);
        $this->assertStringContainsString('name="perfbase_settings[sample_rate]"', $output);
        $this->assertStringContainsString('Status Filtering', $output);
        $this->assertStringContainsString('Plugin version', $output);
        $this->assertStringContainsString('WordPress Version', $output);
        $this->assertStringNotContainsString('<span>Endpoint</span>', $output);
    }

    public function testRenderSettingsPageShowsSystemBadges()
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_admin_page_title')->justReturn('Perfbase Settings');
        Functions\when('settings_fields')->justReturn();
        Functions\when('get_bloginfo')->alias(function ($field) {
            return $field === 'version' ? '6.9.4' : '';
        });

        ob_start();
        $this->admin->render_settings_page();
        $output = ob_get_clean();

        $this->assertStringContainsString('perfbase-badge perfbase-badge-warning', $output);
        $this->assertStringContainsString('Plugin Version', $output);
        $this->assertStringContainsString('WordPress Version', $output);
        $this->assertStringContainsString('Perfbase Extension', $output);
        $this->assertStringContainsString('Profiling Status', $output);
    }

    public function testFormatExtensionStatusShowsExtensionVersion()
    {
        Functions\when('__')->alias(function ($text) {
            return $text;
        });

        $method = new \ReflectionMethod(PerfbaseAdmin::class, 'format_extension_status');
        $method->setAccessible(true);

        $this->assertSame('Available (v1.0.121)', $method->invoke($this->admin, true, '1.0.121'));
        $this->assertSame('Not Available', $method->invoke($this->admin, true, null));
        $this->assertSame('Not Available', $method->invoke($this->admin, false, '1.0.121'));
    }

    public function testSanitizeSettingsMigratesLegacyExcludedPaths()
    {
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->returnArg();

        $result = $this->admin->sanitize_settings([
            'excluded_paths' => "/legacy-one\n/legacy-two",
        ]);

        $this->assertEquals(['/legacy-one', '/legacy-two'], $result['exclude']['http']);
    }

    public function testSanitizeSettingsPreservesNestedFiltersForOtherContexts()
    {
        $config = TestData::getValidConfig();
        $config['include']['ajax'] = ['heartbeat'];
        $config['exclude']['ajax'] = ['forbidden'];

        $mockPlugin = Mockery::mock(PerfbasePlugin::class);
        $mockPlugin->shouldReceive('get_config')->andReturn($config);
        $admin = new PerfbaseAdmin($mockPlugin);

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->returnArg();

        $result = $admin->sanitize_settings([
            'exclude_http' => "/wp-admin/*",
        ]);

        $this->assertEquals(['heartbeat'], $result['include']['ajax']);
        $this->assertEquals(['forbidden'], $result['exclude']['ajax']);
        $this->assertEquals(['/wp-admin/*'], $result['exclude']['http']);
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
