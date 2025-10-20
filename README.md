# Perfbase WordPress Plugin

WordPress integration for the Perfbase APM (Application Performance Monitoring) platform. This plugin provides comprehensive performance monitoring and profiling for WordPress applications.

## Overview

The Perfbase WordPress plugin integrates with the Perfbase APM platform to provide:

- **Real-time Performance Monitoring**: Track request performance, database queries, and resource usage
- **WordPress-Specific Profiling**: Monitor themes, plugins, hooks, and WordPress core functionality
- **Configurable Sampling**: Control which requests are profiled and at what rate
- **WooCommerce Integration**: Special profiling for WooCommerce stores (when WooCommerce is active)
- **Admin Dashboard**: Easy configuration through WordPress admin interface

## Requirements

- **PHP**: 7.4 or higher
- **WordPress**: 5.0 or higher
- **Perfbase PHP Extension**: Required for profiling functionality
- **Perfbase PHP SDK**: Automatically included via Composer

## Installation

### Via Composer (Recommended)

```bash
composer require perfbase/wordpress
```

### Manual Installation

1. Download the plugin files
2. Upload to `/wp-content/plugins/perfbase/`
3. Run `composer install` in the plugin directory
4. Activate the plugin through the WordPress admin interface

### WordPress Plugin Directory

The plugin will be available through the WordPress Plugin Directory once published.

## Configuration

