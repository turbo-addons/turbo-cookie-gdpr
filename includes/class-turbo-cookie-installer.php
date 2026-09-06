<?php
/**
 * Plugin installer — handles activation, deactivation, and uninstall.
 *
 * @package TurboCookie
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Turbo_Cookie_Installer
 *
 * @since 1.0.0
 */
class Turbo_Cookie_Installer {

	/**
	 * Run on plugin activation.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
		self::set_activation_redirect();

		// Store DB version.
		update_option( 'turbo_cookie_db_version', TURBO_COOKIE_DB_VERSION );

		// Flush rewrite rules for REST endpoints.
		flush_rewrite_rules();
	}

	/**
	 * Run on plugin deactivation.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		// Clear any scheduled events if needed in the future.
		wp_clear_scheduled_hook( 'turbo_cookie_cleanup_logs' );
	}

	/**
	 * Run on plugin uninstall (called from uninstall.php).
	 *
	 * @since 1.0.0
	 */
	public static function uninstall() {
		global $wpdb;

		// Remove all plugin options.
		delete_option( TURBO_COOKIE_SETTINGS_OPTION );
		delete_option( TURBO_COOKIE_BANNER_OPTION );
		delete_option( TURBO_COOKIE_CATEGORIES_OPTION );
		delete_option( TURBO_COOKIE_GCM_OPTION );
		delete_option( TURBO_COOKIE_ONBOARDING_OPTION );
		delete_option( 'turbo_cookie_db_version' );

		// Drop the consent log table.
		$table_name = $wpdb->prefix . TURBO_COOKIE_CONSENT_LOG_TABLE;
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

		// Clear scheduled hooks.
		wp_clear_scheduled_hook( 'turbo_cookie_cleanup_logs' );
	}

	/**
	 * Create the consent log database table.
	 *
	 * @since 1.0.0
	 */
	private static function create_tables() {
		global $wpdb;

		$table_name      = $wpdb->prefix . TURBO_COOKIE_CONSENT_LOG_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(64) NOT NULL DEFAULT '',
			user_id BIGINT(20) UNSIGNED DEFAULT 0,
			action VARCHAR(32) NOT NULL DEFAULT 'accepted',
			categories TEXT NOT NULL,
			ip_address VARCHAR(45) DEFAULT '',
			country VARCHAR(2) DEFAULT '',
			user_agent TEXT,
			page_url VARCHAR(2048) DEFAULT '',
			consent_version VARCHAR(16) DEFAULT '1',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_session_id (session_id),
			KEY idx_action (action),
			KEY idx_created_at (created_at),
			KEY idx_user_id (user_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	/**
	 * Set default option values on first activation.
	 *
	 * @since 1.0.0
	 */
	private static function set_default_options() {
		// Only set defaults if not already configured (protects re-activation).
		if ( false === get_option( TURBO_COOKIE_SETTINGS_OPTION ) ) {
			$default_settings = array(
				'enabled'               => true,
				'script_blocking'       => true,
				'gcm_enabled'           => true,
				'consent_expiry'        => 365,
				'show_on_pages'         => 'all',
				'excluded_pages'        => '',
				'consent_log_enabled'   => true,
				'consent_log_retention' => 365,
				'cookie_name'           => 'turbo_cookie_consent',
				'reconsent_version'     => '1',
				'show_credit'           => false,
			);
			add_option( TURBO_COOKIE_SETTINGS_OPTION, $default_settings );
		}

		if ( false === get_option( TURBO_COOKIE_BANNER_OPTION ) ) {
			$default_banner = array(
				'position'            => 'bottom',
				'layout'              => 'bar',
				'width'               => 'full',
				'title'               => 'We value your privacy',
				'message'             => 'We use cookies to enhance your browsing experience, serve personalized content, and analyze our traffic. By clicking "Accept All", you consent to our use of cookies.',
				'privacy_policy_url'  => '',
				'privacy_policy_text' => 'Cookie Policy',
				'accept_text'         => 'Accept All',
				'decline_text'        => 'Decline',
				'preferences_text'    => 'Preferences',
				'save_text'           => 'Save Preferences',
				'show_decline'        => true,
				'bg_color'            => '#1e293b',
				'text_color'          => '#f1f5f9',
				'btn_accept_bg'       => '#3b82f6',
				'btn_accept_text'     => '#ffffff',
				'btn_decline_bg'      => 'transparent',
				'btn_decline_text'    => '#94a3b8',
				'btn_decline_border'  => '#475569',
				'btn_prefs_bg'        => 'transparent',
				'btn_prefs_text'      => '#94a3b8',
				'btn_prefs_border'    => '#475569',
				'animation'           => 'slide',
				'show_logo'           => false,
				'logo_url'            => '',
			);
			add_option( TURBO_COOKIE_BANNER_OPTION, $default_banner );
		}

		if ( false === get_option( TURBO_COOKIE_CATEGORIES_OPTION ) ) {
			$default_categories = array(
				'essential'  => array(
					'name'        => 'Essential',
					'description' => 'Essential cookies are required for the website to function properly. These cookies ensure basic functionalities and security features.',
					'required'    => true,
					'enabled'     => true,
				),
				'functional' => array(
					'name'        => 'Functional',
					'description' => 'Functional cookies help perform certain functionalities like sharing website content on social media, collecting feedback, and other third-party features.',
					'required'    => false,
					'enabled'     => true,
				),
				'analytics'  => array(
					'name'        => 'Analytics',
					'description' => 'Analytics cookies help us understand how visitors interact with the website. These cookies help provide information on the number of visitors, bounce rate, traffic source, etc.',
					'required'    => false,
					'enabled'     => true,
				),
				'marketing'  => array(
					'name'        => 'Marketing',
					'description' => 'Marketing cookies are used to deliver advertisements relevant to you and your interests. They also help limit the number of times you see an ad and measure the effectiveness of advertising campaigns.',
					'required'    => false,
					'enabled'     => true,
				),
			);
			add_option( TURBO_COOKIE_CATEGORIES_OPTION, $default_categories );
		}

		if ( false === get_option( TURBO_COOKIE_GCM_OPTION ) ) {
			$default_gcm = array(
				'enabled'                  => true,
				'wait_for_update'          => 500,
				'ad_storage'               => 'denied',
				'ad_user_data'             => 'denied',
				'ad_personalization'       => 'denied',
				'analytics_storage'        => 'denied',
				'functionality_storage'    => 'denied',
				'personalization_storage'  => 'denied',
				'security_storage'         => 'granted',
				'map_analytics'            => 'analytics',
				'map_marketing'            => 'marketing',
				'map_functional'           => 'functional',
			);
			add_option( TURBO_COOKIE_GCM_OPTION, $default_gcm );
		}
	}

	/**
	 * Set transient to redirect to onboarding after activation.
	 *
	 * @since 1.0.0
	 */
	private static function set_activation_redirect() {
		// Only redirect if onboarding not yet completed.
		if ( ! get_option( TURBO_COOKIE_ONBOARDING_OPTION ) ) {
			set_transient( 'turbo_cookie_activation_redirect', true, 30 );
		}
	}
}
