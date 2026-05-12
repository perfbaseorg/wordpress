<p align="center">
  <a href="https://perfbase.com">
    <img src="https://cdn.perfbase.com/img/logo-full.svg" alt="Perfbase" width="300">
  </a>
</p>

<h3 align="center">Perfbase for WordPress</h3>
<p align="center">
  WordPress integration for <a href="https://perfbase.com">Perfbase</a>.
</p>

<p align="center">
  <a href="https://packagist.org/packages/perfbase/wordpress"><img src="https://img.shields.io/packagist/v/perfbase/wordpress" alt="Packagist Version"></a>
  <a href="https://github.com/perfbaseorg/wordpress/blob/main/LICENSE.txt"><img src="https://img.shields.io/packagist/l/perfbase/wordpress" alt="License"></a>
  <a href="https://github.com/perfbaseorg/wordpress/actions/workflows/tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/perfbaseorg/wordpress/tests.yml?branch=main" alt="CI"></a>
  <img src="https://img.shields.io/badge/php-7.4%2B-blue" alt="PHP Version">
  <img src="https://img.shields.io/badge/wordpress-5.0%2B-blue" alt="WordPress Version">
</p>

This plugin is a thin adapter over [`perfbase/php-sdk`](https://packagist.org/packages/perfbase/php-sdk). It detects the current WordPress execution context, starts and stops trace spans through the shared SDK, and adds WordPress-specific metadata along the way.

## What it profiles

- Standard HTTP requests
- WordPress admin requests when enabled
- AJAX requests
- WordPress cron runs
- WP-CLI commands
- Additional WordPress metadata through hook-based attribute collection

## Requirements

- PHP `7.4` to `8.5`
- WordPress `5.0+`
- `ext-curl`
- `ext-json`
- `ext-perfbase`

## Installation

### Composer-managed WordPress installs

```bash
composer require perfbase/wordpress:^1.0
```

This is the best fit for Bedrock-style or otherwise Composer-managed WordPress projects.

### WordPress.org, GitHub Releases, or classic WordPress installs

Install the plugin from WordPress.org when available, or upload the `perfbase-<version>.zip` asset from GitHub Releases through the WordPress admin plugin installer.

Do not use GitHub's automatically generated "Source code" archives for a classic WordPress install. Those archives are source snapshots for developers and do not include production Composer dependencies.

For manual source installation:

1. Copy or download this package into `wp-content/plugins/perfbase`
2. Run `composer install --no-dev --optimize-autoloader` inside the plugin directory if you are installing from source
3. Activate the plugin in the WordPress admin

### Install the Perfbase extension

The plugin depends on the native Perfbase PHP extension. Install it with:

```bash
bash -c "$(curl -fsSL https://cdn.perfbase.com/install.sh)"
```

Restart PHP-FPM, Apache, Nginx Unit, or any long-lived PHP worker after installing the extension.

## Quick start

1. Activate the plugin
2. Go to `Settings -> Perfbase`
3. Add your Perfbase API key
4. Enable profiling
5. Start with a sample rate like `0.1`

If you prefer configuration in code, define the supported constants in `wp-config.php`:

```php
define('PERFBASE_ENABLED', true);
define('PERFBASE_API_KEY', 'your-api-key-here');
define('PERFBASE_SAMPLE_RATE', 0.1);
define('PERFBASE_API_URL', 'https://ingress.perfbase.cloud');
define('PERFBASE_TIMEOUT', 10);
define('PERFBASE_PROXY', '');
define('PERFBASE_FLAGS', \Perfbase\SDK\FeatureFlags::DefaultFlags);

// Trace metadata helpers
define('PERFBASE_ENVIRONMENT', 'production');
define('PERFBASE_APP_VERSION', '1.0.0');
```

## Configuration model

Configuration priority is:

1. Defaults from `ConfigManager`
2. Saved WordPress options (`perfbase_settings`)
3. `wp-config.php` constants for the supported core keys

### Core settings

| Setting | Default | Purpose |
| --- | --- | --- |
| `enabled` | `false` | Global on/off switch |
| `debug` | `false` | Surface profiling failures instead of failing open |
| `log_errors` | `true` | Log profiling failures when debug is off |
| `api_key` | `''` | Perfbase API key |
| `api_url` | `https://ingress.perfbase.cloud` | Receiver base URL |
| `sample_rate` | `0.1` | Sampling rate between `0.0` and `1.0` |
| `timeout` | `10` | Submission timeout in seconds |
| `proxy` | `''` | Optional outbound proxy |
| `flags` | `FeatureFlags::DefaultFlags` | Perfbase extension flags |
| `profile_http_status_codes` | `array_merge(range(200, 299), range(500, 599))` | HTTP response codes that should be submitted |

### Context toggles

| Setting | Default | Notes |
| --- | --- | --- |
| `profile_admin` | `false` | Skip admin by default |
| `profile_ajax` | `true` | AJAX requests are profiled by default |
| `profile_cron` | `true` | Cron requests are profiled by default |
| `profile_cli` | `false` | WP-CLI support exists but is off by default |

### Filtering

The runtime filter model uses nested context filters:

```php
[
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
        'spider',
    ],
]
```

`http`, `ajax`, `cron`, and `cli` all support:

- `*` and `.*` wildcard matching
- glob-style patterns through `fnmatch()`
- regex patterns like `/^POST \/wp-admin/`

### Admin UI vs runtime filters

The admin page covers the common operational settings:

- API key
- enable profiling
- sample rate
- API URL
- timeout
- proxy
- profile admin
- profile AJAX
- profile cron
- profile WP-CLI
- HTTP status codes to submit
- feature flags
- HTTP include patterns
- HTTP exclude patterns
- excluded user agents

The admin UI writes the same nested `include` / `exclude` structure used by the runtime. It currently exposes the HTTP filter lists directly and preserves any existing AJAX, cron, and CLI filter arrays already present in saved config.

The HTTP status code setting accepts comma-separated values or ranges such as `200-299, 500-599, 404`. The default is `200-299, 500-599`. Leave it empty if you want to drop all HTTP trace submissions.

## Feature flags

The plugin passes SDK feature flags straight through to the Perfbase extension.

Common flags exposed in the admin UI include:

- `UseCoarseClock`
- `TrackCpuTime`
- `TrackPdo`
- `TrackHttp`
- `TrackCaches`
- `TrackMongodb`
- `TrackElasticsearch`
- `TrackQueues`
- `TrackFileOperations`

Programmatic configuration can also use the broader flag set from `perfbase/php-sdk`.

Example:

```php
define(
    'PERFBASE_FLAGS',
    \Perfbase\SDK\FeatureFlags::UseCoarseClock |
    \Perfbase\SDK\FeatureFlags::TrackCpuTime |
    \Perfbase\SDK\FeatureFlags::TrackPdo
);
```

## How it works

The plugin creates one lifecycle object per request context:

- `HttpRequestLifecycle`
- `AjaxRequestLifecycle`
- `CronLifecycle`
- `CliLifecycle`

At a high level:

1. The plugin boots on `plugins_loaded`
2. It loads config and attempts to create the shared SDK client
3. On `init`, it detects the current context and starts the appropriate lifecycle
4. During the request, lightweight hooks add WordPress-specific attributes
5. On shutdown, HTTP requests are only submitted if their status code is in `profile_http_status_codes`
5. On `shutdown`, the lifecycle stops the span and submits the trace

The plugin also adds context through WordPress hooks such as:

- outbound HTTP hooks
- theme and plugin lifecycle hooks
- user, post, comment, REST API, and WooCommerce hooks when available

Cache profiling itself is handled by the native Perfbase extension via feature flags rather than by WordPress cache hooks.

For safety and lower cardinality:

- outbound HTTP attributes are stored as sanitized `scheme://host/path` values without query strings or fragments
- database visibility is limited to aggregate shutdown-time stats such as total query count, slow query count, and total query time

## Request metadata

The plugin keeps action names low-cardinality and avoids leaking sensitive query parameters.

### Core trace attributes

- `action` in the format `GET /path`
- `user_id` when logged in
- `user_ip`
- `user_agent`
- `hostname`
- `environment`
- `app_version`
- `php_version`
- `http_method`
- `http_url`
- `http_status_code`

### WordPress-specific attributes

- `wordpress.version`
- `perfbase.version`
- `wordpress.ajax_action`
- `wordpress.rest_route`
- `wordpress.admin_page`
- template, theme, post, taxonomy, and conditional-tag attributes when available

`http_url` is stored without the query string. Important WordPress query parameters are broken out into dedicated attributes instead.

## Example production setup

For a typical production site:

```php
define('PERFBASE_ENABLED', true);
define('PERFBASE_API_KEY', getenv('PERFBASE_API_KEY'));
define('PERFBASE_SAMPLE_RATE', 0.02);
define('PERFBASE_TIMEOUT', 5);
define(
    'PERFBASE_FLAGS',
    \Perfbase\SDK\FeatureFlags::UseCoarseClock |
    \Perfbase\SDK\FeatureFlags::TrackCpuTime |
    \Perfbase\SDK\FeatureFlags::TrackPdo
);
```

That gives you low overhead while still capturing a useful stream of production traces.

## Troubleshooting

### The plugin says the extension is unavailable

Check that the extension is loaded:

```bash
php -m | grep perfbase
php --ini
```

If needed, reinstall it:

```bash
bash -c "$(curl -fsSL https://cdn.perfbase.com/install.sh)"
```

### No traces are appearing

Check these first:

- profiling is enabled
- the API key is present
- the extension is loaded
- the current request type is allowed by your profile toggles
- your sample rate is not set too low
- the request is not blocked by filters or excluded user-agent rules

### High overhead

To reduce overhead:

- lower `sample_rate`
- use `UseCoarseClock`
- disable feature flags you do not need
- avoid profiling admin traffic unless you need it
- narrow your include filters

## Development

Useful commands:

```bash
composer test
composer phpstan
composer phpcs:syntax
composer phpcs
composer lint
```

The repository includes unit, integration, and functional tests. `composer phpcs` is the focused WordPress security gate for release checks; use `composer phpcs:syntax` for the lightweight PHP syntax-only check.

## Documentation

Full documentation is available at [perfbase.com/docs](https://perfbase.com/docs).

- **Docs**: [perfbase.com/docs](https://perfbase.com/docs)
- **Issues**: [github.com/perfbaseorg/wordpress/issues](https://github.com/perfbaseorg/wordpress/issues)
- **Support**: [support@perfbase.com](mailto:support@perfbase.com)

## Release packaging

Build the installable release ZIP from a clean checkout by passing the tag or release version:

```bash
bin/build-release-zip v1.2.3
```

The generated ZIP is written to `dist/perfbase-<version>.zip` and includes production Composer dependencies only. Use this same ZIP for GitHub Releases and WordPress.org distribution.

Release checklist:

1. Run `composer test`, `composer phpstan`, `composer phpcs:syntax`, and `composer phpcs`.
2. Tag the release as `v<version>`, for example `v1.2.3`.
3. Push the tag. GitHub Actions builds, verifies, and uploads the installable ZIP to the GitHub Release.
4. Use the same generated package contents for WordPress.org SVN.

To reproduce the tagged package locally:

```bash
bin/build-release-zip v1.2.3
bin/verify-release-zip dist/perfbase-1.2.3.zip v1.2.3
```

`bin/build-wporg-zip` remains available as a compatibility wrapper around `bin/build-release-zip`.

## License

GPL-2.0-or-later. See [LICENSE.txt](LICENSE.txt).
