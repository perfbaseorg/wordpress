<?php

namespace Perfbase\WordPress\Tests\Unit\Plugin;

use Brain\Monkey\Functions;
use Perfbase\WordPress\Helpers\RequestContext;
use Perfbase\WordPress\Tests\Helpers\BaseWordPressTest;
use Perfbase\WordPress\Tests\Helpers\TestData;
use Mockery;

/**
 * Test RequestContext class
 */
class RequestContextTest extends BaseWordPressTest
{
    private $request_context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request_context = new RequestContext();
    }

    /**
     * Create a mock RequestContext with specific context methods
     */
    private function createMockRequestContext(array $methods = []): RequestContext
    {
        $mock = Mockery::mock(RequestContext::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        foreach ($methods as $method => $return) {
            $mock->shouldReceive($method)->andReturn($return);
        }

        return $mock;
    }

    public function testGetSpanNameForFrontend()
    {
        $mock_context = $this->createMockRequestContext([
            'isAdmin' => false,
            'isDoingAjax' => false,
            'isDoingCron' => false,
            'isWpCli' => false
        ]);

        $span_name = $mock_context->getSpanName();

        $this->assertEquals('wordpress.request', $span_name);
    }

    public function testGetSpanNameForAdmin()
    {
        $mock_context = $this->createMockRequestContext([
            'isAdmin' => true
        ]);

        $span_name = $mock_context->getSpanName();

        $this->assertEquals('wordpress.admin', $span_name);
    }

    public function testGetSpanNameForAjax()
    {
        $mock_context = $this->createMockRequestContext([
            'isAdmin' => false,
            'isDoingAjax' => true
        ]);

        $span_name = $mock_context->getSpanName();

        $this->assertEquals('wordpress.ajax', $span_name);
    }

    public function testGetSpanNameForCron()
    {
        $mock_context = $this->createMockRequestContext([
            'isAdmin' => false,
            'isDoingAjax' => false,
            'isDoingCron' => true
        ]);

        $span_name = $mock_context->getSpanName();

        $this->assertEquals('wordpress.cron', $span_name);
    }

    public function testGetSpanNameForCli()
    {
        $mock_context = $this->createMockRequestContext([
            'isAdmin' => false,
            'isDoingAjax' => false,
            'isDoingCron' => false,
            'isWpCli' => true
        ]);

        $span_name = $mock_context->getSpanName();

        $this->assertEquals('wordpress.cli', $span_name);
    }

    public function testGetRequestAttributes()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/test-page/';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test Browser';

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.0');
        Functions\when('get_query_var')->justReturn(0);
        Functions\when('get_query_var')->justReturn(0);

        $attributes = $this->request_context->getRequestAttributes();

        $this->assertIsArray($attributes);
        $this->assertEquals('GET', $attributes['http_method']);
        $this->assertEquals('http://example.com/test-page/', $attributes['http_url']);
        $this->assertEquals('Mozilla/5.0 Test Browser', $attributes['user_agent']);
        $this->assertEquals('6.0', $attributes['wordpress.version']);
        $this->assertEquals('1.0.0', $attributes['perfbase.version']);
    }

    public function testGetRequestAttributesWithPostId()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/';

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.0');
        Functions\when('get_query_var')->alias(function($var) {
            return $var === 'p' ? 123 : 0;
        });

        $attributes = $this->request_context->getRequestAttributes();

        $this->assertEquals('123', $attributes['wordpress.post_id']);
    }

    public function testGetRequestAttributesWithPageId()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/';

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.0');
        Functions\when('get_query_var')->alias(function($var) {
            return $var === 'page_id' ? 456 : 0;
        });

        $attributes = $this->request_context->getRequestAttributes();

        $this->assertEquals('456', $attributes['wordpress.page_id']);
    }

    public function testGetWordPressContextWithLoggedInUser()
    {
        Functions\when('is_front_page')->justReturn(true);
        Functions\when('is_home')->justReturn(false);
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_single')->justReturn(false);
        Functions\when('is_page')->justReturn(false);
        Functions\when('is_404')->justReturn(false);
        Functions\when('is_search')->justReturn(false);
        Functions\when('is_archive')->justReturn(false);
        Functions\when('is_attachment')->justReturn(false);
        Functions\when('is_feed')->justReturn(false);
        Functions\when('is_category')->justReturn(false);
        Functions\when('is_tag')->justReturn(false);
        Functions\when('is_tax')->justReturn(false);
        Functions\when('is_singular')->justReturn(false);
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('wp_get_current_user')->justReturn((object) [
            'ID' => 1,
            'roles' => ['administrator']
        ]);

        $context = $this->request_context->getWordPressContext();

        $this->assertArrayHasKey('wordpress.is_front_page', $context);
        $this->assertEquals('true', $context['wordpress.is_front_page']);
        $this->assertArrayHasKey('user.id', $context);
        $this->assertEquals('1', $context['user.id']);
        $this->assertArrayHasKey('user.role', $context);
        $this->assertEquals('administrator', $context['user.role']);
    }

    public function testGetWordPressContextWithSinglePost()
    {
        $mock_post = (object) [
            'ID' => 123,
            'post_type' => 'post',
            'post_status' => 'publish'
        ];

        Functions\when('is_singular')->justReturn(true);
        Functions\when('get_queried_object')->justReturn($mock_post);
        Functions\when('is_user_logged_in')->justReturn(false);

        $context = $this->request_context->getWordPressContext();

        $this->assertEquals('123', $context['wordpress.post.id']);
        $this->assertEquals('post', $context['wordpress.post.type']);
        $this->assertEquals('publish', $context['wordpress.post.status']);
    }

    public function testGetWordPressContextWithTerm()
    {
        $mock_term = (object) [
            'term_id' => 5,
            'taxonomy' => 'category',
            'slug' => 'test-category'
        ];

        Functions\when('is_category')->justReturn(true);
        Functions\when('is_tag')->justReturn(false);
        Functions\when('is_tax')->justReturn(false);
        Functions\when('get_queried_object')->justReturn($mock_term);
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('is_singular')->justReturn(false);

        $context = $this->request_context->getWordPressContext();

        $this->assertEquals('5', $context['wordpress.term.id']);
        $this->assertEquals('category', $context['wordpress.term.taxonomy']);
        $this->assertEquals('test-category', $context['wordpress.term.slug']);
    }

    public function testGetTemplateContext()
    {
        $mock_theme = Mockery::mock();
        $mock_theme->shouldReceive('get')
            ->with('Name')
            ->andReturn('Test Theme');
        $mock_theme->shouldReceive('get')
            ->with('Version')
            ->andReturn('1.0');

        Functions\when('get_page_template_slug')->justReturn('page-custom.php');
        Functions\when('wp_get_theme')->justReturn($mock_theme);

        $context = $this->request_context->getTemplateContext();

        $this->assertEquals('page-custom.php', $context['wordpress.template']);
        $this->assertEquals('Test Theme', $context['wordpress.theme.name']);
        $this->assertEquals('1.0', $context['wordpress.theme.version']);
    }

    public function testGetFinalAttributes()
    {
        Functions\when('memory_get_peak_usage')->justReturn(1024000);
        Functions\when('memory_get_usage')->justReturn(512000);
        Functions\when('get_num_queries')->justReturn(15);
        Functions\when('http_response_code')->justReturn(200);

        $attributes = $this->request_context->getFinalAttributes();

        $this->assertEquals('1024000', $attributes['memory.peak']);
        $this->assertEquals('512000', $attributes['memory.current']);
        $this->assertEquals('15', $attributes['database.queries']);
        $this->assertEquals('200', $attributes['http_status_code']);
    }

    public function testGetCurrentUrlHttp()
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/test-page/?param=value';

        Functions\when('is_ssl')->justReturn(false);

        $url = $this->request_context->getCurrentUrl();

        // getCurrentUrl() now strips query strings
        $this->assertEquals('http://example.com/test-page/', $url);
    }

    public function testGetCurrentUrlHttps()
    {
        $_SERVER['HTTP_HOST'] = 'secure.example.com';
        $_SERVER['REQUEST_URI'] = '/secure-page/';

        Functions\when('is_ssl')->justReturn(true);

        $url = $this->request_context->getCurrentUrl();

        $this->assertEquals('https://secure.example.com/secure-page/', $url);
    }

    public function testShouldProfileRequestAdmin()
    {
        $config = TestData::getValidConfig();
        $config['profile_admin'] = false;

        Functions\when('is_admin')->justReturn(true);

        $result = $this->request_context->shouldProfileRequest($config);

        $this->assertFalse($result);
    }

    public function testShouldProfileRequestBasicLogic()
    {
        // Test the basic logic with a simple config
        $config = [
            'profile_admin' => true,
            'include' => ['http' => ['*']],
            'exclude' => ['http' => []],
            'exclude_user_agents' => []
        ];

        $_SERVER['REQUEST_URI'] = '/simple-page/';
        $_SERVER['HTTP_USER_AGENT'] = 'SimpleTestAgent';

        Functions\when('is_admin')->justReturn(false);

        $result = $this->request_context->shouldProfileRequest($config);

        $this->assertTrue($result);
    }

    public function testShouldProfileRequestCli()
    {
        // WP_CLI check is now handled by CliLifecycle, not shouldProfileRequest().
        // shouldProfileRequest() only checks admin, path filters, and user agents.
        $config = TestData::getValidConfig();

        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_USER_AGENT'] = 'WP-CLI';

        Functions\when('is_admin')->justReturn(false);

        $result = $this->request_context->shouldProfileRequest($config);

        // Should return true because shouldProfileRequest no longer checks WP_CLI
        $this->assertTrue($result);
    }

    public function testShouldProfileRequestExcludedPath()
    {
        $config = TestData::getValidConfig();
        // Use a path that matches the new exclude.http patterns (glob-based)
        $_SERVER['REQUEST_URI'] = '/wp-content/uploads/2023/image.jpg';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test Browser';

        Functions\when('is_admin')->justReturn(false);

        $result = $this->request_context->shouldProfileRequest($config);

        $this->assertFalse($result);
    }

    public function testShouldProfileRequestExcludedUserAgent()
    {
        $config = TestData::getValidConfig();
        // Config now uses 'exclude_user_agents' key with substring matching
        $_SERVER['REQUEST_URI'] = '/test-page/';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1)';

        Functions\when('is_admin')->justReturn(false);

        $result = $this->request_context->shouldProfileRequest($config);

        // "bot" in exclude_user_agents matches "Googlebot"
        $this->assertFalse($result);
    }

    public function testShouldProfileRequestSimpleExclusion()
    {
        // Test path exclusion logic with new FilterMatcher-based config
        $config = [
            'profile_admin' => true,
            'include' => ['http' => ['*']],
            'exclude' => ['http' => ['/exclude-me/*']],
            'exclude_user_agents' => []
        ];

        $_SERVER['REQUEST_URI'] = '/exclude-me/test.php';
        $_SERVER['HTTP_USER_AGENT'] = 'SimpleTestAgent';

        Functions\when('is_admin')->justReturn(false);

        $result = $this->request_context->shouldProfileRequest($config);

        $this->assertFalse($result);
    }

    public function testShouldProfileRequestHonorsIncludeFiltersWithoutExcludes()
    {
        $config = [
            'profile_admin' => true,
            'include' => ['http' => ['/wp-json/*']],
            'exclude' => ['http' => []],
            'exclude_user_agents' => []
        ];

        $_SERVER['REQUEST_URI'] = '/non-matching-page/';
        $_SERVER['HTTP_USER_AGENT'] = 'SimpleTestAgent';

        Functions\when('is_admin')->justReturn(false);

        $result = $this->request_context->shouldProfileRequest($config);

        $this->assertFalse($result);
    }

    // Edge Case Tests for Recent Changes

    public function testGetRequestAttributesStripsQueryParamsFromAction()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/wp-cron.php?doing_wp_cron=1760930969.8033809661865234375000';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['HTTP_USER_AGENT'] = 'WordPress/6.0';

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.0');
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('get_query_var')->justReturn(0);
        Functions\when('wp_get_environment_type')->justReturn('production');
        Functions\when('gethostname')->justReturn('webserver-01');
        Functions\when('phpversion')->justReturn('8.2.0');

        $attributes = $this->request_context->getRequestAttributes();

        // Action should NOT include query parameters
        $this->assertEquals('POST /wp-cron.php', $attributes['action']);
        // http_url also strips query strings now (getCurrentUrl strips them)
        $this->assertEquals('http://example.com/wp-cron.php', $attributes['http_url']);
    }

    public function testGetRequestAttributesExtractsAjaxAction()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php?action=heartbeat&_nonce=abc123';
        $_SERVER['HTTP_HOST'] = 'example.com';

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.0');
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('get_query_var')->justReturn(0);
        Functions\when('wp_get_environment_type')->justReturn('production');
        Functions\when('gethostname')->justReturn('webserver-01');
        Functions\when('phpversion')->justReturn('8.2.0');

        $attributes = $this->request_context->getRequestAttributes();

        $this->assertEquals('POST /wp-admin/admin-ajax.php', $attributes['action']);
        $this->assertEquals('heartbeat', $attributes['wordpress.ajax_action']);
    }

    public function testGetRequestAttributesExtractsRestRoute()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/wp-json/?rest_route=/wp/v2/posts&per_page=10';
        $_SERVER['HTTP_HOST'] = 'example.com';

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.0');
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('get_query_var')->justReturn(0);
        Functions\when('wp_get_environment_type')->justReturn('production');
        Functions\when('gethostname')->justReturn('webserver-01');
        Functions\when('phpversion')->justReturn('8.2.0');

        $attributes = $this->request_context->getRequestAttributes();

        $this->assertEquals('GET /wp-json/', $attributes['action']);
        $this->assertEquals('/wp/v2/posts', $attributes['wordpress.rest_route']);
    }

    public function testGetRequestAttributesExtractsAdminPage()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=perfbase-settings&tab=general';
        $_SERVER['HTTP_HOST'] = 'example.com';

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.0');
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('get_query_var')->justReturn(0);
        Functions\when('wp_get_environment_type')->justReturn('production');
        Functions\when('gethostname')->justReturn('webserver-01');
        Functions\when('phpversion')->justReturn('8.2.0');

        $attributes = $this->request_context->getRequestAttributes();

        $this->assertEquals('GET /wp-admin/admin.php', $attributes['action']);
        $this->assertEquals('perfbase-settings', $attributes['wordpress.admin_page']);
    }

    public function testGetRequestAttributesHandlesMissingQueryParams()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/simple-page/';
        $_SERVER['HTTP_HOST'] = 'example.com';

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.0');
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('get_query_var')->justReturn(0);
        Functions\when('wp_get_environment_type')->justReturn('production');
        Functions\when('gethostname')->justReturn('webserver-01');
        Functions\when('phpversion')->justReturn('8.2.0');

        $attributes = $this->request_context->getRequestAttributes();

        $this->assertEquals('GET /simple-page/', $attributes['action']);
        $this->assertArrayNotHasKey('wordpress.ajax_action', $attributes);
        $this->assertArrayNotHasKey('wordpress.rest_route', $attributes);
        $this->assertArrayNotHasKey('wordpress.admin_page', $attributes);
    }

    public function testGetRequestAttributesEnvironmentDetectionWordPress55Plus()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'example.com';

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.0');
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('get_query_var')->justReturn(0);
        Functions\when('wp_get_environment_type')->justReturn('staging');
        Functions\when('gethostname')->justReturn('webserver-01');
        Functions\when('phpversion')->justReturn('8.2.0');

        $attributes = $this->request_context->getRequestAttributes();

        $this->assertEquals('staging', $attributes['environment']);
    }

    public function testGetRequestAttributesEnvironmentDetectionFallbackToWPDebug()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'example.com';

        // Simulate WordPress <5.5 without wp_get_environment_type
        Functions\when('wp_get_environment_type')->justReturn(null);
        Functions\when('function_exists')->alias(function($func) {
            return $func !== 'wp_get_environment_type';
        });

        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('5.4');
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('get_query_var')->justReturn(0);
        Functions\when('gethostname')->justReturn('webserver-01');
        Functions\when('phpversion')->justReturn('8.2.0');

        $attributes = $this->request_context->getRequestAttributes();

        $this->assertEquals('development', $attributes['environment']);
    }

    public function testGetRequestAttributesAppVersionDefault()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'example.com';

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.4.1');
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('get_query_var')->justReturn(0);
        Functions\when('wp_get_environment_type')->justReturn('production');
        Functions\when('gethostname')->justReturn('webserver-01');
        Functions\when('phpversion')->justReturn('8.2.0');

        $attributes = $this->request_context->getRequestAttributes();

        // Should default to WordPress version
        $this->assertEquals('6.4.1', $attributes['app_version']);
    }

    public function testGetRequestAttributesHandlesMissingServerVars()
    {
        // Unset all $_SERVER vars
        $_SERVER = [];

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.0');
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('get_query_var')->justReturn(0);
        Functions\when('wp_get_environment_type')->justReturn('production');
        Functions\when('gethostname')->justReturn('webserver-01');
        Functions\when('phpversion')->justReturn('8.2.0');

        $attributes = $this->request_context->getRequestAttributes();

        $this->assertEquals('GET /', $attributes['action']);
        $this->assertEquals('http://', $attributes['http_url']);
        $this->assertEquals('GET', $attributes['http_method']);
    }

    public function testGetRequestAttributesHandlesMalformedHostname()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.0');
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('get_query_var')->justReturn(0);
        Functions\when('wp_get_environment_type')->justReturn('production');
        Functions\when('gethostname')->justReturn(false); // Failed to get hostname
        Functions\when('phpversion')->justReturn('8.2.0');

        $attributes = $this->request_context->getRequestAttributes();

        $this->assertEquals('', $attributes['hostname']);
    }

    public function testGetRequestAttributesHandlesInvalidPHPVersion()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'example.com';

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.0');
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('get_query_var')->justReturn(0);
        Functions\when('wp_get_environment_type')->justReturn('production');
        Functions\when('gethostname')->justReturn('webserver-01');
        Functions\when('phpversion')->justReturn(false); // Failed

        $attributes = $this->request_context->getRequestAttributes();

        $this->assertEquals('', $attributes['php_version']);
    }

    public function testGetRequestAttributesIncludesUserIdWhenLoggedIn()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/dashboard/';
        $_SERVER['HTTP_HOST'] = 'example.com';

        $mock_user = (object) [
            'ID' => 42,
            'roles' => ['editor']
        ];

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.0');
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('wp_get_current_user')->justReturn($mock_user);
        Functions\when('get_query_var')->justReturn(0);
        Functions\when('wp_get_environment_type')->justReturn('production');
        Functions\when('gethostname')->justReturn('webserver-01');
        Functions\when('phpversion')->justReturn('8.2.0');

        $attributes = $this->request_context->getRequestAttributes();

        $this->assertEquals('42', $attributes['user_id']);
    }

    public function testGetRequestAttributesNoUserIdWhenNotLoggedIn()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'example.com';

        Functions\when('is_ssl')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('6.0');
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('get_query_var')->justReturn(0);
        Functions\when('wp_get_environment_type')->justReturn('production');
        Functions\when('gethostname')->justReturn('webserver-01');
        Functions\when('phpversion')->justReturn('8.2.0');

        $attributes = $this->request_context->getRequestAttributes();

        $this->assertArrayNotHasKey('user_id', $attributes);
    }

    public function testGetFinalAttributesHandlesFalseHttpResponseCode()
    {
        Functions\when('memory_get_peak_usage')->justReturn(1024000);
        Functions\when('memory_get_usage')->justReturn(512000);
        Functions\when('get_num_queries')->justReturn(10);
        Functions\when('http_response_code')->justReturn(false); // No response code yet

        $attributes = $this->request_context->getFinalAttributes();

        $this->assertArrayNotHasKey('http_status_code', $attributes);
    }

    public function testGetFinalAttributesIncludesHttpStatusCode()
    {
        Functions\when('memory_get_peak_usage')->justReturn(1024000);
        Functions\when('memory_get_usage')->justReturn(512000);
        Functions\when('get_num_queries')->justReturn(10);
        Functions\when('http_response_code')->justReturn(404);

        $attributes = $this->request_context->getFinalAttributes();

        $this->assertEquals('404', $attributes['http_status_code']);
    }
}
