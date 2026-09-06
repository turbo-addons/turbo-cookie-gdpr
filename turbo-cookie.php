<?php
/**
 * Plugin Name: Turbo Cookie – GDPR Cookie Consent
 * Description: Lightweight GDPR/CCPA cookie consent with customizable banner, script blocking, Google Consent Mode v2, and local consent logging. 100% free, no visitor limits.
 * Version: 1.0.1
 * Author: Turbo Addons
 * Author URI: https://turbo-addons.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: turbo-cookie-gdpr
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package TurboCookie
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'TURBO_COOKIE_VERSION', '1.0.1' );
define( 'TURBO_COOKIE_FILE', __FILE__ );
define( 'TURBO_COOKIE_DIR', plugin_dir_path( __FILE__ ) );
define( 'TURBO_COOKIE_URL', plugin_dir_url( __FILE__ ) );
define( 'TURBO_COOKIE_BASENAME', plugin_basename( __FILE__ ) );
define( 'TURBO_COOKIE_SLUG', 'turbo-cookie-gdpr' );

// Database constants.
define( 'TURBO_COOKIE_CONSENT_LOG_TABLE', 'turbo_cookie_consent_log' );
define( 'TURBO_COOKIE_DB_VERSION', '1.0.0' );

// Option keys.
define( 'TURBO_COOKIE_SETTINGS_OPTION', 'turbo_cookie_settings' );
define( 'TURBO_COOKIE_BANNER_OPTION', 'turbo_cookie_banner' );
define( 'TURBO_COOKIE_CATEGORIES_OPTION', 'turbo_cookie_categories' );
define( 'TURBO_COOKIE_GCM_OPTION', 'turbo_cookie_gcm' );
define( 'TURBO_COOKIE_ONBOARDING_OPTION', 'turbo_cookie_onboarding_completed' );

// Load the plugin.
require_once TURBO_COOKIE_DIR . 'includes/class-turbo-cookie.php';

/**
 * Initialize the plugin on plugins_loaded.
 *
 * @since 1.0.0
 */
function turbo_cookie_init() {
	Turbo_Cookie::get_instance();
}
add_action( 'plugins_loaded', 'turbo_cookie_init' );

/**
 * Activation hook.
 *
 * @since 1.0.0
 */
function turbo_cookie_activate() {
	require_once TURBO_COOKIE_DIR . 'includes/class-turbo-cookie-installer.php';
	Turbo_Cookie_Installer::activate();
}
register_activation_hook( __FILE__, 'turbo_cookie_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 */
function turbo_cookie_deactivate() {
	require_once TURBO_COOKIE_DIR . 'includes/class-turbo-cookie-installer.php';
	Turbo_Cookie_Installer::deactivate();
}
register_deactivation_hook( __FILE__, 'turbo_cookie_deactivate' );
