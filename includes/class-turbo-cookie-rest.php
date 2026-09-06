<?php
/**
 * REST API endpoints for consent logging.
 *
 * @package TurboCookie
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Turbo_Cookie_REST
 *
 * Registers REST API endpoints for frontend consent logging.
 *
 * @since 1.0.0
 */
class Turbo_Cookie_REST {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'turbo-cookie/v1';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		// Log consent (public — called from frontend JS).
		register_rest_route(
			self::NAMESPACE,
			'/consent',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'log_consent' ),
				'permission_callback' => '__return_true', // Public endpoint.
				'args'                => array(
					'action'     => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'accepted', 'declined', 'partial' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'categories' => array(
						'required'          => true,
						'type'              => 'object',
						'sanitize_callback' => array( $this, 'sanitize_categories' ),
					),
					'session_id' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page_url'   => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		// Domain ownership verification — called by Turbo Cookie API during Pro registration.
		// The API GETs this endpoint to confirm domain control before issuing an API key.
		// Token is a 64-char hex string stored temporarily as a transient by the Pro plugin.
		register_rest_route(
			self::NAMESPACE,
			'/verify/(?P<token>[a-f0-9]{64})',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'verify_domain' ),
				'permission_callback' => '__return_true',
			)
		);

		// Get consent stats (admin only).
		register_rest_route(
			self::NAMESPACE,
			'/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
				'args'                => array(
					'days' => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 30,
					),
				),
			)
		);

		// Get consent logs (admin only).
		register_rest_route(
			self::NAMESPACE,
			'/logs',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_logs' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
				'args'                => array(
					'page'      => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 1,
					),
					'per_page'  => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 20,
					),
					'action'    => array(
						'required' => false,
						'type'     => 'string',
					),
					'search'    => array(
						'required' => false,
						'type'     => 'string',
					),
					'date_from' => array(
						'required' => false,
						'type'     => 'string',
					),
					'date_to'   => array(
						'required' => false,
						'type'     => 'string',
					),
				),
			)
		);

		// Delete consent log(s) (admin only).
		register_rest_route(
			self::NAMESPACE,
			'/logs',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_logs' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
				'args'                => array(
					'ids' => array(
						'required' => false,
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
					),
					'all' => array(
						'required' => false,
						'type'     => 'boolean',
						'default'  => false,
					),
				),
			)
		);
	}

	/**
	 * Log consent from frontend.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function log_consent( $request ) {
		$consent = new Turbo_Cookie_Consent();

		$data = array(
			'session_id' => $request->get_param( 'session_id' ) ?: Turbo_Cookie_Consent::generate_session_id(),
			'action'     => $request->get_param( 'action' ),
			'categories' => $request->get_param( 'categories' ),
			'ip_address' => Turbo_Cookie_Consent::get_visitor_ip(),
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'page_url'   => $request->get_param( 'page_url' ) ?: '',
		);

		$log_id = $consent->log_consent( $data );

		if ( false === $log_id ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Consent recorded (logging disabled or table missing).',
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'log_id'  => $log_id,
			),
			201
		);
	}

	/**
	 * Get consent statistics.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_stats( $request ) {
		$consent = new Turbo_Cookie_Consent();
		$days    = $request->get_param( 'days' );
		$stats   = $consent->get_stats( $days );

		return new WP_REST_Response( $stats, 200 );
	}

	/**
	 * Get consent logs.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_logs( $request ) {
		$consent = new Turbo_Cookie_Consent();

		$args = array(
			'page'      => $request->get_param( 'page' ),
			'per_page'  => $request->get_param( 'per_page' ),
			'action'    => $request->get_param( 'action' ),
			'search'    => $request->get_param( 'search' ),
			'date_from' => $request->get_param( 'date_from' ),
			'date_to'   => $request->get_param( 'date_to' ),
		);

		$result = $consent->get_logs( $args );

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Delete consent logs.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function delete_logs( $request ) {
		$consent = new Turbo_Cookie_Consent();

		if ( $request->get_param( 'all' ) ) {
			$deleted = $consent->clear_all_logs();
			return new WP_REST_Response(
				array(
					'success' => true,
					'deleted' => $deleted,
					'message' => 'All consent logs cleared.',
				),
				200
			);
		}

		$ids = $request->get_param( 'ids' );
		if ( empty( $ids ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'No IDs provided.',
				),
				400
			);
		}

		$deleted = $consent->bulk_delete_logs( $ids );

		return new WP_REST_Response(
			array(
				'success' => true,
				'deleted' => $deleted,
			),
			200
		);
	}

	/**
	 * Domain verification endpoint for Pro registration handshake.
	 *
	 * The Turbo Cookie API calls GET /wp-json/turbo-cookie/v1/verify/{token}
	 * to confirm this site owns the domain before issuing an API key.
	 * The Pro plugin stores the token as a transient; we return it here.
	 *
	 * @since 1.1.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function verify_domain( $request ) {
		$token   = sanitize_text_field( $request->get_param( 'token' ) );
		$stored  = get_transient( 'turbo_cookie_pro_verify_' . $token );

		if ( empty( $stored ) || $stored !== $token ) {
			return new WP_REST_Response(
				array( 'error' => 'not_found' ),
				404
			);
		}

		// Single use — delete immediately.
		delete_transient( 'turbo_cookie_pro_verify_' . $token );

		return new WP_REST_Response(
			array(
				'token'    => $token,
				'verified' => true,
				'site_url' => home_url(),
			),
			200
		);
	}

	/**
	 * Admin permission check.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function admin_permission_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Sanitize categories parameter.
	 *
	 * @since 1.0.0
	 * @param mixed $value The value to sanitize.
	 * @return array
	 */
	public function sanitize_categories( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $value as $key => $val ) {
			$sanitized[ sanitize_key( $key ) ] = (bool) $val;
		}

		return $sanitized;
	}
}
