<?php
/**
 * Cookie Policy page generator and shortcode.
 *
 * Generates a dynamic cookie policy table from the consent categories
 * and any scanned cookies stored in the database.
 *
 * Shortcode: [turbo_cookie_policy]
 *
 * @package TurboCookie
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Turbo_Cookie_Policy
 *
 * @since 1.1.0
 */
class Turbo_Cookie_Policy {

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	public function __construct() {
		add_shortcode( 'turbo_cookie_policy', array( $this, 'render_policy_table' ) );
		add_action( 'wp_ajax_turbo_cookie_create_policy_page', array( $this, 'ajax_create_policy_page' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue policy styles when the shortcode is present in the current page.
	 *
	 * @since 1.0.1
	 */
	public function enqueue_assets() {
		global $post;

		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'turbo_cookie_policy' ) ) {
			$this->enqueue_styles();
		}
	}

	/**
	 * Enqueue the policy stylesheet.
	 *
	 * @since 1.0.1
	 */
	private function enqueue_styles() {
		wp_enqueue_style(
			'turbo-cookie-policy',
			TURBO_COOKIE_URL . 'frontend/css/turbo-cookie-policy.css',
			array(),
			TURBO_COOKIE_VERSION
		);
	}

	/**
	 * Render the cookie policy table via shortcode.
	 *
	 * @since 1.1.0
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_policy_table( $atts ) {
		$atts = shortcode_atts(
			array(
				'show_essential'  => 'yes',
				'show_functional' => 'yes',
				'show_analytics'  => 'yes',
				'show_marketing'  => 'yes',
			),
			$atts,
			'turbo_cookie_policy'
		);

		// Ensure styles are enqueued even when the shortcode is rendered
		// outside the main post content (widgets, page builders, etc.).
		$this->enqueue_styles();

		$categories = Turbo_Cookie::get_categories();
		$cookies    = $this->get_declared_cookies();

		// Group cookies by category.
		$grouped = array(
			'essential'  => array(),
			'functional' => array(),
			'analytics'  => array(),
			'marketing'  => array(),
		);

		foreach ( $cookies as $cookie ) {
			$cat = $cookie['category'] ?? 'functional';
			if ( isset( $grouped[ $cat ] ) ) {
				$grouped[ $cat ][] = $cookie;
			}
		}

		ob_start();
		?>
		<div class="turbo-cookie-policy" id="turbo-cookie-policy">

			<p class="turbo-cookie-policy-intro">
				<?php esc_html_e( 'This page explains what cookies we use, why we use them, and how you can control them. You can change your cookie preferences at any time using the button below.', 'turbo-cookie-gdpr' ); ?>
			</p>

			<p>
				<button type="button"
					class="turbo-cookie-policy-prefs-btn"
					data-turbo-cookie-action="open-preferences"
					onclick="if(window.TurboCookie){window.TurboCookie.openPreferences();}">
					<?php esc_html_e( 'Manage Cookie Preferences', 'turbo-cookie-gdpr' ); ?>
				</button>
			</p>

			<?php
			$cat_order = array( 'essential', 'functional', 'analytics', 'marketing' );

			foreach ( $cat_order as $slug ) :
				$show_key = 'show_' . $slug;
				if ( 'no' === $atts[ $show_key ] ) continue;

				$cat_data  = $categories[ $slug ] ?? array();
				$cat_name  = $cat_data['name'] ?? ucfirst( $slug );
				$cat_desc  = $cat_data['description'] ?? '';
				$required  = ! empty( $cat_data['required'] );
				$cat_items = $grouped[ $slug ] ?? array();
			?>
			<div class="turbo-cookie-policy-section turbo-cookie-policy-<?php echo esc_attr( $slug ); ?>">
				<h3 class="turbo-cookie-policy-cat-title">
					<?php echo esc_html( $cat_name ); ?>
					<?php if ( $required ) : ?>
						<span class="turbo-cookie-policy-required-badge">
							<?php esc_html_e( 'Always Active', 'turbo-cookie-gdpr' ); ?>
						</span>
					<?php endif; ?>
				</h3>

				<?php if ( ! empty( $cat_desc ) ) : ?>
					<p class="turbo-cookie-policy-cat-desc"><?php echo esc_html( $cat_desc ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $cat_items ) ) : ?>
				<div class="turbo-cookie-policy-table-wrap">
					<table class="turbo-cookie-policy-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Cookie', 'turbo-cookie-gdpr' ); ?></th>
								<th><?php esc_html_e( 'Provider', 'turbo-cookie-gdpr' ); ?></th>
								<th><?php esc_html_e( 'Purpose', 'turbo-cookie-gdpr' ); ?></th>
								<th><?php esc_html_e( 'Expiry', 'turbo-cookie-gdpr' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $cat_items as $item ) : ?>
							<tr>
								<td><code><?php echo esc_html( $item['name'] ); ?></code></td>
								<td><?php echo esc_html( $item['provider'] ?? '' ); ?></td>
								<td><?php echo esc_html( $item['description'] ?? '' ); ?></td>
								<td><?php echo esc_html( $item['duration'] ?? '' ); ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php else : ?>
					<p class="turbo-cookie-policy-empty">
						<?php
						printf(
							/* translators: %s: category name */
							esc_html__( 'No %s cookies are currently declared.', 'turbo-cookie-gdpr' ),
							esc_html( strtolower( $cat_name ) )
						);
						?>
					</p>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>

