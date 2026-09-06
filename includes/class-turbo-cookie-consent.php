<?php
/**
 * Consent logic and cookie category management.
 *
 * @package TurboCookie
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Turbo_Cookie_Consent
 *
 * Handles consent state, cookie reading/writing, and consent log database operations.
 *
 * @since 1.0.0
 */
class Turbo_Cookie_Consent {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Schedule daily log cleanup if consent logging is enabled.
		if ( ! wp_next_scheduled( 'turbo_cookie_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'turbo_cookie_cleanup_logs' );
		}
		add_action( 'turbo_cookie_cleanup_logs', array( $this, 'cleanup_expired_logs' ) );
	}

	/**
	 * Check if a visitor has given consent (server-side check).
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function has_consent() {
		$settings    = Turbo_Cookie::get_settings();
		$cookie_name = $settings['cookie_name'];

		return isset( $_COOKIE[ $cookie_name ] );
	}

	/**
	 * Get the current consent state from the cookie.
	 *
	 * @since 1.0.0
	 * @return array|false Consent data array or false if no consent.
	 */
	public function get_consent_state() {
		$settings    = Turbo_Cookie::get_settings();
		$cookie_name = $settings['cookie_name'];

		if ( ! isset( $_COOKIE[ $cookie_name ] ) ) {
			return false;
		}

		$cookie_value = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
		$decoded      = json_decode( base64_decode( $cookie_value ), true );

		if ( ! is_array( $decoded ) ) {
			return false;
		}

		return $decoded;
	}

	/**
	 * Check if a specific category has been consented to.
	 *
	 * @since 1.0.0
	 * @param string $category Category slug (essential, functional, analytics, marketing).
	 * @return bool
	 */
	public function is_category_allowed( $category ) {
		// Essential is always allowed.
		if ( 'essential' === $category ) {
			return true;
		}

		$state = $this->get_consent_state();

		if ( false === $state || ! isset( $state['categories'] ) ) {
			return false;
		}

		return ! empty( $state['categories'][ $category ] );
	}

	/**
	 * Get all allowed category slugs for the current visitor.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_allowed_categories() {
		$state = $this->get_consent_state();

		if ( false === $state || ! isset( $state['categories'] ) ) {
			return array( 'essential' );
		}

		$allowed = array( 'essential' );

		foreach ( $state['categories'] as $slug => $consented ) {
			if ( $consented && 'essential' !== $slug ) {
				$allowed[] = $slug;
			}
		}

		return $allowed;
	}

	/**
	 * Log a consent action to the database.
	 *
	 * @since 1.0.0
	 * @param array $data Consent data.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function log_consent( $data ) {
		global $wpdb;

		$settings = Turbo_Cookie::get_settings();

		// Check if logging is enabled.
		if ( empty( $settings['consent_log_enabled'] ) ) {
			return false;
		}

		$table_name = $wpdb->prefix . TURBO_COOKIE_CONSENT_LOG_TABLE;

		$defaults = array(
			'session_id'      => '',
			'user_id'         => get_current_user_id(),
			'action'          => 'accepted',
			'categories'      => '',
			'ip_address'      => '',
			'country'         => '',
			'user_agent'      => '',
			'page_url'        => '',
			'consent_version' => $settings['reconsent_version'],
		);

		$data = wp_parse_args( $data, $defaults );

		// Mask IP address for privacy (store only first 3 octets for IPv4).
		$data['ip_address'] = $this->mask_ip( $data['ip_address'] );

		// Ensure categories is a string.
		if ( is_array( $data['categories'] ) ) {
			$data['categories'] = wp_json_encode( $data['categories'] );
		}

		// Truncate user agent.
		$data['user_agent'] = substr( $data['user_agent'], 0, 512 );

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table_name,
			array(
				'session_id'      => sanitize_text_field( $data['session_id'] ),
				'user_id'         => absint( $data['user_id'] ),
				'action'          => sanitize_text_field( $data['action'] ),
				'categories'      => $data['categories'],
				'ip_address'      => sanitize_text_field( $data['ip_address'] ),
				'country'         => sanitize_text_field( $data['country'] ),
				'user_agent'      => sanitize_text_field( $data['user_agent'] ),
				'page_url'        => esc_url_raw( $data['page_url'] ),
				'consent_version' => sanitize_text_field( $data['consent_version'] ),
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get consent logs with optional filters and pagination.
	 *
	 * @since 1.0.0
	 * @param array $args Query arguments.
	 * @return array Array with 'items' and 'total' keys.
	 */
	public function get_logs( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'per_page'   => 20,
			'page'       => 1,
			'action'     => '',
			'search'     => '',
			'date_from'  => '',
			'date_to'    => '',
			'order_by'   => 'created_at',
			'order'      => 'DESC',
		);

		$args       = wp_parse_args( $args, $defaults );
		$table_name = $wpdb->prefix . TURBO_COOKIE_CONSENT_LOG_TABLE;
		$where      = array( '1=1' );
		$values     = array();

