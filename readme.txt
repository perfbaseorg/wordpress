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

== Installation ==

1. Install the plugin from WordPress.org, or upload the `perfbase-<version>.zip` asset from GitHub Releases through the WordPress plugins screen.
2. Install the native Perfbase PHP extension on the server.
3. Activate the plugin through the Plugins screen in WordPress.
4. Go to Settings -> Perfbase.
5. Add your Perfbase API key.
6. Enable profiling and choose an appropriate sample rate.

Do not use GitHub's automatically generated "Source code" archives for manual WordPress installs. Those archives are source snapshots for developers and do not include production Composer dependencies.

= Installing the Perfbase extension =

The plugin requires the native Perfbase PHP extension. Install it with:

`bash -c "$(curl -fsSL https://cdn.perfbase.com/install.sh)"`

Restart PHP-FPM, Apache, Nginx Unit, or any long-lived PHP worker after installing the extension.

== Frequently Asked Questions ==

= Does this plugin work without the Perfbase PHP extension? =

No. The admin settings screen remains available and shows a warning, but profiling requires the native `ext-perfbase` extension.

= Does this plugin send data immediately after activation? =

No. Profiling is disabled by default. Traces are submitted only after you configure an API key and enable profiling.

= Does this plugin require a Perfbase account? =

Yes. Perfbase is a SaaS APM platform and trace submission requires a Perfbase API key.

= What data is sent to Perfbase? =

When enabled, the plugin sends profiling traces and request metadata such as action name, HTTP method, sanitized URL, HTTP status code, user agent, user IP, hostname, environment, WordPress version, PHP version, and selected WordPress context. Query strings are removed from URLs before submission.

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
