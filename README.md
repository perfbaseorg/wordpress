# Perfbase for WordPress

[![Tests](https://github.com/perfbaseorg/wordpress/actions/workflows/tests.yml/badge.svg)](https://github.com/perfbaseorg/wordpress/actions/workflows/tests.yml)
[![PHP Version](https://img.shields.io/badge/php-7.4%2B-blue)](https://php.net)
[![WordPress Version](https://img.shields.io/badge/wordpress-5.0%2B-blue)](https://wordpress.org)
[![License](https://img.shields.io/badge/license-Apache%202.0-green)](LICENSE)
[![Codecov](https://codecov.io/gh/perfbaseorg/wordpress/branch/master/graph/badge.svg)](https://codecov.io/gh/perfbaseorg/wordpress)

> **Professional Application Performance Monitoring (APM) for WordPress**

Perfbase provides real-time performance monitoring and profiling for WordPress applications. Track database queries, monitor slow requests, profile WooCommerce operations, and gain deep insights into your WordPress application's performance.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [What Gets Monitored](#what-gets-monitored)
- [Performance Best Practices](#performance-best-practices)
- [Development](#development)
- [Contributing](#contributing)
- [Support](#support)
- [License](#license)

---

## Features

### Core Capabilities

- **Real-Time Performance Monitoring** - Track request performance, execution time, and resource usage
- **Database Query Profiling** - Monitor all SQL queries with execution time and slow query detection
- **WordPress Integration** - Deep integration with WordPress hooks, themes, and plugins
- **WooCommerce Support** - Specialized profiling for WooCommerce stores
- **Cache Profiling** - Track Redis, Memcached, and WordPress object cache operations
- **HTTP Request Monitoring** - Monitor external API calls and HTTP requests
- **Memory & CPU Tracking** - Track resource consumption and identify bottlenecks
- **Configurable Sampling** - Control profiling overhead with intelligent sampling
- **Admin Dashboard** - Easy configuration through WordPress admin interface

### Why Perfbase?

- **Low Overhead** - Minimal performance impact with configurable sampling
- **Production-Ready** - Battle-tested with comprehensive test coverage (181 tests)
- **Security First** - XSS and SQL injection prevention, secure API key handling
- **PHP 7.4-8.3 Support** - Works across all modern PHP versions
- **Open Source** - Apache 2.0 license with active development

---

## Requirements

| Requirement | Version |
|-------------|---------|
| **PHP** | 7.4 or higher |
| **WordPress** | 5.0 or higher |
| **Perfbase PHP Extension** | Latest version |
| **Composer** | For dependency management |

---

## Installation

### Via Composer (Recommended)

```bash
composer require perfbase/wordpress
```

### Manual Installation

1. Download the [latest release](https://github.com/perfbaseorg/wordpress/releases)
2. Upload to `/wp-content/plugins/perfbase/`
3. Run `composer install` in the plugin directory
4. Activate the plugin through the WordPress admin

### WordPress Plugin Directory

Coming soon! The plugin will be available through the official WordPress Plugin Directory.

---

## Quick Start

### 1. Install the Perfbase PHP Extension

```bash
# Install via PECL
pecl install perfbase

# Or download from https://perfbase.com/download
```

Add to your `php.ini`:
```ini
extension=perfbase.so
```

### 2. Get Your API Key

Sign up at [console.perfbase.com](https://console.perfbase.com) and create a new project to get your API key.

### 3. Configure the Plugin

**Via WordPress Admin:**

1. Go to **Settings → Perfbase**
2. Enter your API key
3. Enable profiling
4. Set your desired sample rate (start with 0.1 for 10% sampling)
5. Save changes

**Via wp-config.php:**

```php
// Basic configuration
define('PERFBASE_ENABLED', true);
define('PERFBASE_API_KEY', 'your-api-key-here');
define('PERFBASE_SAMPLE_RATE', 0.1); // 10% of requests

// Optional settings
define('PERFBASE_ENVIRONMENT', 'production');
define('PERFBASE_APP_VERSION', '1.0.0');
```

### 4. Start Monitoring

That's it! Visit your WordPress site and check the Perfbase dashboard to see your performance data.

---

## Configuration

### General Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **API Key** | Your Perfbase project API key | Required |
| **Enable Profiling** | Toggle profiling on/off | `false` |
| **Sample Rate** | Percentage of requests to profile (0.0-1.0) | `0.1` |
| **API URL** | Perfbase receiver endpoint | `https://receiver.perfbase.com` |
| **Timeout** | API request timeout in seconds | `10` |

### Profiling Options

Control what gets profiled:

- **Profile Admin Area** - Include WordPress admin requests
- **Profile AJAX Requests** - Include AJAX calls
- **Profile Cron Jobs** - Include WordPress cron execution
- **Profile CLI** - Include WP-CLI commands

### Feature Flags

Enable specific profiling features:

```php
use Perfbase\SDK\FeatureFlags;

// Enable all features
define('PERFBASE_FLAGS', FeatureFlags::AllFlags);

// Or select specific features
define('PERFBASE_FLAGS',
    FeatureFlags::TrackPdo |
    FeatureFlags::TrackHttp |
    FeatureFlags::TrackCaches
);
```

Available flags:

- `UseCoarseClock` - Faster, less accurate timing (low overhead)
- `TrackCpuTime` - Track CPU time consumption
- `TrackPdo` - Profile database queries
- `TrackHttp` - Monitor HTTP requests
- `TrackCaches` - Track cache operations
- `TrackMemoryAllocation` - Memory profiling (high overhead)
- `TrackFileOperations` - File I/O tracking (high overhead)

### Exclusions

Exclude specific paths or user agents:

```php
// In WordPress admin: Settings → Perfbase → Exclusions

// Via configuration
$excluded_paths = [
    '/wp-admin/admin-ajax.php',
    '/wp-content/uploads/',
    '/favicon.ico',
    '/robots.txt',
    '/wp-cron.php'
];

$excluded_user_agents = [
    'bot', 'crawler', 'spider', 'scraper'
];
```

---

## What Gets Monitored

### WordPress Core

- **Request Lifecycle** - Complete WordPress request cycle
- **Template Detection** - Active theme and template files
- **Conditional Tags** - `is_home()`, `is_single()`, `is_page()`, etc.
- **User Context** - User ID, roles, and authentication state
- **Post/Page Context** - Current post ID, type, and metadata

### Database

- **All Queries** - Every database query with execution time
- **Query Classification** - SELECT, INSERT, UPDATE, DELETE operations
- **Slow Query Detection** - Queries exceeding thresholds
- **Query Statistics** - Total count and aggregate timing

### Cache Operations

- **WordPress Object Cache** - All cache get/set operations
- **Cache Groups** - Operations grouped by cache group
- **External Caches** - Redis, Memcached, APC support

### WooCommerce (when active)

- **Page Detection** - Shop, product, cart, checkout pages
- **Product Context** - Product ID, type, and attributes
- **Cart Operations** - Add to cart, update quantities
- **Order Operations** - Order creation and updates

### Performance Metrics

- **Memory Usage** - Peak and current memory consumption
- **HTTP Response Codes** - Status code tracking
- **External HTTP Requests** - Outbound API calls
- **Component Loading** - Theme and plugin load timing

---

## Performance Best Practices

### Sampling Strategy

Balance monitoring coverage with performance impact:

| Environment | Recommended Sample Rate | Reasoning |
|-------------|------------------------|-----------|
| **Production (high-traffic)** | 0.01 - 0.05 (1-5%) | Minimal overhead, sufficient data |
| **Production (low-traffic)** | 0.1 - 0.3 (10-30%) | More coverage, still low impact |
| **Staging** | 0.3 - 0.5 (30-50%) | Representative sampling |
| **Development** | 1.0 (100%) | Full coverage for debugging |

### Feature Flag Impact

| Impact Level | Flags | Overhead |
|--------------|-------|----------|
| **Low** | `UseCoarseClock`, `TrackCpuTime`, `TrackPdo` | < 1% |
| **Medium** | `TrackHttp`, `TrackCaches` | 1-3% |
| **High** | `TrackMemoryAllocation`, `TrackFileOperations` | 3-10% |

### Recommended Production Settings

```php
// Optimized for high-traffic sites
define('PERFBASE_ENABLED', true);
define('PERFBASE_API_KEY', getenv('PERFBASE_API_KEY'));
define('PERFBASE_SAMPLE_RATE', 0.02); // 2% sampling
define('PERFBASE_TIMEOUT', 5);
define('PERFBASE_PROFILE_ADMIN', false); // Skip admin area
define('PERFBASE_FLAGS',
    FeatureFlags::UseCoarseClock |
    FeatureFlags::TrackCpuTime |
    FeatureFlags::TrackPdo
);
```

---

## Development

### Prerequisites

- PHP 7.4+
- Composer
- Git

### Setup

```bash
# Clone repository
git clone https://github.com/perfbaseorg/wordpress.git
cd wordpress

# Install dependencies
composer install

# Run tests
composer test

# Run code quality checks
composer phpstan  # Static analysis
composer phpcs    # Code style check
```

### Testing

The plugin includes **181 comprehensive tests** covering:

- Unit Tests (isolation testing)
- Integration Tests (WordPress integration)
- Functional Tests (complete workflows)

```bash
# Run all tests
composer test

# Run specific test suite
./vendor/bin/phpunit --testsuite Unit
./vendor/bin/phpunit --testsuite Integration
./vendor/bin/phpunit --testsuite Functional

# Generate coverage report
./vendor/bin/phpunit --coverage-html coverage/html
open coverage/html/index.html
```

### Test Coverage

| Area | Coverage | Tests |
|------|----------|-------|
| **Security** | 100% | XSS/SQL injection prevention |
| **Admin Interface** | 95%+ | Settings, validation, sanitization |
| **WordPress Hooks** | 90%+ | 40+ WordPress hooks |
| **Request Context** | 95%+ | ClickHouse attribute mapping |
| **Database Profiling** | 90%+ | Query tracking and analysis |
| **Edge Cases** | 85%+ | Error handling, failures |

### Continuous Integration

Tests automatically run on:
- Every push to `master`, `main`, `develop`
- All pull requests
- PHP versions: 7.4, 8.0, 8.1, 8.2, 8.3
- Quality checks: PHPUnit, PHPStan (level 6), PHPCS

---

## Contributing

We welcome contributions! Here's how to get started:

### Reporting Issues

1. Check [existing issues](https://github.com/perfbaseorg/wordpress/issues) first
2. Use issue templates for bugs and features
3. Include WordPress/PHP versions and error logs

### Submitting Pull Requests

1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/amazing-feature`)
3. **Write** tests for new functionality
4. **Ensure** all tests pass (`composer test`)
5. **Run** quality checks (`composer phpstan && composer phpcs`)
6. **Commit** with clear messages
7. **Push** to your fork
8. **Submit** a pull request

### Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- Write unit tests for all new code
- Maintain test coverage above 80%
- Document public APIs with PHPDoc
- Keep backward compatibility

### Development Workflow

```bash
# Create feature branch
git checkout -b feature/my-feature

# Make changes and add tests
# ...

# Run test suite
composer test

# Run code quality checks
composer phpstan
composer phpcs

# Fix code style issues automatically
composer phpcbf

# Commit changes
git add .
git commit -m "Add amazing feature"

# Push and create PR
git push origin feature/my-feature
```

---

## Troubleshooting

### Extension Not Available

**Error:** "Perfbase extension is not available"

**Solution:**
```bash
# Verify installation
php -m | grep perfbase

# Check PHP config
php --ini

# Restart web server
sudo systemctl restart php-fpm  # or apache2/nginx
```

### No Data in Dashboard

**Checklist:**
1. ✓ API key is correct
2. ✓ Sample rate > 0
3. ✓ Profiling is enabled
4. ✓ Requests match inclusion criteria
5. ✓ Check exclusion rules
6. ✓ Review WordPress error logs

### Performance Impact

If you're experiencing performance issues:

1. **Reduce sample rate** - Lower from 0.1 to 0.05 or 0.01
2. **Disable heavy features** - Turn off `TrackMemoryAllocation` and `TrackFileOperations`
3. **Add exclusions** - Exclude high-frequency endpoints
4. **Skip admin area** - Set `PERFBASE_PROFILE_ADMIN` to `false`
5. **Enable coarse clock** - Use `UseCoarseClock` flag

---

## Security

### API Key Management

- ✓ Store keys in `wp-config.php` or environment variables
- ✓ Use different keys per environment
- ✓ Rotate keys regularly
- ✗ Never commit keys to version control

### Data Privacy

- Profiling captures request metadata only (no sensitive content)
- User data limited to ID and roles (no passwords)
- Database queries captured without result sets
- Configure exclusions for sensitive endpoints

### Vulnerability Reporting

Found a security issue? Please email [security@perfbase.com](mailto:security@perfbase.com) instead of using public issues.

---

## FAQ

**Q: Does this slow down my site?**
A: With proper configuration, overhead is minimal (<1-2%). Use sampling rates of 0.01-0.1 for production.

**Q: What data is sent to Perfbase?**
A: Performance metrics, timing data, query information (no result sets), and request metadata. No sensitive user data.

**Q: Can I use this with WP Engine/Kinsta/other managed hosts?**
A: Yes! The PHP extension needs to be installed by the hosting provider. Contact them for custom PHP extensions.

**Q: Is it GDPR compliant?**
A: Yes. We don't collect personal data. User IDs and roles are captured for context but no PII.

**Q: Does it work with WordPress Multisite?**
A: Yes, each site in a network can be configured independently.

**Q: What about WP-CLI commands?**
A: CLI profiling can be enabled with `PERFBASE_PROFILE_CLI`. Disabled by default.

---

## Support

- **Documentation**: [docs.perfbase.com](https://docs.perfbase.com)
- **Community**: [GitHub Discussions](https://github.com/perfbaseorg/wordpress/discussions)
- **Issues**: [GitHub Issues](https://github.com/perfbaseorg/wordpress/issues)
- **Email**: [support@perfbase.com](mailto:support@perfbase.com)
- **Twitter**: [@perfbasecom](https://twitter.com/perfbasecom)

---

## License

This plugin is licensed under the Apache License 2.0. See [LICENSE](LICENSE) for details.

```
Copyright 2025 Perfbase

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
```

---

## Changelog

### 1.0.0 - Initial Release (2025-01-XX)

#### Added
- Complete WordPress integration with 40+ hooks
- Real-time performance monitoring and profiling
- Database query tracking and slow query detection
- WooCommerce integration for e-commerce sites
- Admin dashboard for easy configuration
- Configurable sampling and feature flags
- Cache profiling (Redis, Memcached, WordPress Object Cache)
- HTTP request monitoring
- Memory and CPU tracking
- Comprehensive test suite (181 tests)
- Multi-PHP version support (7.4-8.3)
- CI/CD pipeline with automated testing

#### Security
- XSS prevention in admin interface
- SQL injection prevention
- Secure API key handling
- Input sanitization and validation

---

<div align="center">

**[⬆ back to top](#perfbase-for-wordpress)**

Made with ❤️ by the [Perfbase Team](https://perfbase.com)

**[Star this repo](https://github.com/perfbaseorg/wordpress) if you find it useful!** ⭐

</div>
