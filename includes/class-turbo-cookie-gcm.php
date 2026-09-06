<?php
/**
 * Google Consent Mode v2 integration.
 *
 * @package TurboCookie
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Turbo_Cookie_GCM
 *
 * Outputs Google Consent Mode v2 default and update commands.
 * Integrates with the frontend consent flow to update consent signals
 * when visitors make their choice.
 *
 * @since 1.0.0
 */
class Turbo_Cookie_GCM {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	private function register_hooks() {
		$settings     = Turbo_Cookie::get_settings();
		$gcm_settings = Turbo_Cookie::get_gcm_settings();

		// Only output GCM if both plugin and GCM are enabled.
		if ( empty( $settings['enabled'] ) || empty( $gcm_settings['enabled'] ) ) {
			return;
		}

		// Don't output on admin pages.
		if ( is_admin() ) {
			return;
		}

		// Register a placeholder script so we can attach inline JS to it via wp_add_inline_script().
		// Priority 1 ensures it loads before any Google tags in wp_head.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_gcm_script' ), 1 );
	}

	/**
	 * Register and output the Google Consent Mode v2 default script via wp_add_inline_script().
	 *
	 * This must execute BEFORE any Google tags (gtag.js, GTM) load on the page.
	 * We register a dependency-free placeholder handle and attach the inline
	 * consent default to it so WordPress manages the output through the
	 * standard script queue rather than raw echo in wp_head.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_gcm_script() {
		$gcm_settings = Turbo_Cookie::get_gcm_settings();

		// Build the default consent state.
		$defaults = array(
			'ad_storage'              => $gcm_settings['ad_storage'],
			'ad_user_data'            => $gcm_settings['ad_user_data'],
			'ad_personalization'      => $gcm_settings['ad_personalization'],
			'analytics_storage'       => $gcm_settings['analytics_storage'],
			'functionality_storage'   => $gcm_settings['functionality_storage'],
			'personalization_storage' => $gcm_settings['personalization_storage'],
			'security_storage'        => $gcm_settings['security_storage'],
			'wait_for_update'         => absint( $gcm_settings['wait_for_update'] ),
		);

		/**
		 * Filter the GCM category mapping.
		 *
		 * @since 1.0.0
		 * @param array $mapping Category-to-signal mapping.
		 */
		$mapping = apply_filters(
			'turbo_cookie_gcm_mapping',
			array(
				'analytics'  => array( 'analytics_storage' ),
				'marketing'  => array( 'ad_storage', 'ad_user_data', 'ad_personalization' ),
				'functional' => array( 'functionality_storage', 'personalization_storage' ),
			)
		);

		// Register an empty placeholder script with no src — gives us a handle to attach inline JS to.
		wp_register_script( 'turbo-cookie-gcm', false, array(), null, false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_script( 'turbo-cookie-gcm' );

		// Build the inline JS — wp_json_encode output is safe to embed directly in JS context.
		$inline_js = sprintf(
			"window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('consent','default',%s);window.turboCookieGCMMapping=%s;",
			wp_json_encode( $defaults ),
			wp_json_encode( $mapping )
		);

		wp_add_inline_script( 'turbo-cookie-gcm', $inline_js );
	}

	/**
	 * Get the GCM update payload based on consented categories.
	 *
	 * Used by the frontend JS to call gtag('consent', 'update', {...}).
	 *
	 * @since 1.0.0
	 * @param array $consented_categories Array of consented category slugs.
	 * @return array GCM consent update payload.
	 */
	public static function get_update_payload( $consented_categories ) {
		$mapping = array(
			'analytics'  => array( 'analytics_storage' ),
			'marketing'  => array( 'ad_storage', 'ad_user_data', 'ad_personalization' ),
			'functional' => array( 'functionality_storage', 'personalization_storage' ),
		);

		/** This filter is documented in class-turbo-cookie-gcm.php */
		$mapping = apply_filters( 'turbo_cookie_gcm_mapping', $mapping );

		$payload = array(
			'ad_storage'             => 'denied',
			'ad_user_data'           => 'denied',
			'ad_personalization'     => 'denied',
			'analytics_storage'      => 'denied',
			'functionality_storage'  => 'denied',
			'personalization_storage' => 'denied',
			'security_storage'       => 'granted', // Always granted.
		);

		foreach ( $consented_categories as $category ) {
			if ( isset( $mapping[ $category ] ) ) {
				foreach ( $mapping[ $category ] as $signal ) {
					$payload[ $signal ] = 'granted';
				}
			}
		}

		return $payload;
	}
}
