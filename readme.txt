=== Turbo Cookie – GDPR Cookie Consent ===
Contributors: turboaddons
Tags: cookie consent, gdpr, ccpa, cookie banner, privacy
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 1.0.1
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight GDPR/CCPA cookie consent with customizable banner, script blocking, Google Consent Mode v2, and local consent logging. 100% free.

== Description ==

Turbo Cookie is a lightweight, privacy-first cookie consent plugin for WordPress. It helps you comply with GDPR, CCPA, and other privacy regulations by showing a customizable cookie consent banner, blocking non-essential scripts until visitors give consent, and logging consent records locally in your WordPress database.

Built by Turbo Addons — the same team behind popular WordPress tools used by thousands of sites.

= Cookie Consent Banner =

* Customizable position: bottom bar, top bar, floating (bottom-left/right), or center modal
* Layout styles: bar, box, or cloud
* Smooth animations: slide, fade, or none
* Full color customization for background, text, and buttons
* Custom banner message and title
* Cookie policy page link
* Show/hide decline button

= Preferences Modal =

* Four consent categories: Essential, Functional, Analytics, Marketing
* Toggle switches for each category
* Essential cookies always active (cannot be disabled)
* Editable category names and descriptions
* Accessible — ARIA roles, keyboard navigation, focus trap

= Script Blocking =

* Automatically detects and blocks known tracking scripts until consent is given
* Blocks Google Analytics, Tag Manager, Facebook Pixel, and 40+ services
* Blocks iframes (YouTube, Vimeo, Google Maps) until consent
* Scripts activate instantly after visitor consents
* Works with both inline scripts and enqueued WordPress scripts
* No manual script tagging required for common services
* Extend via the `turbo_cookie_script_patterns` filter

= Google Consent Mode v2 =

* Outputs consent default signals before any Google tags load
* Updates consent signals when visitors make their choice
* Supports all GCM v2 parameters: ad_storage, ad_user_data, ad_personalization, analytics_storage, functionality_storage, personalization_storage, security_storage
* Extend category-to-signal mapping via `turbo_cookie_gcm_mapping` filter

= Consent Logging =

* Stores consent records in your WordPress database — no data sent externally
* Logs session ID, action, categories, masked IP, and timestamp
* IP addresses masked for privacy (last octet zeroed for IPv4; first 48 bits kept for IPv6)
* Filterable log viewer in admin with date range and action filters
* CSV export for compliance documentation
* Configurable retention period with automatic cleanup
* Bulk delete and clear all options

= Admin Dashboard =

* At-a-glance consent statistics: accepted, declined, partial, acceptance rate (30-day window)
* Feature status overview
* Quick action links to all settings pages
* Privacy notice reminding site owners of their legal obligations

= Onboarding Wizard =

* 3-step setup wizard runs on first activation
* Choose banner position, enable features, set cookie policy URL
* Ready in under 2 minutes

= Developer Friendly =

* WordPress coding standards throughout
* Shortcode: `[turbo_cookie_preferences]` — lets visitors re-open preferences modal
* JavaScript API: `window.TurboCookie.openPreferences()`
* Filterable script blocking patterns: `turbo_cookie_script_patterns`
* Filterable GCM mapping: `turbo_cookie_gcm_mapping`
* REST API endpoint for consent logging: `POST /wp-json/turbo-cookie/v1/consent`

= Privacy =

Turbo Cookie does not send any visitor or site data to external servers. All consent records are stored in your own WordPress database. IP addresses are masked before storage. No telemetry. No tracking. No account required.

= Legal Notice =

Turbo Cookie provides technical tools for cookie consent management. Whether your site meets applicable privacy laws depends on your specific site, policies, and data practices. For legal advice about your compliance obligations, consult a qualified legal professional.

== Installation ==

1. Upload the `turbo-cookie-gdpr` folder to `/wp-content/plugins/` or install via the WordPress Plugins screen.
2. Activate Turbo Cookie.
3. Follow the onboarding wizard to configure your banner.

= After Installation =

* Customize banner: **Turbo Cookie > Banner Design**
* Edit cookie categories: **Turbo Cookie > Categories**
* View consent records: **Turbo Cookie > Consent Logs**
* Adjust settings: **Turbo Cookie > Settings**

= Re-open Preferences =

Add this shortcode anywhere on your site to let visitors change their cookie preferences:

`[turbo_cookie_preferences text="Cookie Settings"]`

Or use the JavaScript API:

