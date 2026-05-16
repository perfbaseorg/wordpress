=== Perfbase ===
Contributors: perfbaseorg
Tags: performance, profiling, apm, monitoring, woocommerce
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: trunk
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress integration for the Perfbase APM platform.

== Description ==

Perfbase profiles WordPress applications and sends performance traces to the Perfbase APM platform. It captures request lifecycle timing, WordPress context, selected metadata, and trace data through the native Perfbase PHP extension and the shared Perfbase PHP SDK.

This plugin is a Software-as-a-Service integration. It requires a Perfbase account, a Perfbase API key, and the native `ext-perfbase` PHP extension. The plugin does not submit traces until profiling is enabled and an API key is configured.

The plugin can profile:

* Standard HTTP requests.
* Admin requests when enabled.
* AJAX requests.
* WordPress cron runs.
* WP-CLI commands when enabled.
* WordPress, theme, plugin, REST, and WooCommerce context when available.

For privacy and cardinality control, request URLs are stored without query strings. Important WordPress query parameters are stored separately where useful.

== External Services ==

This plugin connects to the Perfbase APM platform, an external application performance monitoring service, when profiling is enabled.

Service name: Perfbase APM platform and the configured Perfbase ingest API endpoint. The default endpoint is `https://ingress.perfbase.cloud`.