			<p class="turbo-cookie-policy-updated">
				<?php
				printf(
					/* translators: %s: date */
					esc_html__( 'Last updated: %s', 'turbo-cookie-gdpr' ),
					esc_html( wp_date( get_option( 'date_format' ) ) )
				);
				?>
			</p>

		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get declared cookies from plugin options.
	 * Returns built-in defaults + any saved via Pro scanner.
	 *
	 * @since 1.1.0
	 * @return array
	 */
	private function get_declared_cookies() {
		// Load manually declared cookies (set by Pro scanner or admin).
		$declared = get_option( 'turbo_cookie_declared_cookies', array() );

		// Always include built-in platform defaults.
		$defaults = $this->get_default_cookies();

		// Merge: declared overrides defaults for same cookie name.
		$merged = $defaults;
		foreach ( $declared as $cookie ) {
			$name    = $cookie['name'] ?? '';
			$found   = false;
			foreach ( $merged as $i => $existing ) {
				if ( $existing['name'] === $name ) {
					$merged[ $i ] = $cookie;
					$found        = true;
					break;
				}
			}
			if ( ! $found ) {
				$merged[] = $cookie;
			}
		}

		return $merged;
	}

	/**
	 * Built-in default cookies (WordPress core, this plugin's own consent cookie).
	 *
	 * @since 1.1.0
	 * @return array
	 */
	private function get_default_cookies() {
		$settings    = Turbo_Cookie::get_settings();
		$cookie_name = $settings['cookie_name'] ?? 'turbo_cookie_consent';

		return array(
			array(
				'name'        => $cookie_name,
				'provider'    => get_bloginfo( 'name' ),
				'category'    => 'essential',
				'description' => __( 'Stores the visitor\'s cookie consent preferences.', 'turbo-cookie-gdpr' ),
				'duration'    => $settings['consent_expiry'] . ' ' . __( 'days', 'turbo-cookie-gdpr' ),
			),
			array(
				'name'        => 'wordpress_*',
				'provider'    => 'WordPress',
				'category'    => 'essential',
				'description' => __( 'WordPress authentication cookies for logged-in users.', 'turbo-cookie-gdpr' ),
				'duration'    => __( 'Session / 14 days', 'turbo-cookie-gdpr' ),
			),
			array(
				'name'        => 'wp-settings-*',
				'provider'    => 'WordPress',
				'category'    => 'essential',
				'description' => __( 'WordPress admin interface preferences.', 'turbo-cookie-gdpr' ),
				'duration'    => __( '1 year', 'turbo-cookie-gdpr' ),
			),
		);
	}

	/**
	 * AJAX: Create a Cookie Policy page with the shortcode pre-inserted.
	 *
	 * @since 1.1.0
	 */
	public function ajax_create_policy_page() {
		check_ajax_referer( 'turbo_cookie_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		// Check if page already exists.
		$existing = get_option( 'turbo_cookie_policy_page_id' );
		if ( $existing && get_post( $existing ) ) {
			wp_send_json_success( array(
				'page_id'  => $existing,
				'page_url' => get_permalink( $existing ),
				'message'  => __( 'Cookie Policy page already exists.', 'turbo-cookie-gdpr' ),
			) );
		}

		// Create the page.
		$page_id = wp_insert_post( array(
			'post_title'   => __( 'Cookie Policy', 'turbo-cookie-gdpr' ),
			'post_content' => '<!-- wp:shortcode -->[turbo_cookie_policy]<!-- /wp:shortcode -->',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_author'  => get_current_user_id(),
		) );

		if ( is_wp_error( $page_id ) ) {
			wp_send_json_error( $page_id->get_error_message() );
		}

		// Save page ID + auto-set as banner cookie policy URL if not already set.
		update_option( 'turbo_cookie_policy_page_id', $page_id );

		$banner = Turbo_Cookie::get_banner_settings();
		if ( empty( $banner['privacy_policy_url'] ) ) {
			$banner['privacy_policy_url'] = get_permalink( $page_id );
			update_option( TURBO_COOKIE_BANNER_OPTION, $banner );
		}

		wp_send_json_success( array(
			'page_id'  => $page_id,
			'page_url' => get_permalink( $page_id ),
			'message'  => __( 'Cookie Policy page created successfully.', 'turbo-cookie-gdpr' ),
		) );
	}
}