1. **Install Perfbase PHP Extension**: Follow the [extension installation guide](https://docs.perfbase.com/installation/php-extension)

2. **Get API Key**: Obtain your API key from the [Perfbase Console](https://console.perfbase.com)

3. **Configure Plugin**:
   - Go to **Settings → Perfbase** in WordPress admin
   - Enter your API key
   - Enable profiling
   - Configure sampling rate and features

## Settings

### General Settings

- **API Key**: Your Perfbase project API key
- **Enable Profiling**: Toggle profiling on/off
- **Sample Rate**: Percentage of requests to profile (0.0-1.0)

### Advanced Settings

- **API URL**: Perfbase receiver endpoint (default: https://receiver.perfbase.com)
- **Timeout**: API request timeout in seconds
- **Proxy Server**: Optional proxy configuration

### Profiling Options

- **Profile Admin Area**: Include WordPress admin requests
- **Profile AJAX Requests**: Include AJAX calls
- **Profile Cron Jobs**: Include WordPress cron execution
- **Feature Flags**: Select specific profiling features:
  - CPU Time Tracking
  - Database Query Profiling
  - HTTP Request Monitoring
  - Cache Operation Tracking
  - File Operation Monitoring

### Exclusions

- **Excluded Paths**: URL paths to skip profiling
- **Excluded User Agents**: User agents to skip (bots, crawlers)

## Features

### WordPress Integration

- **Request Lifecycle**: Profiles complete WordPress request cycle
- **Template Detection**: Tracks template files and theme information
- **Conditional Tags**: Records WordPress conditional states (is_home, is_single, etc.)
- **User Context**: Includes user authentication and role information
- **Post/Page Context**: Captures current post/page metadata

### Database Profiling

- **Query Tracking**: Monitors all database queries
- **Query Type Classification**: Categorizes SELECT, INSERT, UPDATE, DELETE operations
- **Slow Query Detection**: Identifies queries exceeding performance thresholds
- **Query Statistics**: Total count and execution time

### Cache Profiling

- **WordPress Object Cache**: Monitors cache get/set operations
- **Cache Groups**: Tracks cache operations by group
- **External Cache Systems**: Supports Redis, Memcached when configured

### WooCommerce Integration

When WooCommerce is active, additional profiling includes:

- **Page Type Detection**: Shop, product, cart, checkout pages
- **Product Context**: Product ID and type information
- **Cart Operations**: Add to cart, quantity changes
- **Order Operations**: Order creation and updates

### Performance Monitoring

- **Memory Usage**: Peak and current memory consumption
- **HTTP Response Codes**: Status code tracking
- **External HTTP Requests**: Outbound API calls and requests
- **Theme and Plugin Loading**: Component load timing

## WordPress Hooks

The plugin integrates with numerous WordPress hooks:

### Core Hooks
- `init`: Start request profiling
- `wp_loaded`: Mark WordPress core loaded
- `template_redirect`: Capture template information
- `shutdown`: Finalize and submit profiling data

### Database Hooks
- `query`: Profile database queries
- WordPress database optimization

### User Hooks
- `wp_login`: Track user authentication
- `wp_logout`: Track user logout

### Content Hooks
- `wp_insert_post`: Track post creation/updates
- `wp_insert_comment`: Track comment creation

## Configuration Examples

### Basic Configuration

```php
// wp-config.php additions
define('PERFBASE_ENABLED', true);
define('PERFBASE_API_KEY', 'your-api-key-here');
define('PERFBASE_SAMPLE_RATE', 0.1); // 10% sampling

// Optional: Set environment name (defaults to wp_get_environment_type())
define('PERFBASE_ENVIRONMENT', 'staging');

// Optional: Set application version (defaults to WordPress version)
define('PERFBASE_APP_VERSION', '2.3.1');
```

### Advanced Configuration

```php
// High-traffic site configuration
define('PERFBASE_ENABLED', true);
define('PERFBASE_API_KEY', 'your-api-key-here');
define('PERFBASE_SAMPLE_RATE', 0.01); // 1% sampling
define('PERFBASE_TIMEOUT', 5);
define('PERFBASE_PROFILE_ADMIN', false);
```

### Development Configuration

```php
// Development environment
define('PERFBASE_ENABLED', true);
define('PERFBASE_API_KEY', 'your-dev-api-key');
define('PERFBASE_SAMPLE_RATE', 1.0); // 100% sampling
define('PERFBASE_FLAGS', \Perfbase\SDK\FeatureFlags::AllFlags);
```

## Performance Considerations

### Sampling Strategy

- **Production Sites**: Use low sampling rates (0.01-0.1) for high-traffic sites
- **Development**: Use higher sampling rates (0.5-1.0) for comprehensive testing
- **Staging**: Medium sampling rates (0.1-0.3) for representative data

### Feature Flags

Balance functionality vs. performance impact:

- **Low Impact**: `UseCoarseClock`, `TrackCpuTime`, `TrackPdo`
- **Medium Impact**: `TrackHttp`, `TrackCaches`
- **High Impact**: `TrackMemoryAllocation`, `TrackFileOperations`

### Exclusions

Configure exclusions to reduce noise:

```php
// Exclude common non-essential paths
$excluded_paths = [
    '/wp-admin/admin-ajax.php',
    '/wp-content/uploads/',
    '/favicon.ico',
    '/robots.txt',
    '/wp-cron.php'
];

// Exclude bot traffic
$excluded_user_agents = [
    'bot', 'crawler', 'spider', 'scraper'
];
```

## Troubleshooting

### Extension Not Available

If you see "Perfbase extension is not available":

1. Verify PHP extension installation: `php -m | grep perfbase`
2. Check PHP configuration: `php --ini`
3. Restart web server after installation
4. Check error logs for extension loading issues

### No Data in Dashboard

If profiling data isn't appearing:

1. Verify API key is correct
2. Check sampling rate (ensure > 0)
3. Verify requests match inclusion criteria
4. Check exclusion rules aren't too broad
5. Monitor WordPress error logs

### Performance Impact

If experiencing performance issues:

1. Reduce sample rate
2. Disable high-impact feature flags
3. Add exclusions for high-frequency requests
4. Consider profiling only specific user roles or conditions

## Security

### API Key Management

- Store API keys in `wp-config.php` or environment variables
- Use different keys for different environments
- Rotate keys regularly
- Never commit keys to version control

### Data Privacy

- Profiling data includes request metadata but not sensitive content
- User information is limited to ID and roles (no passwords or personal data)
- Database queries are captured but not result sets
- Configure exclusions for sensitive endpoints

## Development

### Local Development

```bash
# Clone repository
git clone https://github.com/perfbaseorg/wordpress.git
cd wordpress

# Install dependencies
composer install

# Run tests
composer test

# Run linting
composer lint
```

### Testing

[![Tests](https://github.com/perfbaseorg/wordpress/actions/workflows/tests.yml/badge.svg)](https://github.com/perfbaseorg/wordpress/actions/workflows/tests.yml)

The plugin includes comprehensive test coverage across multiple test types:

#### Test Structure

- **Unit Tests** (`tests/Unit/`): Test individual classes and methods in isolation
- **Integration Tests** (`tests/Integration/`): Test component interactions and WordPress integration
- **Functional Tests** (`tests/Functional/`): Test complete workflows and user scenarios

#### Running Tests

```bash
# Run all tests
composer test
# or
./vendor/bin/phpunit

# Run specific test suite
./vendor/bin/phpunit --testsuite Unit
./vendor/bin/phpunit --testsuite Integration
./vendor/bin/phpunit --testsuite Functional

# Run with code coverage
./vendor/bin/phpunit --coverage-html coverage/html
# View coverage report at: coverage/html/index.html

# Run with coverage output to terminal
./vendor/bin/phpunit --coverage-text
```

#### Test Coverage

Current test coverage includes:

- **Plugin Core**: Initialization, configuration, lifecycle management
- **Admin Interface**: Settings pages, form validation, security (XSS/SQL injection prevention)
- **Profiler**: WordPress hook integration, database query profiling, request context
- **Request Context**: ClickHouse attribute mapping, environment detection, query parameter handling
- **Edge Cases**: API failures, network errors, invalid configurations, resource exhaustion

Coverage targets:
- **Unit Tests**: ≥80% code coverage
- **Integration Tests**: ≥70% code coverage
- **Critical Paths**: 100% coverage for security and data integrity

#### Continuous Integration

Tests run automatically on:
- All pushes to `master`, `main`, and `develop` branches
- All pull requests

CI matrix tests against:
- PHP 7.4, 8.0, 8.1, 8.2, 8.3
- Multiple WordPress versions
- Ubuntu latest environment

Quality checks include:
- PHPUnit test suite (all PHP versions)
- PHPStan static analysis (level 6)
- PHPCS code style validation (WordPress Coding Standards)
- Code coverage reporting (Codecov)

#### Writing Tests

When contributing, ensure tests:

1. **Follow WordPress testing patterns** using Brain Monkey/WP_Mock
2. **Mock WordPress functions** rather than requiring WordPress installation
3. **Test edge cases** including errors and invalid inputs
4. **Include security tests** for XSS, SQL injection, and CSRF
5. **Maintain isolation** between tests using `setUp()` and `tearDown()`
6. **Document test purpose** with clear test method names and comments

Example test structure:

```php
<?php
namespace Perfbase\WordPress\Tests\Unit\Plugin;

use Perfbase\WordPress\Tests\Helpers\BaseWordPressTest;
use Brain\Monkey\Functions;

class MyFeatureTest extends BaseWordPressTest
{
    protected function setUp(): void
    {
        parent::setUp();
        // Test-specific setup
    }

    public function testFeatureBehavior()
    {
        // Mock WordPress functions
        Functions\when('get_option')->justReturn(['enabled' => true]);

        // Execute test
        $result = my_feature_function();

        // Assert expectations
        $this->assertTrue($result);
    }
}
```

### Contributing

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Ensure all tests pass: `composer test`
5. Run code quality checks: `composer phpstan && composer phpcs`
6. Submit a pull request

## Support

- **Documentation**: [https://docs.perfbase.com](https://docs.perfbase.com)
- **Issues**: [GitHub Issues](https://github.com/perfbaseorg/wordpress/issues)
- **Support**: [support@perfbase.com](mailto:support@perfbase.com)

## License

This plugin is licensed under the Apache License 2.0. See [LICENSE](LICENSE) for details.

## Changelog

### 1.0.0 (Initial Release)

- Initial WordPress plugin implementation
- Basic profiling functionality
- Admin interface
- WooCommerce integration
- WordPress hook integration
- Configurable sampling and feature flags