`window.TurboCookie.openPreferences();`

== Frequently Asked Questions ==

= Is Turbo Cookie really 100% free? =

Yes. All features are included with no visitor limits, no paywalls, and no feature gating.

= Does Turbo Cookie actually block scripts? =

Yes. Turbo Cookie intercepts and blocks known tracking scripts and iframes until the visitor consents to the relevant category. This is real blocking, not just a cosmetic banner.

= Does it support Google Consent Mode v2? =

Yes. Consent default signals are output before any Google tags load, then updated when visitors make their choice. Works with Google Analytics 4, Google Ads, and Google Tag Manager.

= Where are consent logs stored? =

In your WordPress database in a dedicated table. No data is sent to external services.

= How does IP masking work? =

For IPv4, the last octet is replaced with 0 (e.g., 192.168.1.0). For IPv6, only the first 48 bits are kept.

= Can visitors change their consent later? =

Yes. Use the `[turbo_cookie_preferences]` shortcode or `window.TurboCookie.openPreferences()` anywhere on your site.

= Does it work with caching plugins? =

Yes. The banner and script blocking run client-side via JavaScript, so they work correctly with page caching.

= Does Turbo Cookie slow down my site? =

No. Frontend assets are under 15KB combined and load asynchronously.

= Can I extend the script blocking list? =

Yes. Use the `turbo_cookie_script_patterns` filter to add your own patterns or remove existing ones.

= What happens when a visitor declines? =

Non-essential scripts remain blocked, Google Consent Mode signals stay denied, and a consent record is logged locally. Only essential cookies remain active.

== Screenshots ==

1. Cookie consent banner — bottom bar position
2. Preferences modal with category toggles
3. Admin dashboard with consent statistics
4. Banner design customizer
5. Cookie categories management
6. Consent logs viewer with CSV export
7. Onboarding wizard

== External Services ==

Turbo Cookie does not make any server-to-server calls and does not send your site data to any external server. Consent records, settings, and cookie state are stored entirely in your own WordPress database and browser cookies.

The plugin interacts with third-party services in the following two ways only:

= Google Consent Mode v2 (optional, on by default) =

When enabled, Turbo Cookie outputs Google Consent Mode v2 signals in the visitor's browser. These signals communicate the visitor's consent state to Google services that you have added to your site (for example Google Analytics 4, Google Ads, or Google Tag Manager). The plugin does not load Google services itself — it only declares and later updates the consent state for the Google tags you have configured.

What data is sent and when: the visitor's consent choices (granted or denied for each consent category) are sent to Google's services in the browser when the visitor interacts with the banner, and only for the Google tags present on the page.

This service is provided by Google LLC: privacy policy (https://policies.google.com/privacy) and terms of service (https://policies.google.com/terms).

= Script Blocking (optional, on by default) =

The script blocker recognizes and temporarily blocks scripts, iframes, and embeds from well-known third-party services until the visitor consents. Recognition is performed locally on your WordPress server by matching service domain names in the page output — no data is sent to those services by the plugin. Once the visitor consents, the blocked content is loaded in their browser by the original third-party service.

Blocked services include (but are not limited to): Google Analytics, Google Tag Manager, Google Maps, Facebook/Meta SDK and Pixel, and 40+ others.

No visitor or site data is transmitted by Turbo Cookie to any of these services. Data is only exchanged between the visitor's browser and the third-party service after the visitor consents and the original script or embed is restored.

== Changelog ==

= 1.0.1 =
* Harden banner color sanitization to prevent CSS injection.
* Load cookie policy styles via wp_enqueue_style instead of inline output.
* Explicitly close the script-blocking output buffer on shutdown.
* Document Google Consent Mode v2 and blocked third-party services.
* Align the text domain with the plugin slug (turbo-cookie-gdpr).

= 1.0.0 =
* Initial release.
* Cookie consent banner with 5 position options.
* Preferences modal with 4 cookie categories.
* Script blocking for 40+ known tracking services.
* Google Consent Mode v2 — all 7 parameters supported.
* Consent logging with IP masking stored in local WordPress database.
* Admin dashboard with 30-day consent statistics.
* Banner customizer with full color control.
* Cookie categories manager.
* Onboarding wizard.
* CSV export for consent logs.
* Shortcode [turbo_cookie_preferences] and JavaScript API.
* REST endpoint for frontend consent logging.

== Upgrade Notice ==

= 1.0.0 =
Initial release of Turbo Cookie.