		// Filter by action.
		if ( ! empty( $args['action'] ) ) {
			$where[]  = 'action = %s';
			$values[] = $args['action'];
		}

		// Search by session_id or ip_address.
		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(session_id LIKE %s OR ip_address LIKE %s)';
			$values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		// Date range.
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = $args['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = $args['date_to'] . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where );

		// Sanitize order.
		$allowed_order_by = array( 'id', 'created_at', 'action', 'session_id' );
		$order_by         = in_array( $args['order_by'], $allowed_order_by, true ) ? $args['order_by'] : 'created_at';
		$order            = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		// Count total.
		$count_sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";
		if ( ! empty( $values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		// Get items.
		$offset  = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );
		$limit   = absint( $args['per_page'] );
		$sql     = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY {$order_by} {$order} LIMIT {$limit} OFFSET {$offset}";

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$items = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Delete a consent log entry by ID.
	 *
	 * @since 1.0.0
	 * @param int $id Log entry ID.
	 * @return bool
	 */
	public function delete_log( $id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . TURBO_COOKIE_CONSENT_LOG_TABLE;

		return (bool) $wpdb->delete( $table_name, array( 'id' => absint( $id ) ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Bulk delete consent logs.
	 *
	 * @since 1.0.0
	 * @param array $ids Array of log IDs.
	 * @return int Number of deleted rows.
	 */
	public function bulk_delete_logs( $ids ) {
		global $wpdb;

		if ( empty( $ids ) ) {
			return 0;
		}

		$table_name   = $wpdb->prefix . TURBO_COOKIE_CONSENT_LOG_TABLE;
		$ids          = array_map( 'absint', $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM {$table_name} WHERE id IN ({$placeholders})",
				...$ids
			)
		);
	}

	/**
	 * Clear all consent logs.
	 *
	 * @since 1.0.0
	 * @return int Number of deleted rows.
	 */
	public function clear_all_logs() {
		global $wpdb;

		$table_name = $wpdb->prefix . TURBO_COOKIE_CONSENT_LOG_TABLE;

		return (int) $wpdb->query( "TRUNCATE TABLE {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	/**
	 * Get total consent log count.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public function get_log_count() {
		global $wpdb;

		$table_name = $wpdb->prefix . TURBO_COOKIE_CONSENT_LOG_TABLE;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Get consent statistics.
	 *
	 * @since 1.0.0
	 * @param int $days Number of days to look back.
	 * @return array
	 */
	public function get_stats( $days = 30 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . TURBO_COOKIE_CONSENT_LOG_TABLE;
		$date_from  = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$stats = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT action, COUNT(*) as count FROM {$table_name} WHERE created_at >= %s GROUP BY action", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$date_from
			),
			ARRAY_A
		);

		$result = array(
			'total'              => 0,
			'accepted'           => 0,
			'declined'           => 0,
			'partial'            => 0,
			'acceptance_rate'    => 0,
		);

		if ( $stats ) {
			foreach ( $stats as $stat ) {
				$result[ $stat['action'] ] = (int) $stat['count'];
				$result['total']          += (int) $stat['count'];
			}

			if ( $result['total'] > 0 ) {
				$result['acceptance_rate'] = round(
					( ( $result['accepted'] + $result['partial'] ) / $result['total'] ) * 100,
					1
				);
			}
		}

		return $result;
	}

	/**
	 * Cleanup expired consent logs based on retention setting.
	 *
	 * @since 1.0.0
	 */
	public function cleanup_expired_logs() {
		global $wpdb;

		$settings  = Turbo_Cookie::get_settings();
		$retention = absint( $settings['consent_log_retention'] );

		if ( $retention <= 0 ) {
			return;
		}

		$table_name = $wpdb->prefix . TURBO_COOKIE_CONSENT_LOG_TABLE;
		$cutoff     = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention} days" ) );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cutoff
			)
		);
	}

	/**
	 * Mask an IP address for privacy.
	 * IPv4: keeps first 3 octets, replaces last with 0.
	 * IPv6: keeps first 4 groups, zeros the rest.
	 *
	 * @since 1.0.0
	 * @param string $ip The IP address to mask.
	 * @return string Masked IP address.
	 */
	private function mask_ip( $ip ) {
		if ( empty( $ip ) ) {
			return '';
		}

		// IPv4.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts    = explode( '.', $ip );
			$parts[3] = '0';
			return implode( '.', $parts );
		}

		// IPv6.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$packed = inet_pton( $ip );
			// Zero out the last 10 bytes (keep first 6 bytes = 48 bits).
			$packed = substr( $packed, 0, 6 ) . str_repeat( "\0", 10 );
			return inet_ntop( $packed );
		}

		return '';
	}

	/**
	 * Get the visitor's IP address.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_visitor_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// May contain multiple IPs separated by commas — take the first.
			$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$ips       = explode( ',', $forwarded );
			$ip        = trim( $ips[0] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		// Validate.
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}

		return '';
	}

	/**
	 * Generate a unique session ID for consent tracking.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function generate_session_id() {
		return wp_generate_uuid4();
	}
}