Service policies: [Perfbase Privacy Policy](https://perfbase.com/privacy/) and [Perfbase Terms](https://perfbase.com/terms/).

When data is sent: trace data is sent only after a Perfbase API key is configured, profiling is enabled, the request passes the configured sampling rate, include/exclude filters, user-agent exclusions, request-type toggles, and HTTP status-code rules. For HTTP, AJAX, cron, and WP-CLI lifecycles, submission normally happens at the end of the lifecycle or during shutdown. The plugin does not submit profiling traces while profiling is disabled or while no API key is configured.

Perfbase can send:

* Native trace payloads: function call trees, function names, source file paths and line numbers, timing, CPU, memory, host resource metrics, call context, and runtime error or exception context.
* System/resource data: host operating system, kernel, hostname, CPU architecture, CPU details, disk capacity details, memory usage, CPU usage, disk I/O, and network I/O samples.
* Process-list tracking when enabled: capped process snapshots with process ID, executable basename, OS user, CPU usage, memory usage, and process runtime. Command-line arguments are not included in process snapshots.
* Additional native trace metadata: normalized SQL query text and query type, database DSN/host/database/username/port metadata, MongoDB or Elasticsearch query/filter payload summaries, Redis or Memcached keys and fields, HTTP URL or URI metadata that may include query strings depending on the PHP API or HTTP library used, HTTP method/status/timing/byte-count metadata, file paths and file operation metadata, mail recipient and subject metadata, shell/process command strings, AWS operation names, OPcache and JIT statistics, PHP error or exception samples, compiled file paths, magic method counts, and truncated function argument values if argument capture is separately configured.
* WordPress request metadata: action name, HTTP method, request URL without query string, HTTP status code, user IP address, user agent, logged-in user ID when available, hostname, environment, application version, PHP version, WordPress version, and Perfbase plugin version.
* WordPress context metadata: AJAX action, REST route, admin page, post/page identifiers, post type/status, taxonomy context, template and theme information, conditional page type flags, plugin lifecycle context, and WooCommerce page, cart, product, or order context when available.
* Operational summaries: memory usage, database query count and timing summaries when available, and sanitized outbound HTTP request metadata when HTTP tracking is enabled.

Perfbase does not collect:

* Source code.
* Request bodies, full POST payloads (`$_POST`), arbitrary form fields, or uploaded file contents.
* Cookie values (`$_COOKIE`) or PHP session data (`$_SESSION`).
* Authorization header values.
* Passwords, API keys, nonces, or session IDs from WordPress request, cookie, or session data.
* Command-line arguments for process-list snapshots.

Feature flags control the extra native trace metadata listed under "Perfbase can send", including outbound HTTP URLs or URIs with query strings for some HTTP libraries and truncated function argument values if argument capture is separately configured. Administrators should review enabled Perfbase extension feature flags before profiling sensitive workloads.

Extension installer and CDN: the plugin runtime does not call `cdn.perfbase.com`. The optional extension installer and manual extension binary downloads use `https://cdn.perfbase.com` only when a server administrator downloads or runs those installation assets.

== Installation ==

1. Install the plugin from WordPress.org, or upload the `perfbase-<version>.zip` asset from GitHub Releases through the WordPress plugins screen.
2. Install the native Perfbase PHP extension on the server.
3. Activate the plugin through the Plugins screen in WordPress.
4. Go to Settings -> Perfbase.
5. Add your Perfbase API key.
6. Enable profiling and choose an appropriate sample rate.

Do not use GitHub's automatically generated "Source code" archives for manual WordPress installs. Those archives are source snapshots for developers and do not include production Composer dependencies.

= Installing the Perfbase extension =

The plugin requires the native Perfbase PHP extension. This means shell/server access is required, and the plugin is intended for advanced or server-managed WordPress environments. Many shared hosting and restricted managed WordPress environments do not allow custom PHP extensions; in those environments the plugin can be installed, but profiling will not run until the extension is available.

Automated installer for supported server environments:

`bash -c "$(curl -fsSL https://cdn.perfbase.com/install.sh)"`

The installer performs the same download, checksum, copy, ini-file, and verification steps automatically.

Manual extension installation with `wget`:

1. Find the PHP major/minor version with `php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;'`.
2. Find the CPU architecture with `uname -m`. Use `amd64` for `x86_64`, and `arm64` for `aarch64` or Apple Silicon.
3. Choose the URL pattern for your server:

Debian/Ubuntu and most glibc Linux distributions:

`wget https://cdn.perfbase.com/extension/latest/perfbase-8.3-linux-amd64-gnu-release.so`
`wget https://cdn.perfbase.com/extension/latest/perfbase-8.3-linux-amd64-gnu-release.so.sha256sum.txt`
`sha256sum -c perfbase-8.3-linux-amd64-gnu-release.so.sha256sum.txt`

Alpine Linux:

`wget https://cdn.perfbase.com/extension/latest/perfbase-8.3-linux-amd64-musl-release.so`
`wget https://cdn.perfbase.com/extension/latest/perfbase-8.3-linux-amd64-musl-release.so.sha256sum.txt`
`sha256sum -c perfbase-8.3-linux-amd64-musl-release.so.sha256sum.txt`

macOS:

`wget https://cdn.perfbase.com/extension/latest/perfbase-8.3-darwin-arm64-release.dylib`
`wget https://cdn.perfbase.com/extension/latest/perfbase-8.3-darwin-arm64-release.dylib.sha256sum.txt`
`sha256sum -c perfbase-8.3-darwin-arm64-release.dylib.sha256sum.txt`

Replace `8.3` with your PHP major/minor version and replace `amd64` with `arm64` when using ARM64 Linux.

4. Find PHP's extension directory with `php -i | grep '^extension_dir'`.
5. Find the loaded ini scan directories with `php --ini`.
6. Copy the extension binary into PHP's extension directory.
7. Create `perfbase.ini` in one of the loaded ini scan directories:

`extension=perfbase.ext`

Replace `perfbase.ext` with the downloaded filename, such as `perfbase.so` or `perfbase.dylib`.
8. Restart PHP-FPM, Apache, Nginx Unit, or any long-lived PHP worker.
9. Verify the extension is loaded with `php -m | grep perfbase`.

For a specific pinned build instead of the mutable latest build, replace `/extension/latest/` with a versioned path such as `/extension/v123/`.

== Frequently Asked Questions ==

= Does this plugin work without the Perfbase PHP extension? =

No. The admin settings screen remains available and shows a warning, but profiling requires the native `ext-perfbase` extension.

= Does this plugin send data immediately after activation? =

No. Profiling is disabled by default. Traces are submitted only after you configure an API key and enable profiling.

= Does this plugin require a Perfbase account? =

Yes. Perfbase is a SaaS APM platform and trace submission requires a Perfbase API key.

= What data is sent to Perfbase? =

When enabled, the plugin sends profiling traces and request metadata such as action name, HTTP method, sanitized request URL without query string, HTTP status code, user agent, user IP address, hostname, environment, WordPress version, PHP version, and selected WordPress context.

See the External Services section for the full data disclosure. The key distinction is that Perfbase does not collect source code, request bodies, full POST payloads, arbitrary form fields, uploaded file contents, cookie values, PHP session data, or authorization header values.

= Can I reduce production overhead? =

Yes. Lower the sample rate, disable profiling for admin traffic, and narrow the include and exclude filters.

== Privacy ==

This plugin connects to Perfbase's external service when profiling is enabled. It sends profiling trace data and request metadata to the configured Perfbase API endpoint.

Data submission requires explicit configuration of a Perfbase API key and the Enable Profiling setting. The plugin does not submit traces while profiling is disabled or while no API key is configured.

Administrators should review their site's privacy policy and disclose their use of Perfbase where appropriate.

== Changelog ==

= Unreleased =

* Tagged Perfbase WordPress plugin release.
* Built with production Composer dependencies and tag-synced plugin metadata.

== Upgrade Notice ==

= Unreleased =

Tagged Perfbase WordPress plugin release.
