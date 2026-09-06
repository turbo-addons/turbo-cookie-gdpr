<?php
/**
 * Main plugin orchestrator class.
 *
 * @package TurboCookie
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Turbo_Cookie
 *
 * Singleton that bootstraps all plugin components.
 *
 * @since 1.0.0
 */
class Turbo_Cookie {

	/**
	 * Singleton instance.
	 *
	 * @var Turbo_Cookie|null
	 */
	private static $instance = null;

	/**
	 * Admin class instance.
	 *
	 * @var Turbo_Cookie_Admin|null
	 */
	public $admin = null;

	/**
	 * Frontend class instance.
	 *
	 * @var Turbo_Cookie_Frontend|null
	 */
	public $frontend = null;

	/**
	 * Consent handler instance.
	 *
	 * @var Turbo_Cookie_Consent|null
	 */
	public $consent = null;

	/**
	 * Script blocker instance.
	 *
	 * @var Turbo_Cookie_Script_Blocker|null
	 */
	public $script_blocker = null;

	/**
	 * Google Consent Mode instance.
	 *
	 * @var Turbo_Cookie_GCM|null
	 */
	public $gcm = null;

	/**
	 * REST API instance.
	 *
	 * @var Turbo_Cookie_REST|null
	 */
	public $rest = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return Turbo_Cookie
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_components();
		$this->register_hooks();
	}

	/**
	 * Load required files.
	 *
	 * @since 1.0.0
	 */
	private function load_dependencies() {
		// Core includes.
		require_once TURBO_COOKIE_DIR . 'includes/class-turbo-cookie-consent.php';
		require_once TURBO_COOKIE_DIR . 'includes/class-turbo-cookie-script-blocker.php';
		require_once TURBO_COOKIE_DIR . 'includes/class-turbo-cookie-gcm.php';
		require_once TURBO_COOKIE_DIR . 'includes/class-turbo-cookie-rest.php';
		require_once TURBO_COOKIE_DIR . 'includes/class-turbo-cookie-policy.php';

		// Admin files.
		if ( is_admin() ) {
			require_once TURBO_COOKIE_DIR . 'admin/class-turbo-cookie-admin.php';
		}

		// Frontend files.
		if ( ! is_admin() || wp_doing_ajax() ) {
			require_once TURBO_COOKIE_DIR . 'frontend/class-turbo-cookie-frontend.php';
		}
	}

	/**
	 * Initialize components.
	 *
	 * @since 1.0.0
	 */
	private function init_components() {
		$this->consent        = new Turbo_Cookie_Consent();
		$this->script_blocker = new Turbo_Cookie_Script_Blocker();
		$this->gcm            = new Turbo_Cookie_GCM();
		$this->rest           = new Turbo_Cookie_REST();
		new Turbo_Cookie_Policy();

		if ( is_admin() ) {
			$this->admin = new Turbo_Cookie_Admin();
		}

		if ( ! is_admin() || wp_doing_ajax() ) {
			$this->frontend = new Turbo_Cookie_Frontend();
		}
	}

	/**
	 * Register global hooks.
	 *
	 * @since 1.0.0
	 */
	private function register_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_filter( 'plugin_action_links_' . TURBO_COOKIE_BASENAME, array( $this, 'add_action_links' ) );
	}

	/**
	 * Load plugin textdomain.
	 *
	 * Since WordPress 4.6, translations are loaded automatically for plugins
	 * hosted on WordPress.org. Manual load_plugin_textdomain() is not needed.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		// WordPress 4.6+ loads translations automatically for .org plugins.
		// No manual call needed.
	}

	/**
	 * Add settings link to plugins page.
	 *
	 * @since 1.0.0
	 * @param array $links Existing plugin action links.
	 * @return array Modified links.
	 */
	public function add_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=turbo-cookie' ) ),
			esc_html__( 'Settings', 'turbo-cookie-gdpr' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Get plugin settings with defaults.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_settings() {
		$defaults = array(
			'enabled'              => true,
			'script_blocking'      => true,
			'gcm_enabled'          => true,
			'consent_expiry'       => 365,
			'show_on_pages'        => 'all',
			'excluded_pages'       => '',
			'consent_log_enabled'  => true,
			'consent_log_retention' => 365,
			'cookie_name'          => 'turbo_cookie_consent',
			'reconsent_version'    => '1',
			'show_credit'          => false,
		);

		$settings = get_option( TURBO_COOKIE_SETTINGS_OPTION, array() );
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Get banner settings with defaults.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_banner_settings() {
		$defaults = array(
			// Layout.
			'position'           => 'bottom',
			'layout'             => 'bar',
			'width'              => 'full',
			// Content.
			'title'              => __( 'We value your privacy', 'turbo-cookie-gdpr' ),
			'message'            => __( 'We use cookies to enhance your browsing experience, serve personalized content, and analyze our traffic. By clicking "Accept All", you consent to our use of cookies.', 'turbo-cookie-gdpr' ),
			'privacy_policy_url' => '',
			'privacy_policy_text' => __( 'Cookie Policy', 'turbo-cookie-gdpr' ),
			// Buttons.
			'accept_text'        => __( 'Accept All', 'turbo-cookie-gdpr' ),
			'decline_text'       => __( 'Decline', 'turbo-cookie-gdpr' ),
			'preferences_text'   => __( 'Preferences', 'turbo-cookie-gdpr' ),
			'save_text'          => __( 'Save Preferences', 'turbo-cookie-gdpr' ),
			'show_decline'       => true,
			// Colors.
			'bg_color'           => '#1e293b',
			'text_color'         => '#f1f5f9',
			'btn_accept_bg'      => '#3b82f6',
			'btn_accept_text'    => '#ffffff',
			'btn_decline_bg'     => 'transparent',
			'btn_decline_text'   => '#94a3b8',
			'btn_decline_border' => '#475569',
			'btn_prefs_bg'       => 'transparent',
			'btn_prefs_text'     => '#94a3b8',
			'btn_prefs_border'   => '#475569',
			// Animation.
			'animation'          => 'slide',
			// Logo.
			'show_logo'          => false,
			'logo_url'           => '',
		);

		$settings = get_option( TURBO_COOKIE_BANNER_OPTION, array() );
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Get cookie categories with defaults.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_categories() {
		$defaults = array(
			'essential'  => array(
				'name'        => __( 'Essential', 'turbo-cookie-gdpr' ),
				'description' => __( 'Essential cookies are required for the website to function properly. These cookies ensure basic functionalities and security features.', 'turbo-cookie-gdpr' ),
				'required'    => true,
				'enabled'     => true,
			),
			'functional' => array(
				'name'        => __( 'Functional', 'turbo-cookie-gdpr' ),
				'description' => __( 'Functional cookies help perform certain functionalities like sharing website content on social media, collecting feedback, and other third-party features.', 'turbo-cookie-gdpr' ),
				'required'    => false,
				'enabled'     => true,
			),
			'analytics'  => array(
				'name'        => __( 'Analytics', 'turbo-cookie-gdpr' ),
				'description' => __( 'Analytics cookies help us understand how visitors interact with the website. These cookies help provide information on the number of visitors, bounce rate, traffic source, etc.', 'turbo-cookie-gdpr' ),
				'required'    => false,
				'enabled'     => true,
			),
			'marketing'  => array(
				'name'        => __( 'Marketing', 'turbo-cookie-gdpr' ),
				'description' => __( 'Marketing cookies are used to deliver advertisements relevant to you and your interests. They also help limit the number of times you see an ad and measure the effectiveness of advertising campaigns.', 'turbo-cookie-gdpr' ),
				'required'    => false,
				'enabled'     => true,
			),
		);

		$categories = get_option( TURBO_COOKIE_CATEGORIES_OPTION, array() );

		if ( empty( $categories ) ) {
			return $defaults;
		}

		return $categories;
	}

	/**
	 * Get GCM (Google Consent Mode) settings with defaults.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_gcm_settings() {
		$defaults = array(
			'enabled'              => true,
			'wait_for_update'      => 500,
			'ad_storage'           => 'denied',
			'ad_user_data'         => 'denied',
			'ad_personalization'   => 'denied',
			'analytics_storage'    => 'denied',
			'functionality_storage' => 'denied',
			'personalization_storage' => 'denied',
			'security_storage'     => 'granted',
			// Category mappings.
			'map_analytics'        => 'analytics',
			'map_marketing'        => 'marketing',
			'map_functional'       => 'functional',
		);

		$settings = get_option( TURBO_COOKIE_GCM_OPTION, array() );
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Sanitize a CSS color value.
	 *
	 * Only allows the `transparent` keyword or a valid hex color, preventing
	 * arbitrary CSS injection through banner color fields.
	 *
	 * @since 1.0.1
	 * @param string $value    Raw color value.
	 * @param string $fallback Fallback color used when the value is invalid.
	 * @return string Safe CSS color value.
	 */
	public static function sanitize_css_color( $value, $fallback = '#3b82f6' ) {
		$value = strtolower( trim( (string) $value ) );

		if ( 'transparent' === $value ) {
			return 'transparent';
		}

		$hex = sanitize_hex_color( $value );

		return ( $hex ) ? $hex : $fallback;
	}
}
