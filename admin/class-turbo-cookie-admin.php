<?php
/**
 * Admin dashboard, settings, and banner customizer.
 *
 * @package TurboCookie
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Turbo_Cookie_Admin
 *
 * @since 1.0.0
 */
class Turbo_Cookie_Admin {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_activation_redirect' ) );
		add_action( 'wp_ajax_turbo_cookie_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_turbo_cookie_save_banner', array( $this, 'ajax_save_banner' ) );
		add_action( 'wp_ajax_turbo_cookie_save_categories', array( $this, 'ajax_save_categories' ) );
		add_action( 'wp_ajax_turbo_cookie_save_gcm', array( $this, 'ajax_save_gcm' ) );
		add_action( 'wp_ajax_turbo_cookie_complete_onboarding', array( $this, 'ajax_complete_onboarding' ) );
		add_action( 'wp_ajax_turbo_cookie_get_stats', array( $this, 'ajax_get_stats' ) );
		add_action( 'wp_ajax_turbo_cookie_export_logs', array( $this, 'ajax_export_logs' ) );
		add_action( 'wp_ajax_turbo_cookie_save_cookie',   array( $this, 'ajax_save_cookie' ) );
		add_action( 'wp_ajax_turbo_cookie_delete_cookie', array( $this, 'ajax_delete_cookie' ) );
		add_action( 'wp_ajax_turbo_cookie_get_known_services', array( $this, 'ajax_get_known_services' ) );
	}

	/**
	 * Handle redirect after activation.
	 *
	 * @since 1.0.0
	 */
	public function handle_activation_redirect() {
		if ( get_transient( 'turbo_cookie_activation_redirect' ) ) {
			delete_transient( 'turbo_cookie_activation_redirect' );

			if ( ! is_multisite() && ! wp_doing_ajax() ) {
				wp_safe_redirect( admin_url( 'admin.php?page=turbo-cookie&tab=wizard' ) );
				exit;
			}
		}
	}

	/**
	 * Register admin menu.
	 *
	 * @since 1.0.0
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Turbo Cookie', 'turbo-cookie-gdpr' ),
			__( 'Turbo Cookie', 'turbo-cookie-gdpr' ),
			'manage_options',
			'turbo-cookie',
			array( $this, 'render_dashboard' ),
			'dashicons-privacy',
			81
		);

		add_submenu_page(
			'turbo-cookie',
			__( 'Dashboard', 'turbo-cookie-gdpr' ),
			__( 'Dashboard', 'turbo-cookie-gdpr' ),
			'manage_options',
			'turbo-cookie',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'turbo-cookie',
			__( 'Banner Design', 'turbo-cookie-gdpr' ),
			__( 'Banner Design', 'turbo-cookie-gdpr' ),
			'manage_options',
			'turbo-cookie-banner',
			array( $this, 'render_banner_page' )
		);

		add_submenu_page(
			'turbo-cookie',
			__( 'Categories', 'turbo-cookie-gdpr' ),
			__( 'Categories', 'turbo-cookie-gdpr' ),
			'manage_options',
			'turbo-cookie-categories',
			array( $this, 'render_categories_page' )
		);

		add_submenu_page(
			'turbo-cookie',
			__( 'Consent Logs', 'turbo-cookie-gdpr' ),
			__( 'Consent Logs', 'turbo-cookie-gdpr' ),
			'manage_options',
			'turbo-cookie-logs',
			array( $this, 'render_logs_page' )
		);

		add_submenu_page(
			'turbo-cookie',
			__( 'Settings', 'turbo-cookie-gdpr' ),
			__( 'Settings', 'turbo-cookie-gdpr' ),
			'manage_options',
			'turbo-cookie-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'turbo-cookie',
			__( 'Manage Cookies', 'turbo-cookie-gdpr' ),
			__( 'Manage Cookies', 'turbo-cookie-gdpr' ),
			'manage_options',
			'turbo-cookie-manage',
			array( $this, 'render_manage_cookies_page' )
		);

		add_submenu_page(
			'turbo-cookie',
			__( 'Cookie Policy', 'turbo-cookie-gdpr' ),
			__( 'Cookie Policy', 'turbo-cookie-gdpr' ),
			'manage_options',
			'turbo-cookie-policy',
			array( $this, 'render_cookie_policy_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		// Only load on our pages.
		if ( false === strpos( $hook, 'turbo-cookie' ) ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );

		wp_enqueue_style(
			'turbo-cookie-admin',
			TURBO_COOKIE_URL . 'admin/css/turbo-cookie-admin.css',
			array(),
			TURBO_COOKIE_VERSION
		);

		wp_enqueue_script( 'wp-color-picker' );

		wp_enqueue_script(
			'turbo-cookie-admin',
			TURBO_COOKIE_URL . 'admin/js/turbo-cookie-admin.js',
			array( 'jquery', 'wp-color-picker' ),
			TURBO_COOKIE_VERSION,
			true
		);

		wp_localize_script( 'turbo-cookie-admin', 'turboCookieAdmin', array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'restUrl'  => rest_url( 'turbo-cookie/v1/' ),
			'nonce'    => wp_create_nonce( 'turbo_cookie_admin' ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'strings'  => array(
				'saved'        => __( 'Settings saved successfully!', 'turbo-cookie-gdpr' ),
				'error'        => __( 'An error occurred. Please try again.', 'turbo-cookie-gdpr' ),
				'confirm_clear' => __( 'Are you sure you want to delete all consent logs? This cannot be undone.', 'turbo-cookie-gdpr' ),
				'exported'     => __( 'Logs exported successfully.', 'turbo-cookie-gdpr' ),
				'confirm_delete_cookie' => __( 'Delete this cookie?', 'turbo-cookie-gdpr' ),
				'cookie_saved'  => __( 'Cookie saved.', 'turbo-cookie-gdpr' ),
				'cookie_deleted' => __( 'Cookie deleted.', 'turbo-cookie-gdpr' ),
			),
		) );
	}

	/**
	 * Render dashboard page.
	 *
	 * @since 1.0.0
	 */
	public function render_dashboard() {
		$settings   = Turbo_Cookie::get_settings();
		$consent    = new Turbo_Cookie_Consent();
		$stats      = $consent->get_stats( 30 );
		$log_count  = $consent->get_log_count();
		$onboarding = get_option( TURBO_COOKIE_ONBOARDING_OPTION );
		$tab        = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'wizard' === $tab && ! $onboarding ) {
			$this->render_wizard();
			return;
		}
		?>
		<div class="wrap turbo-cookie-wrap">
			<div class="turbo-cookie-header">
				<h1>
					<span class="dashicons dashicons-shield"></span>
					<?php esc_html_e( 'Turbo Cookie', 'turbo-cookie-gdpr' ); ?>
					<span class="turbo-cookie-version">v<?php echo esc_html( TURBO_COOKIE_VERSION ); ?></span>
				</h1>
				<p class="turbo-cookie-tagline"><?php esc_html_e( 'GDPR Cookie Consent by Turbo Addons', 'turbo-cookie-gdpr' ); ?></p>
			</div>

			<div class="turbo-cookie-dashboard">
				<!-- Status Card -->
				<div class="turbo-cookie-card turbo-cookie-status-card">
					<div class="card-header">
						<h2><?php esc_html_e( 'Cookie Consent Status', 'turbo-cookie-gdpr' ); ?></h2>
						<span class="status-badge <?php echo esc_attr( $settings['enabled'] ? 'active' : 'inactive' ); ?>">
							<?php echo $settings['enabled'] ? esc_html__( 'Active', 'turbo-cookie-gdpr' ) : esc_html__( 'Inactive', 'turbo-cookie-gdpr' ); ?>
						</span>
					</div>
					<div class="card-body">
						<div class="status-grid">
							<div class="status-item">
								<span class="status-icon dashicons dashicons-<?php echo esc_attr( $settings['script_blocking'] ? 'yes-alt' : 'minus' ); ?>"></span>
								<span><?php esc_html_e( 'Script Blocking', 'turbo-cookie-gdpr' ); ?></span>
							</div>
							<div class="status-item">
								<span class="status-icon dashicons dashicons-<?php echo esc_attr( $settings['gcm_enabled'] ? 'yes-alt' : 'minus' ); ?>"></span>
								<span><?php esc_html_e( 'Google Consent Mode', 'turbo-cookie-gdpr' ); ?></span>
							</div>
							<div class="status-item">
								<span class="status-icon dashicons dashicons-<?php echo esc_attr( $settings['consent_log_enabled'] ? 'yes-alt' : 'minus' ); ?>"></span>
								<span><?php esc_html_e( 'Consent Logging', 'turbo-cookie-gdpr' ); ?></span>
							</div>
						</div>
					</div>
				</div>

				<!-- Stats Card -->
				<div class="turbo-cookie-card turbo-cookie-stats-card">
					<div class="card-header">
						<h2><?php esc_html_e( 'Consent Statistics (30 days)', 'turbo-cookie-gdpr' ); ?></h2>
					</div>
					<div class="card-body">
						<div class="stats-grid">
							<div class="stat-item">
								<span class="stat-number"><?php echo esc_html( $stats['total'] ); ?></span>
								<span class="stat-label"><?php esc_html_e( 'Total', 'turbo-cookie-gdpr' ); ?></span>
							</div>
							<div class="stat-item stat-accepted">
								<span class="stat-number"><?php echo esc_html( $stats['accepted'] ); ?></span>
								<span class="stat-label"><?php esc_html_e( 'Accepted', 'turbo-cookie-gdpr' ); ?></span>
							</div>
							<div class="stat-item stat-declined">
								<span class="stat-number"><?php echo esc_html( $stats['declined'] ); ?></span>
								<span class="stat-label"><?php esc_html_e( 'Declined', 'turbo-cookie-gdpr' ); ?></span>
							</div>
							<div class="stat-item stat-partial">
								<span class="stat-number"><?php echo esc_html( $stats['partial'] ); ?></span>
								<span class="stat-label"><?php esc_html_e( 'Partial', 'turbo-cookie-gdpr' ); ?></span>
							</div>
							<div class="stat-item stat-rate">
								<span class="stat-number"><?php echo esc_html( $stats['acceptance_rate'] ); ?>%</span>
								<span class="stat-label"><?php esc_html_e( 'Accept Rate', 'turbo-cookie-gdpr' ); ?></span>
							</div>
						</div>
					</div>
				</div>

					<!-- Quick Links -->
				<div class="turbo-cookie-card turbo-cookie-links-card">
					<div class="card-header">
						<h2><?php esc_html_e( 'Quick Actions', 'turbo-cookie-gdpr' ); ?></h2>
					</div>
					<div class="card-body">
						<div class="quick-links">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=turbo-cookie-banner' ) ); ?>" class="quick-link">
								<span class="dashicons dashicons-art"></span>
								<?php esc_html_e( 'Customize Banner', 'turbo-cookie-gdpr' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=turbo-cookie-categories' ) ); ?>" class="quick-link">
								<span class="dashicons dashicons-category"></span>
								<?php esc_html_e( 'Manage Categories', 'turbo-cookie-gdpr' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=turbo-cookie-manage' ) ); ?>" class="quick-link">
								<span class="dashicons dashicons-list-view"></span>
								<?php esc_html_e( 'Manage Cookies', 'turbo-cookie-gdpr' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=turbo-cookie-policy' ) ); ?>" class="quick-link">
								<span class="dashicons dashicons-media-document"></span>
								<?php esc_html_e( 'Cookie Policy', 'turbo-cookie-gdpr' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=turbo-cookie-logs' ) ); ?>" class="quick-link">
								<span class="dashicons dashicons-chart-bar"></span>
								<?php esc_html_e( 'Consent Logs', 'turbo-cookie-gdpr' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=turbo-cookie-settings' ) ); ?>" class="quick-link">
								<span class="dashicons dashicons-admin-generic"></span>
								<?php esc_html_e( 'Settings', 'turbo-cookie-gdpr' ); ?>
							</a>
						</div>
					</div>
				</div>
			</div>

			<!-- Privacy Policy Notice -->
				<div class="turbo-cookie-card turbo-cookie-notice-card">
					<div class="card-header">
						<h2><?php esc_html_e( 'Privacy & Legal Notice', 'turbo-cookie-gdpr' ); ?></h2>
					</div>
					<div class="card-body">
						<p><?php esc_html_e( 'Turbo Cookie provides technical tools for cookie consent. Whether your site meets applicable privacy laws (GDPR, CCPA, etc.) depends on your specific site, policies, and data practices.', 'turbo-cookie-gdpr' ); ?></p>
						<p><?php esc_html_e( 'All consent data is stored in your own database. No visitor data is sent to external servers by this plugin.', 'turbo-cookie-gdpr' ); ?></p>
						<p><strong><?php esc_html_e( 'For legal advice about your compliance obligations, consult a qualified legal professional.', 'turbo-cookie-gdpr' ); ?></strong></p>
					</div>
				</div>
		</div>
		<?php
	}

	/**
	 * Render banner design page.
	 *
	 * @since 1.0.0
	 */
	public function render_banner_page() {
		$banner = Turbo_Cookie::get_banner_settings();
		?>
		<div class="wrap turbo-cookie-wrap">
			<div class="turbo-cookie-header">
				<h1><?php esc_html_e( 'Banner Design', 'turbo-cookie-gdpr' ); ?></h1>
				<p><?php esc_html_e( 'Customize the appearance and content of your cookie consent banner.', 'turbo-cookie-gdpr' ); ?></p>
			</div>

			<form id="turbo-cookie-banner-form" class="turbo-cookie-form">
				<?php wp_nonce_field( 'turbo_cookie_admin', 'turbo_cookie_nonce' ); ?>

				<div class="turbo-cookie-form-grid">
					<!-- Layout Section -->
					<div class="turbo-cookie-card">
						<div class="card-header"><h2><?php esc_html_e( 'Layout', 'turbo-cookie-gdpr' ); ?></h2></div>
						<div class="card-body">
							<div class="form-row">
								<label for="banner_position"><?php esc_html_e( 'Position', 'turbo-cookie-gdpr' ); ?></label>
								<select id="banner_position" name="position">
									<option value="bottom" <?php selected( $banner['position'], 'bottom' ); ?>><?php esc_html_e( 'Bottom', 'turbo-cookie-gdpr' ); ?></option>
									<option value="top" <?php selected( $banner['position'], 'top' ); ?>><?php esc_html_e( 'Top', 'turbo-cookie-gdpr' ); ?></option>
									<option value="bottom-left" <?php selected( $banner['position'], 'bottom-left' ); ?>><?php esc_html_e( 'Bottom Left (Floating)', 'turbo-cookie-gdpr' ); ?></option>
									<option value="bottom-right" <?php selected( $banner['position'], 'bottom-right' ); ?>><?php esc_html_e( 'Bottom Right (Floating)', 'turbo-cookie-gdpr' ); ?></option>
									<option value="center" <?php selected( $banner['position'], 'center' ); ?>><?php esc_html_e( 'Center (Modal)', 'turbo-cookie-gdpr' ); ?></option>
								</select>
							</div>
							<div class="form-row">
								<label for="banner_layout"><?php esc_html_e( 'Layout Style', 'turbo-cookie-gdpr' ); ?></label>
								<select id="banner_layout" name="layout">
									<option value="bar" <?php selected( $banner['layout'], 'bar' ); ?>><?php esc_html_e( 'Bar', 'turbo-cookie-gdpr' ); ?></option>
									<option value="box" <?php selected( $banner['layout'], 'box' ); ?>><?php esc_html_e( 'Box', 'turbo-cookie-gdpr' ); ?></option>
									<option value="cloud" <?php selected( $banner['layout'], 'cloud' ); ?>><?php esc_html_e( 'Cloud', 'turbo-cookie-gdpr' ); ?></option>
								</select>
							</div>
							<div class="form-row">
								<label for="banner_animation"><?php esc_html_e( 'Animation', 'turbo-cookie-gdpr' ); ?></label>
								<select id="banner_animation" name="animation">
									<option value="slide" <?php selected( $banner['animation'], 'slide' ); ?>><?php esc_html_e( 'Slide', 'turbo-cookie-gdpr' ); ?></option>
									<option value="fade" <?php selected( $banner['animation'], 'fade' ); ?>><?php esc_html_e( 'Fade', 'turbo-cookie-gdpr' ); ?></option>
									<option value="none" <?php selected( $banner['animation'], 'none' ); ?>><?php esc_html_e( 'None', 'turbo-cookie-gdpr' ); ?></option>
								</select>
							</div>
						</div>
					</div>

					<!-- Content Section -->
					<div class="turbo-cookie-card">
						<div class="card-header"><h2><?php esc_html_e( 'Content', 'turbo-cookie-gdpr' ); ?></h2></div>
						<div class="card-body">
							<div class="form-row">
								<label for="banner_title"><?php esc_html_e( 'Title', 'turbo-cookie-gdpr' ); ?></label>
								<input type="text" id="banner_title" name="title" value="<?php echo esc_attr( $banner['title'] ); ?>" />
							</div>
							<div class="form-row">
								<label for="banner_message"><?php esc_html_e( 'Message', 'turbo-cookie-gdpr' ); ?></label>
								<textarea id="banner_message" name="message" rows="4"><?php echo esc_textarea( $banner['message'] ); ?></textarea>
							</div>
							<div class="form-row">
								<label for="banner_privacy_url"><?php esc_html_e( 'Cookie Policy URL', 'turbo-cookie-gdpr' ); ?></label>
								<input type="url" id="banner_privacy_url" name="privacy_policy_url" value="<?php echo esc_url( $banner['privacy_policy_url'] ); ?>" placeholder="https://example.com/cookie-policy" />
							</div>
							<div class="form-row">
								<label for="banner_privacy_text"><?php esc_html_e( 'Cookie Policy Link Text', 'turbo-cookie-gdpr' ); ?></label>
								<input type="text" id="banner_privacy_text" name="privacy_policy_text" value="<?php echo esc_attr( $banner['privacy_policy_text'] ); ?>" />
							</div>
						</div>
					</div>

					<!-- Buttons Section -->
					<div class="turbo-cookie-card">
						<div class="card-header"><h2><?php esc_html_e( 'Buttons', 'turbo-cookie-gdpr' ); ?></h2></div>
						<div class="card-body">
							<div class="form-row">
								<label for="banner_accept_text"><?php esc_html_e( 'Accept Button Text', 'turbo-cookie-gdpr' ); ?></label>
								<input type="text" id="banner_accept_text" name="accept_text" value="<?php echo esc_attr( $banner['accept_text'] ); ?>" />
							</div>
							<div class="form-row">
								<label for="banner_decline_text"><?php esc_html_e( 'Decline Button Text', 'turbo-cookie-gdpr' ); ?></label>
								<input type="text" id="banner_decline_text" name="decline_text" value="<?php echo esc_attr( $banner['decline_text'] ); ?>" />
							</div>
							<div class="form-row">
								<label>
									<input type="checkbox" name="show_decline" value="1" <?php checked( $banner['show_decline'] ); ?> />
									<?php esc_html_e( 'Show Decline Button', 'turbo-cookie-gdpr' ); ?>
								</label>
							</div>
							<div class="form-row">
								<label for="banner_prefs_text"><?php esc_html_e( 'Preferences Button Text', 'turbo-cookie-gdpr' ); ?></label>
								<input type="text" id="banner_prefs_text" name="preferences_text" value="<?php echo esc_attr( $banner['preferences_text'] ); ?>" />
							</div>
							<div class="form-row">
								<label for="banner_save_text"><?php esc_html_e( 'Save Preferences Text', 'turbo-cookie-gdpr' ); ?></label>
								<input type="text" id="banner_save_text" name="save_text" value="<?php echo esc_attr( $banner['save_text'] ); ?>" />
							</div>
						</div>
					</div>

					<!-- Colors Section -->
					<div class="turbo-cookie-card">
						<div class="card-header"><h2><?php esc_html_e( 'Colors', 'turbo-cookie-gdpr' ); ?></h2></div>
						<div class="card-body">
							<div class="form-row">
								<label><?php esc_html_e( 'Background Color', 'turbo-cookie-gdpr' ); ?></label>
								<input type="text" name="bg_color" class="turbo-color-picker" value="<?php echo esc_attr( $banner['bg_color'] ); ?>" />
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Text Color', 'turbo-cookie-gdpr' ); ?></label>
								<input type="text" name="text_color" class="turbo-color-picker" value="<?php echo esc_attr( $banner['text_color'] ); ?>" />
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Accept Button Background', 'turbo-cookie-gdpr' ); ?></label>
								<input type="text" name="btn_accept_bg" class="turbo-color-picker" value="<?php echo esc_attr( $banner['btn_accept_bg'] ); ?>" />
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Accept Button Text', 'turbo-cookie-gdpr' ); ?></label>
								<input type="text" name="btn_accept_text" class="turbo-color-picker" value="<?php echo esc_attr( $banner['btn_accept_text'] ); ?>" />
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Decline Button Border', 'turbo-cookie-gdpr' ); ?></label>
								<input type="text" name="btn_decline_border" class="turbo-color-picker" value="<?php echo esc_attr( $banner['btn_decline_border'] ); ?>" />
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Decline Button Text', 'turbo-cookie-gdpr' ); ?></label>
								<input type="text" name="btn_decline_text" class="turbo-color-picker" value="<?php echo esc_attr( $banner['btn_decline_text'] ); ?>" />
							</div>
						</div>
					</div>
				</div>

				<div class="turbo-cookie-form-actions">
					<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save Banner Settings', 'turbo-cookie-gdpr' ); ?></button>
					<span class="turbo-cookie-save-indicator"></span>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render categories page.
	 *
	 * @since 1.0.0
	 */
	public function render_categories_page() {
		$categories = Turbo_Cookie::get_categories();
		?>
		<div class="wrap turbo-cookie-wrap">
			<div class="turbo-cookie-header">
				<h1><?php esc_html_e( 'Cookie Categories', 'turbo-cookie-gdpr' ); ?></h1>
				<p><?php esc_html_e( 'Manage the cookie consent categories shown to visitors in the preferences modal.', 'turbo-cookie-gdpr' ); ?></p>
			</div>

			<form id="turbo-cookie-categories-form" class="turbo-cookie-form">
				<?php wp_nonce_field( 'turbo_cookie_admin', 'turbo_cookie_nonce' ); ?>

				<div class="turbo-cookie-categories-list">
					<?php foreach ( $categories as $slug => $category ) : ?>
					<div class="turbo-cookie-card turbo-cookie-category-card" data-slug="<?php echo esc_attr( $slug ); ?>">
						<div class="card-header">
							<h2>
								<span class="dashicons dashicons-<?php echo esc_attr( 'essential' === $slug ? 'lock' : 'category' ); ?>"></span>
								<?php echo esc_html( $category['name'] ); ?>
								<?php if ( ! empty( $category['required'] ) ) : ?>
									<span class="badge-required"><?php esc_html_e( 'Required', 'turbo-cookie-gdpr' ); ?></span>
								<?php endif; ?>
							</h2>
						</div>
						<div class="card-body">
							<div class="form-row">
								<label><?php esc_html_e( 'Name', 'turbo-cookie-gdpr' ); ?></label>
								<input type="text" name="categories[<?php echo esc_attr( $slug ); ?>][name]" value="<?php echo esc_attr( $category['name'] ); ?>" />
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Description', 'turbo-cookie-gdpr' ); ?></label>
								<textarea name="categories[<?php echo esc_attr( $slug ); ?>][description]" rows="3"><?php echo esc_textarea( $category['description'] ); ?></textarea>
							</div>
							<div class="form-row">
								<label>
									<input type="checkbox" name="categories[<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( $category['enabled'] ); ?> />
									<?php esc_html_e( 'Enabled', 'turbo-cookie-gdpr' ); ?>
								</label>
							</div>
							<input type="hidden" name="categories[<?php echo esc_attr( $slug ); ?>][required]" value="<?php echo esc_attr( $category['required'] ? '1' : '0' ); ?>" />
						</div>
					</div>
					<?php endforeach; ?>
				</div>

				<div class="turbo-cookie-form-actions">
					<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save Categories', 'turbo-cookie-gdpr' ); ?></button>
					<span class="turbo-cookie-save-indicator"></span>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render consent logs page.
	 *
	 * @since 1.0.0
	 */
	public function render_logs_page() {
		$consent  = new Turbo_Cookie_Consent();
		$page     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page = 20;
		$result   = $consent->get_logs( array( 'page' => $page, 'per_page' => $per_page ) );
		$total    = $result['total'];
		$items    = $result['items'];
		$pages    = ceil( $total / $per_page );
		?>
		<div class="wrap turbo-cookie-wrap">
			<div class="turbo-cookie-header">
				<h1><?php esc_html_e( 'Consent Logs', 'turbo-cookie-gdpr' ); ?></h1>
				<p><?php
					/* translators: %d: total number of consent log records */
					printf( esc_html__( 'Total records: %d', 'turbo-cookie-gdpr' ), absint( $total ) );
				?></p>
			</div>

			<div class="turbo-cookie-logs-actions">
				<button type="button" class="button" id="turbo-cookie-export-logs"><?php esc_html_e( 'Export CSV', 'turbo-cookie-gdpr' ); ?></button>
				<button type="button" class="button button-link-delete" id="turbo-cookie-clear-logs"><?php esc_html_e( 'Clear All Logs', 'turbo-cookie-gdpr' ); ?></button>
			</div>

			<table class="wp-list-table widefat fixed striped turbo-cookie-logs-table">
				<thead>
					<tr>
						<th class="column-id"><?php esc_html_e( 'ID', 'turbo-cookie-gdpr' ); ?></th>
						<th class="column-session"><?php esc_html_e( 'Session', 'turbo-cookie-gdpr' ); ?></th>
						<th class="column-action"><?php esc_html_e( 'Action', 'turbo-cookie-gdpr' ); ?></th>
						<th class="column-categories"><?php esc_html_e( 'Categories', 'turbo-cookie-gdpr' ); ?></th>
						<th class="column-ip"><?php esc_html_e( 'IP (Masked)', 'turbo-cookie-gdpr' ); ?></th>
						<th class="column-date"><?php esc_html_e( 'Date', 'turbo-cookie-gdpr' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $items ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No consent logs recorded yet.', 'turbo-cookie-gdpr' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $items as $item ) : ?>
						<tr>
							<td><?php echo esc_html( $item['id'] ); ?></td>
							<td><code><?php echo esc_html( substr( $item['session_id'], 0, 8 ) ); ?>...</code></td>
							<td>
								<span class="log-action log-action-<?php echo esc_attr( $item['action'] ); ?>">
									<?php echo esc_html( ucfirst( $item['action'] ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $this->format_categories_display( $item['categories'] ) ); ?></td>
							<td><?php echo esc_html( $item['ip_address'] ); ?></td>
							<td><?php echo esc_html( $item['created_at'] ); ?></td>
						</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $page,
						'total'   => $pages,
					) );
					?>
				</div>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		$settings = Turbo_Cookie::get_settings();
		$gcm      = Turbo_Cookie::get_gcm_settings();
		?>
		<div class="wrap turbo-cookie-wrap">
			<div class="turbo-cookie-header">
				<h1><?php esc_html_e( 'Settings', 'turbo-cookie-gdpr' ); ?></h1>
			</div>

			<form id="turbo-cookie-settings-form" class="turbo-cookie-form">
				<?php wp_nonce_field( 'turbo_cookie_admin', 'turbo_cookie_nonce' ); ?>

				<div class="turbo-cookie-form-grid">
					<!-- General Settings -->
					<div class="turbo-cookie-card">
						<div class="card-header"><h2><?php esc_html_e( 'General', 'turbo-cookie-gdpr' ); ?></h2></div>
						<div class="card-body">
							<div class="form-row">
								<label>
									<input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ); ?> />
									<?php esc_html_e( 'Enable Cookie Consent Banner', 'turbo-cookie-gdpr' ); ?>
								</label>
							</div>
							<div class="form-row">
								<label for="consent_expiry"><?php esc_html_e( 'Consent Expiry (days)', 'turbo-cookie-gdpr' ); ?></label>
								<input type="number" id="consent_expiry" name="consent_expiry" value="<?php echo esc_attr( $settings['consent_expiry'] ); ?>" min="1" max="730" />
								<p class="description"><?php esc_html_e( 'How long the consent cookie lasts before visitors are asked again.', 'turbo-cookie-gdpr' ); ?></p>
							</div>
							<div class="form-row">
								<label for="reconsent_version"><?php esc_html_e( 'Consent Version', 'turbo-cookie-gdpr' ); ?></label>
								<input type="text" id="reconsent_version" name="reconsent_version" value="<?php echo esc_attr( $settings['reconsent_version'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Change this to re-request consent from all visitors (e.g., after policy update).', 'turbo-cookie-gdpr' ); ?></p>
							</div>
							<div class="form-row">
								<label>
									<input type="checkbox" name="show_credit" value="1" <?php checked( ! empty( $settings['show_credit'] ) ); ?> />
									<?php esc_html_e( 'Show "Powered by Turbo Cookie" link in banner', 'turbo-cookie-gdpr' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Optional. Displays a small credit link in the cookie banner. Off by default.', 'turbo-cookie-gdpr' ); ?></p>
							</div>
						</div>
					</div>

					<!-- Script Blocking -->
					<div class="turbo-cookie-card">
						<div class="card-header"><h2><?php esc_html_e( 'Script Blocking', 'turbo-cookie-gdpr' ); ?></h2></div>
						<div class="card-body">
							<div class="form-row">
								<label>
									<input type="checkbox" name="script_blocking" value="1" <?php checked( $settings['script_blocking'] ); ?> />
									<?php esc_html_e( 'Enable Script Blocking', 'turbo-cookie-gdpr' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Automatically block known tracking scripts until visitors give consent.', 'turbo-cookie-gdpr' ); ?></p>
							</div>
						</div>
					</div>

					<!-- Google Consent Mode -->
					<div class="turbo-cookie-card">
						<div class="card-header"><h2><?php esc_html_e( 'Google Consent Mode v2', 'turbo-cookie-gdpr' ); ?></h2></div>
						<div class="card-body">
							<div class="form-row">
								<label>
									<input type="checkbox" name="gcm_enabled" value="1" <?php checked( $settings['gcm_enabled'] ); ?> />
									<?php esc_html_e( 'Enable Google Consent Mode', 'turbo-cookie-gdpr' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Outputs consent signals for Google services (Analytics, Ads, Tag Manager).', 'turbo-cookie-gdpr' ); ?></p>
							</div>
						</div>
					</div>

					<!-- Consent Logging -->
					<div class="turbo-cookie-card">
						<div class="card-header"><h2><?php esc_html_e( 'Consent Logging', 'turbo-cookie-gdpr' ); ?></h2></div>
						<div class="card-body">
							<div class="form-row">
								<label>
									<input type="checkbox" name="consent_log_enabled" value="1" <?php checked( $settings['consent_log_enabled'] ); ?> />
									<?php esc_html_e( 'Enable Consent Logging', 'turbo-cookie-gdpr' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Store consent records in the WordPress database for compliance documentation.', 'turbo-cookie-gdpr' ); ?></p>
							</div>
							<div class="form-row">
								<label for="consent_log_retention"><?php esc_html_e( 'Log Retention (days)', 'turbo-cookie-gdpr' ); ?></label>
								<input type="number" id="consent_log_retention" name="consent_log_retention" value="<?php echo esc_attr( $settings['consent_log_retention'] ); ?>" min="30" max="3650" />
								<p class="description"><?php esc_html_e( 'Logs older than this are automatically deleted. Minimum 30 days.', 'turbo-cookie-gdpr' ); ?></p>
							</div>
						</div>
					</div>
				</div>

				<div class="turbo-cookie-form-actions">
					<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save Settings', 'turbo-cookie-gdpr' ); ?></button>
					<span class="turbo-cookie-save-indicator"></span>
				</div>
			</form>

			<!-- Cookie Policy Page Generator -->
			<div class="turbo-cookie-card" style="margin-top:24px;">
				<div class="card-header">
					<h2><?php esc_html_e( 'Cookie Policy Page', 'turbo-cookie-gdpr' ); ?></h2>
				</div>
				<div class="card-body">
					<?php
					$policy_page_id = get_option( 'turbo_cookie_policy_page_id' );
					$policy_page    = $policy_page_id ? get_post( $policy_page_id ) : null;
					if ( $policy_page && 'publish' === $policy_page->post_status ) :
					?>
						<p>
							<?php esc_html_e( 'Your Cookie Policy page is live:', 'turbo-cookie-gdpr' ); ?>
							<a href="<?php echo esc_url( get_permalink( $policy_page_id ) ); ?>" target="_blank">
								<?php echo esc_html( get_permalink( $policy_page_id ) ); ?>
							</a>
						</p>
						<p class="description"><?php esc_html_e( 'It uses the [turbo_cookie_policy] shortcode and updates automatically as you add cookies.', 'turbo-cookie-gdpr' ); ?></p>
					<?php else : ?>
						<p><?php esc_html_e( 'Automatically create a Cookie Policy page with a dynamic cookie table that updates as you add categories and cookies.', 'turbo-cookie-gdpr' ); ?></p>
						<p>
							<button type="button" id="turbo-cookie-create-policy-page" class="button button-secondary">
								<?php esc_html_e( 'Create Cookie Policy Page', 'turbo-cookie-gdpr' ); ?>
							</button>
							<span class="turbo-cookie-save-indicator" id="turbo-policy-indicator"></span>
						</p>
						<p class="description"><?php esc_html_e( 'Or add this shortcode manually to any page: [turbo_cookie_policy]', 'turbo-cookie-gdpr' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render onboarding wizard.
	 *
	 * @since 1.0.0
	 */
	public function render_wizard() {
		$banner = Turbo_Cookie::get_banner_settings();
		?>
		<div class="wrap turbo-cookie-wrap turbo-cookie-wizard-wrap">
			<div class="turbo-cookie-wizard">
				<div class="wizard-header">
					<span class="dashicons dashicons-shield"></span>
					<h1><?php esc_html_e( 'Welcome to Turbo Cookie', 'turbo-cookie-gdpr' ); ?></h1>
					<p><?php esc_html_e( 'Let\'s set up your cookie consent in under 2 minutes.', 'turbo-cookie-gdpr' ); ?></p>
				</div>

				<div class="wizard-steps">
					<div class="wizard-step-indicators">
						<span class="step-dot active" data-step="1">1</span>
						<span class="step-dot" data-step="2">2</span>
						<span class="step-dot" data-step="3">3</span>
					</div>

					<!-- Step 1: Basics -->
					<div class="wizard-step active" data-step="1">
						<h2><?php esc_html_e( 'Step 1: Basic Setup', 'turbo-cookie-gdpr' ); ?></h2>
						<p><?php esc_html_e( 'Choose your banner position and content.', 'turbo-cookie-gdpr' ); ?></p>

						<div class="form-row">
							<label for="wiz_position"><?php esc_html_e( 'Banner Position', 'turbo-cookie-gdpr' ); ?></label>
							<select id="wiz_position" name="position">
								<option value="bottom"><?php esc_html_e( 'Bottom Bar (Recommended)', 'turbo-cookie-gdpr' ); ?></option>
								<option value="top"><?php esc_html_e( 'Top Bar', 'turbo-cookie-gdpr' ); ?></option>
								<option value="bottom-left"><?php esc_html_e( 'Bottom Left Floating', 'turbo-cookie-gdpr' ); ?></option>
								<option value="bottom-right"><?php esc_html_e( 'Bottom Right Floating', 'turbo-cookie-gdpr' ); ?></option>
								<option value="center"><?php esc_html_e( 'Center Modal', 'turbo-cookie-gdpr' ); ?></option>
							</select>
						</div>

						<div class="form-row">
							<label for="wiz_message"><?php esc_html_e( 'Banner Message', 'turbo-cookie-gdpr' ); ?></label>
							<textarea id="wiz_message" name="message" rows="3"><?php echo esc_textarea( $banner['message'] ); ?></textarea>
						</div>

						<div class="form-row">
							<label for="wiz_privacy_url"><?php esc_html_e( 'Cookie Policy Page URL (optional)', 'turbo-cookie-gdpr' ); ?></label>
							<input type="url" id="wiz_privacy_url" name="privacy_policy_url" value="" placeholder="https://yoursite.com/cookie-policy" />
						</div>
					</div>

					<!-- Step 2: Features -->
					<div class="wizard-step" data-step="2">
						<h2><?php esc_html_e( 'Step 2: Features', 'turbo-cookie-gdpr' ); ?></h2>
						<p><?php esc_html_e( 'Enable the features you need.', 'turbo-cookie-gdpr' ); ?></p>

						<div class="wizard-features">
							<label class="wizard-feature-toggle">
								<input type="checkbox" name="script_blocking" value="1" checked />
								<span class="feature-card">
									<span class="dashicons dashicons-shield"></span>
									<strong><?php esc_html_e( 'Script Blocking', 'turbo-cookie-gdpr' ); ?></strong>
									<span><?php esc_html_e( 'Block tracking scripts until consent is given', 'turbo-cookie-gdpr' ); ?></span>
								</span>
							</label>

							<label class="wizard-feature-toggle">
								<input type="checkbox" name="gcm_enabled" value="1" checked />
								<span class="feature-card">
									<span class="dashicons dashicons-google"></span>
									<strong><?php esc_html_e( 'Google Consent Mode v2', 'turbo-cookie-gdpr' ); ?></strong>
									<span><?php esc_html_e( 'Send consent signals to Google services', 'turbo-cookie-gdpr' ); ?></span>
								</span>
							</label>

							<label class="wizard-feature-toggle">
								<input type="checkbox" name="consent_log_enabled" value="1" checked />
								<span class="feature-card">
									<span class="dashicons dashicons-list-view"></span>
									<strong><?php esc_html_e( 'Consent Logging', 'turbo-cookie-gdpr' ); ?></strong>
									<span><?php esc_html_e( 'Store consent records locally in your database', 'turbo-cookie-gdpr' ); ?></span>
								</span>
							</label>
						</div>
					</div>

					<!-- Step 3: Done -->
					<div class="wizard-step" data-step="3">
						<div class="wizard-done">
							<span class="dashicons dashicons-yes-alt"></span>
							<h2><?php esc_html_e( 'All Set!', 'turbo-cookie-gdpr' ); ?></h2>
							<p><?php esc_html_e( 'Your cookie consent banner is ready. Visitors will now see the banner and can manage their preferences.', 'turbo-cookie-gdpr' ); ?></p>
							<p class="wizard-done-note"><?php esc_html_e( 'You can customize colors, text, and categories anytime from the dashboard.', 'turbo-cookie-gdpr' ); ?></p>
						</div>
					</div>

					<!-- Navigation -->
					<div class="wizard-nav">
						<button type="button" class="button wizard-prev" style="display:none;"><?php esc_html_e( 'Previous', 'turbo-cookie-gdpr' ); ?></button>
						<button type="button" class="button button-primary wizard-next"><?php esc_html_e( 'Next', 'turbo-cookie-gdpr' ); ?></button>
						<button type="button" class="button button-primary wizard-finish" style="display:none;"><?php esc_html_e( 'Finish Setup', 'turbo-cookie-gdpr' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: Save general settings.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'turbo_cookie_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$settings = array(
			'enabled'               => ! empty( $_POST['enabled'] ),
			'script_blocking'       => ! empty( $_POST['script_blocking'] ),
			'gcm_enabled'           => ! empty( $_POST['gcm_enabled'] ),
			'consent_expiry'        => absint( $_POST['consent_expiry'] ?? 365 ),
			'show_on_pages'         => sanitize_text_field( wp_unslash( $_POST['show_on_pages'] ?? 'all' ) ),
			'excluded_pages'        => sanitize_textarea_field( wp_unslash( $_POST['excluded_pages'] ?? '' ) ),
			'consent_log_enabled'   => ! empty( $_POST['consent_log_enabled'] ),
			'consent_log_retention' => max( 30, absint( $_POST['consent_log_retention'] ?? 365 ) ),
			'cookie_name'           => sanitize_key( wp_unslash( $_POST['cookie_name'] ?? 'turbo_cookie_consent' ) ),
			'reconsent_version'     => sanitize_text_field( wp_unslash( $_POST['reconsent_version'] ?? '1' ) ),
			'show_credit'           => ! empty( $_POST['show_credit'] ),
		);

		update_option( TURBO_COOKIE_SETTINGS_OPTION, $settings );
		wp_send_json_success( array( 'message' => 'Settings saved.' ) );
	}

	/**
	 * AJAX: Save banner settings.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_banner() {
		check_ajax_referer( 'turbo_cookie_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$banner = array(
			'position'            => sanitize_text_field( wp_unslash( $_POST['position'] ?? 'bottom' ) ),
			'layout'              => sanitize_text_field( wp_unslash( $_POST['layout'] ?? 'bar' ) ),
			'width'               => sanitize_text_field( wp_unslash( $_POST['width'] ?? 'full' ) ),
			'title'               => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'message'             => wp_kses_post( wp_unslash( $_POST['message'] ?? '' ) ),
			'privacy_policy_url'  => esc_url_raw( wp_unslash( $_POST['privacy_policy_url'] ?? '' ) ),
			'privacy_policy_text' => sanitize_text_field( wp_unslash( $_POST['privacy_policy_text'] ?? '' ) ),
			'accept_text'         => sanitize_text_field( wp_unslash( $_POST['accept_text'] ?? 'Accept All' ) ),
			'decline_text'        => sanitize_text_field( wp_unslash( $_POST['decline_text'] ?? 'Decline' ) ),
			'preferences_text'    => sanitize_text_field( wp_unslash( $_POST['preferences_text'] ?? 'Preferences' ) ),
			'save_text'           => sanitize_text_field( wp_unslash( $_POST['save_text'] ?? 'Save Preferences' ) ),
			'show_decline'        => ! empty( $_POST['show_decline'] ),
			'bg_color'            => sanitize_hex_color( wp_unslash( $_POST['bg_color'] ?? '#1e293b' ) ),
			'text_color'          => sanitize_hex_color( wp_unslash( $_POST['text_color'] ?? '#f1f5f9' ) ),
			'btn_accept_bg'       => sanitize_hex_color( wp_unslash( $_POST['btn_accept_bg'] ?? '#3b82f6' ) ),
			'btn_accept_text'     => sanitize_hex_color( wp_unslash( $_POST['btn_accept_text'] ?? '#ffffff' ) ),
			'btn_decline_bg'      => Turbo_Cookie::sanitize_css_color( sanitize_text_field( wp_unslash( $_POST['btn_decline_bg'] ?? 'transparent' ) ), 'transparent' ),
			'btn_decline_text'    => sanitize_hex_color( wp_unslash( $_POST['btn_decline_text'] ?? '#94a3b8' ) ),
			'btn_decline_border'  => sanitize_hex_color( wp_unslash( $_POST['btn_decline_border'] ?? '#475569' ) ),
			'btn_prefs_bg'        => Turbo_Cookie::sanitize_css_color( sanitize_text_field( wp_unslash( $_POST['btn_prefs_bg'] ?? 'transparent' ) ), 'transparent' ),
			'btn_prefs_text'      => sanitize_hex_color( wp_unslash( $_POST['btn_prefs_text'] ?? '#94a3b8' ) ),
			'btn_prefs_border'    => sanitize_hex_color( wp_unslash( $_POST['btn_prefs_border'] ?? '#475569' ) ),
			'animation'           => sanitize_text_field( wp_unslash( $_POST['animation'] ?? 'slide' ) ),
			'show_logo'           => ! empty( $_POST['show_logo'] ),
			'logo_url'            => esc_url_raw( wp_unslash( $_POST['logo_url'] ?? '' ) ),
		);

		update_option( TURBO_COOKIE_BANNER_OPTION, $banner );
		wp_send_json_success( array( 'message' => 'Banner settings saved.' ) );
	}

	/**
	 * AJAX: Save categories.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_categories() {
		check_ajax_referer( 'turbo_cookie_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$raw_categories = isset( $_POST['categories'] ) ? $_POST['categories'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$categories     = array();

		foreach ( $raw_categories as $slug => $cat ) {
			$slug = sanitize_key( $slug );
			$categories[ $slug ] = array(
				'name'        => sanitize_text_field( $cat['name'] ?? '' ),
				'description' => sanitize_textarea_field( $cat['description'] ?? '' ),
				'required'    => ! empty( $cat['required'] ),
				'enabled'     => ! empty( $cat['enabled'] ),
			);
		}

		update_option( TURBO_COOKIE_CATEGORIES_OPTION, $categories );
		wp_send_json_success( array( 'message' => 'Categories saved.' ) );
	}

	/**
	 * AJAX: Save GCM settings.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_gcm() {
		check_ajax_referer( 'turbo_cookie_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$gcm = array(
			'enabled'                  => ! empty( $_POST['gcm_enabled'] ),
			'wait_for_update'          => absint( $_POST['wait_for_update'] ?? 500 ),
			'ad_storage'               => sanitize_text_field( wp_unslash( $_POST['ad_storage'] ?? 'denied' ) ),
			'ad_user_data'             => sanitize_text_field( wp_unslash( $_POST['ad_user_data'] ?? 'denied' ) ),
			'ad_personalization'       => sanitize_text_field( wp_unslash( $_POST['ad_personalization'] ?? 'denied' ) ),
			'analytics_storage'        => sanitize_text_field( wp_unslash( $_POST['analytics_storage'] ?? 'denied' ) ),
			'functionality_storage'    => sanitize_text_field( wp_unslash( $_POST['functionality_storage'] ?? 'denied' ) ),
			'personalization_storage'  => sanitize_text_field( wp_unslash( $_POST['personalization_storage'] ?? 'denied' ) ),
			'security_storage'         => 'granted',
			'map_analytics'            => sanitize_text_field( wp_unslash( $_POST['map_analytics'] ?? 'analytics' ) ),
			'map_marketing'            => sanitize_text_field( wp_unslash( $_POST['map_marketing'] ?? 'marketing' ) ),
			'map_functional'           => sanitize_text_field( wp_unslash( $_POST['map_functional'] ?? 'functional' ) ),
		);

		update_option( TURBO_COOKIE_GCM_OPTION, $gcm );
		wp_send_json_success( array( 'message' => 'Google Consent Mode settings saved.' ) );
	}

	/**
	 * AJAX: Complete onboarding wizard.
	 *
	 * @since 1.0.0
	 */
	public function ajax_complete_onboarding() {
		check_ajax_referer( 'turbo_cookie_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		// Save wizard choices.
		$settings = Turbo_Cookie::get_settings();
		$settings['script_blocking']     = ! empty( $_POST['script_blocking'] );
		$settings['gcm_enabled']         = ! empty( $_POST['gcm_enabled'] );
		$settings['consent_log_enabled'] = ! empty( $_POST['consent_log_enabled'] );
		$settings['enabled']             = true;
		update_option( TURBO_COOKIE_SETTINGS_OPTION, $settings );

		// Save banner position/message if provided.
		if ( ! empty( $_POST['position'] ) || ! empty( $_POST['message'] ) ) {
			$banner = Turbo_Cookie::get_banner_settings();
			if ( ! empty( $_POST['position'] ) ) {
				$banner['position'] = sanitize_text_field( wp_unslash( $_POST['position'] ) );
			}
			if ( ! empty( $_POST['message'] ) ) {
				$banner['message'] = wp_kses_post( wp_unslash( $_POST['message'] ) );
			}
			if ( ! empty( $_POST['privacy_policy_url'] ) ) {
				$banner['privacy_policy_url'] = esc_url_raw( wp_unslash( $_POST['privacy_policy_url'] ) );
			}
			update_option( TURBO_COOKIE_BANNER_OPTION, $banner );
		}

		// Mark onboarding complete.
		update_option( TURBO_COOKIE_ONBOARDING_OPTION, true );

		wp_send_json_success( array(
			'message'  => 'Setup complete!',
			'redirect' => admin_url( 'admin.php?page=turbo-cookie' ),
		) );
	}

	/**
	 * AJAX: Get consent stats.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_stats() {
		check_ajax_referer( 'turbo_cookie_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$days    = absint( $_POST['days'] ?? 30 );
		$consent = new Turbo_Cookie_Consent();
		$stats   = $consent->get_stats( $days );

		wp_send_json_success( $stats );
	}

	/**
	 * AJAX: Export consent logs as CSV.
	 *
	 * @since 1.0.0
	 */
	public function ajax_export_logs() {
		check_ajax_referer( 'turbo_cookie_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$consent = new Turbo_Cookie_Consent();
		$result  = $consent->get_logs( array( 'per_page' => 10000, 'page' => 1 ) );
		$items   = $result['items'];

		$csv_lines = array();
		$csv_lines[] = 'ID,Session ID,User ID,Action,Categories,IP Address,Country,Date';

		foreach ( $items as $item ) {
			$csv_lines[] = sprintf(
				'%d,"%s",%d,"%s","%s","%s","%s","%s"',
				$item['id'],
				$item['session_id'],
				$item['user_id'],
				$item['action'],
				str_replace( '"', '""', $item['categories'] ),
				$item['ip_address'],
				$item['country'],
				$item['created_at']
			);
		}

		wp_send_json_success( array( 'csv' => implode( "\n", $csv_lines ) ) );
	}

	/**
	 * Format categories JSON for display.
	 *
	 * @since 1.0.0
	 * @param string $categories_json JSON string of categories.
	 * @return string Human-readable category list.
	 */
	private function format_categories_display( $categories_json ) {
		$categories = json_decode( $categories_json, true );

		if ( ! is_array( $categories ) ) {
			return $categories_json;
		}

		$enabled = array();
		foreach ( $categories as $key => $value ) {
			if ( $value ) {
				$enabled[] = ucfirst( $key );
			}
		}

		return implode( ', ', $enabled );
	}

	// =========================================================================
	// F2 — Manage Cookies page
	// =========================================================================

	/**
	 * Render Manage Cookies page.
	 *
	 * @since 1.1.0
	 */
	public function render_manage_cookies_page() {
		$declared   = get_option( 'turbo_cookie_declared_cookies', array() );
		$known      = $this->get_known_services_list();
		$valid_cats = array( 'essential', 'functional', 'analytics', 'marketing' );
		?>
		<div class="wrap turbo-cookie-wrap">
			<div class="turbo-cookie-header">
				<h1><?php esc_html_e( 'Manage Cookies', 'turbo-cookie-gdpr' ); ?></h1>
				<p><?php esc_html_e( 'Declare the cookies your site uses. These appear in your Cookie Policy page.', 'turbo-cookie-gdpr' ); ?></p>
			</div>

			<!-- Add from Known Service -->
			<div class="turbo-cookie-card">
				<div class="card-header">
					<h2><?php esc_html_e( 'Add from Known Service', 'turbo-cookie-gdpr' ); ?></h2>
				</div>
				<div class="card-body">
					<p class="description"><?php esc_html_e( 'Click a service to add all its cookies to your declared list instantly.', 'turbo-cookie-gdpr' ); ?></p>
					<div class="turbo-cookie-known-services-grid">
						<?php foreach ( $known as $svc ) : ?>
						<button type="button"
							class="turbo-cookie-service-chip"
							data-service='<?php echo esc_attr( wp_json_encode( $svc ) ); ?>'>
							<span class="chip-cat chip-cat-<?php echo esc_attr( $svc['category'] ?? 'functional' ); ?>"></span>
							<?php echo esc_html( $svc['name'] ); ?>
						</button>
						<?php endforeach; ?>
					</div>
					<p><span class="turbo-cookie-save-indicator" id="tc-service-add-msg"></span></p>
				</div>
			</div>

			<!-- Add manually -->
			<div class="turbo-cookie-card">
				<div class="card-header"><h2><?php esc_html_e( 'Add Cookie Manually', 'turbo-cookie-gdpr' ); ?></h2></div>
				<div class="card-body">
					<form id="tc-add-cookie-form" class="turbo-cookie-form">
						<?php wp_nonce_field( 'turbo_cookie_admin', 'turbo_cookie_nonce' ); ?>
						<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
							<div class="form-row">
								<label><?php esc_html_e( 'Cookie Name *', 'turbo-cookie-gdpr' ); ?></label>
								<input type="text" id="tc-new-name" placeholder="_ga" class="regular-text" required />
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Provider', 'turbo-cookie-gdpr' ); ?></label>
								<input type="text" id="tc-new-provider" placeholder="Google" class="regular-text" />
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Category', 'turbo-cookie-gdpr' ); ?></label>
								<select id="tc-new-category">
									<?php foreach ( $valid_cats as $cat ) : ?>
									<option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( ucfirst( $cat ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Expiry', 'turbo-cookie-gdpr' ); ?></label>
								<input type="text" id="tc-new-duration" placeholder="2 years" class="regular-text" />
							</div>
						</div>
						<div class="form-row" style="margin-top:12px;">
							<label><?php esc_html_e( 'Description', 'turbo-cookie-gdpr' ); ?></label>
							<input type="text" id="tc-new-description" placeholder="<?php esc_attr_e( 'What this cookie does', 'turbo-cookie-gdpr' ); ?>" class="large-text" />
						</div>
						<div class="turbo-cookie-form-actions" style="margin-top:12px;">
							<button type="submit" class="button button-primary"><?php esc_html_e( '+ Add Cookie', 'turbo-cookie-gdpr' ); ?></button>
							<span class="turbo-cookie-save-indicator" id="tc-add-cookie-msg"></span>
						</div>
					</form>
				</div>
			</div>

			<!-- Declared cookies table -->
			<div class="turbo-cookie-card">
				<div class="card-header">
					<h2>
						<?php esc_html_e( 'Declared Cookies', 'turbo-cookie-gdpr' ); ?>
						<span id="tc-cookie-count" style="font-size:13px;font-weight:400;color:#64748b;margin-left:8px;">
							(<?php echo count( $declared ); ?>)
						</span>
					</h2>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=turbo-cookie-policy' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'View Cookie Policy →', 'turbo-cookie-gdpr' ); ?>
					</a>
				</div>
				<div class="card-body" id="tc-declared-table-wrap">
					<?php if ( empty( $declared ) ) : ?>
					<p class="description" id="tc-empty-msg"><?php esc_html_e( 'No cookies declared yet. Add from a known service or manually above.', 'turbo-cookie-gdpr' ); ?></p>
					<?php endif; ?>
					<table class="wp-list-table widefat fixed striped" id="tc-declared-table" <?php echo empty( $declared ) ? 'style="display:none;"' : ''; ?>>
						<thead>
							<tr>
								<th style="width:18%;"><?php esc_html_e( 'Name', 'turbo-cookie-gdpr' ); ?></th>
								<th style="width:14%;"><?php esc_html_e( 'Provider', 'turbo-cookie-gdpr' ); ?></th>
								<th style="width:14%;"><?php esc_html_e( 'Category', 'turbo-cookie-gdpr' ); ?></th>
								<th style="width:12%;"><?php esc_html_e( 'Expiry', 'turbo-cookie-gdpr' ); ?></th>
								<th><?php esc_html_e( 'Description', 'turbo-cookie-gdpr' ); ?></th>
								<th style="width:70px;"></th>
							</tr>
						</thead>
						<tbody id="tc-declared-tbody">
							<?php foreach ( $declared as $idx => $c ) : ?>
							<tr id="tc-row-<?php echo esc_attr( $idx ); ?>">
								<td><code><?php echo esc_html( $c['name'] ?? '' ); ?></code></td>
								<td><?php echo esc_html( $c['provider'] ?? '' ); ?></td>
								<td>
									<span class="chip-cat chip-cat-<?php echo esc_attr( $c['category'] ?? 'functional' ); ?>"></span>
									<?php echo esc_html( ucfirst( $c['category'] ?? '' ) ); ?>
								</td>
								<td><?php echo esc_html( $c['duration'] ?? '' ); ?></td>
								<td><?php echo esc_html( $c['description'] ?? '' ); ?></td>
								<td>
									<button type="button"
										class="button button-small button-link-delete tc-delete-cookie"
										data-index="<?php echo esc_attr( $idx ); ?>">
										<?php esc_html_e( 'Delete', 'turbo-cookie-gdpr' ); ?>
									</button>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	// =========================================================================
	// F3 — Cookie Policy page render
	// =========================================================================

	/**
	 * Render Cookie Policy admin page.
	 *
	 * @since 1.1.0
	 */
	public function render_cookie_policy_page() {
		$policy_page_id = get_option( 'turbo_cookie_policy_page_id' );
		$policy_page    = $policy_page_id ? get_post( $policy_page_id ) : null;
		$is_live        = $policy_page && 'publish' === $policy_page->post_status;
		$declared_count = count( get_option( 'turbo_cookie_declared_cookies', array() ) );
		?>
		<div class="wrap turbo-cookie-wrap">
			<div class="turbo-cookie-header">
				<h1><?php esc_html_e( 'Cookie Policy', 'turbo-cookie-gdpr' ); ?></h1>
				<p><?php esc_html_e( 'A dynamic Cookie Policy page that automatically lists all your declared cookies.', 'turbo-cookie-gdpr' ); ?></p>
			</div>

			<?php if ( $is_live ) : ?>
			<div class="turbo-cookie-card">
				<div class="card-header">
					<h2><?php esc_html_e( 'Your Cookie Policy Page', 'turbo-cookie-gdpr' ); ?></h2>
					<span style="color:#22c55e;font-weight:600;">&#10003; <?php esc_html_e( 'Live', 'turbo-cookie-gdpr' ); ?></span>
				</div>
				<div class="card-body">
					<p>
						<strong><?php esc_html_e( 'URL:', 'turbo-cookie-gdpr' ); ?></strong>
						<a href="<?php echo esc_url( get_permalink( $policy_page_id ) ); ?>" target="_blank">
							<?php echo esc_html( get_permalink( $policy_page_id ) ); ?>
						</a>
					</p>
					<p>
						<a href="<?php echo esc_url( get_edit_post_link( $policy_page_id ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Edit Page', 'turbo-cookie-gdpr' ); ?>
						</a>
						<a href="<?php echo esc_url( get_permalink( $policy_page_id ) ); ?>" target="_blank" class="button button-secondary" style="margin-left:8px;">
							<?php esc_html_e( 'View Live →', 'turbo-cookie-gdpr' ); ?>
						</a>
					</p>
					<p class="description">
						<?php
						/* translators: %d: number of declared cookies */
						printf( esc_html__( 'Showing %d declared cookies. Add more from Manage Cookies.', 'turbo-cookie-gdpr' ), absint( $declared_count ) );
					?>
					</p>
				</div>
			</div>
			<?php else : ?>
			<div class="turbo-cookie-card">
				<div class="card-header"><h2><?php esc_html_e( 'Create Cookie Policy Page', 'turbo-cookie-gdpr' ); ?></h2></div>
				<div class="card-body">
					<p><?php esc_html_e( 'Click below to create a Cookie Policy page. The cookie table updates automatically as you add or remove declared cookies.', 'turbo-cookie-gdpr' ); ?></p>
					<button type="button" id="turbo-cookie-create-policy-page" class="button button-primary button-large">
						<?php esc_html_e( 'Create Cookie Policy Page', 'turbo-cookie-gdpr' ); ?>
					</button>
					<span class="turbo-cookie-save-indicator" id="turbo-policy-indicator"></span>
				</div>
			</div>
			<?php endif; ?>

			<div class="turbo-cookie-card">
				<div class="card-header"><h2><?php esc_html_e( 'Shortcode', 'turbo-cookie-gdpr' ); ?></h2></div>
				<div class="card-body">
					<p><?php esc_html_e( 'Add this to any page to display the cookie policy table:', 'turbo-cookie-gdpr' ); ?></p>
					<code style="font-size:15px;padding:8px 14px;background:#f1f5f9;display:inline-block;border-radius:4px;">[turbo_cookie_policy]</code>
					<p class="description" style="margin-top:8px;"><?php esc_html_e( 'Groups cookies by category. Updates automatically.', 'turbo-cookie-gdpr' ); ?></p>
				</div>
			</div>

			<div class="turbo-cookie-card">
				<div class="card-header"><h2><?php esc_html_e( 'Declared Cookies', 'turbo-cookie-gdpr' ); ?></h2></div>
				<div class="card-body">
					<?php if ( $declared_count > 0 ) : ?>
					<p>
						<?php
						/* translators: %d: number of declared cookies */
						printf( esc_html__( '%d cookies declared.', 'turbo-cookie-gdpr' ), absint( $declared_count ) );
					?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=turbo-cookie-manage' ) ); ?>" style="margin-left:8px;">
							<?php esc_html_e( 'Manage →', 'turbo-cookie-gdpr' ); ?>
						</a>
					</p>
					<?php else : ?>
					<p>
						<?php esc_html_e( 'No cookies declared yet.', 'turbo-cookie-gdpr' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=turbo-cookie-manage' ) ); ?>" style="margin-left:8px;">
							<?php esc_html_e( 'Add cookies →', 'turbo-cookie-gdpr' ); ?>
						</a>
					</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	// =========================================================================
	// F2 AJAX handlers
	// =========================================================================

	/**
	 * AJAX: Save a declared cookie (single or batch from service).
	 *
	 * @since 1.1.0
	 */
	public function ajax_save_cookie() {
		check_ajax_referer( 'turbo_cookie_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$valid_cats = array( 'essential', 'functional', 'analytics', 'marketing' );
		$declared   = get_option( 'turbo_cookie_declared_cookies', array() );

		// Batch from known service.
		$cookies_json = isset( $_POST['cookies_json'] ) ? sanitize_text_field( wp_unslash( $_POST['cookies_json'] ) ) : '';
		if ( ! empty( $cookies_json ) ) {
			$batch = json_decode( $cookies_json, true );
			if ( is_array( $batch ) ) {
				$added = 0;
				foreach ( $batch as $c ) {
					$n = sanitize_text_field( $c['name'] ?? '' );
					if ( empty( $n ) ) continue;
					$cat   = in_array( $c['category'] ?? '', $valid_cats, true ) ? $c['category'] : 'functional';
					$entry = array(
						'name'        => $n,
						'provider'    => sanitize_text_field( $c['provider'] ?? '' ),
						'category'    => $cat,
						'description' => sanitize_text_field( $c['description'] ?? '' ),
						'duration'    => sanitize_text_field( $c['duration'] ?? '' ),
					);
					$found = false;
					foreach ( $declared as $i => $ex ) {
						if ( $ex['name'] === $n ) { $declared[ $i ] = $entry; $found = true; break; }
					}
					if ( ! $found ) { $declared[] = $entry; $added++; }
				}
				update_option( 'turbo_cookie_declared_cookies', array_values( $declared ) );
				/* translators: %d: number of cookies */
				wp_send_json_success( array( 'message' => sprintf( __( '%d cookies added.', 'turbo-cookie-gdpr' ), $added ), 'total' => count( $declared ) ) );
			}
		}

		// Single cookie.
		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		if ( empty( $name ) ) {
			wp_send_json_error( __( 'Cookie name is required.', 'turbo-cookie-gdpr' ) );
		}

		$cat = sanitize_text_field( wp_unslash( $_POST['category'] ?? 'functional' ) );
		if ( ! in_array( $cat, $valid_cats, true ) ) { $cat = 'functional'; }

		$entry = array(
			'name'        => $name,
			'provider'    => sanitize_text_field( wp_unslash( $_POST['provider'] ?? '' ) ),
			'category'    => $cat,
			'description' => sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) ),
			'duration'    => sanitize_text_field( wp_unslash( $_POST['duration'] ?? '' ) ),
		);

		$found = false;
		foreach ( $declared as $i => $ex ) {
			if ( $ex['name'] === $name ) { $declared[ $i ] = $entry; $found = true; break; }
		}
		if ( ! $found ) { $declared[] = $entry; }

		update_option( 'turbo_cookie_declared_cookies', array_values( $declared ) );
		wp_send_json_success( array( 'message' => __( 'Cookie saved.', 'turbo-cookie-gdpr' ), 'cookie' => $entry, 'total' => count( $declared ) ) );
	}

	/**
	 * AJAX: Delete a declared cookie by array index.
	 *
	 * @since 1.1.0
	 */
	public function ajax_delete_cookie() {
		check_ajax_referer( 'turbo_cookie_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$index    = absint( $_POST['index'] ?? 0 );
		$declared = get_option( 'turbo_cookie_declared_cookies', array() );

		if ( ! array_key_exists( $index, $declared ) ) {
			wp_send_json_error( __( 'Cookie not found.', 'turbo-cookie-gdpr' ) );
		}

		array_splice( $declared, $index, 1 );
		update_option( 'turbo_cookie_declared_cookies', array_values( $declared ) );
		wp_send_json_success( array( 'message' => __( 'Cookie deleted.', 'turbo-cookie-gdpr' ) ) );
	}

	/**
	 * AJAX: Return known services list as JSON.
	 *
	 * @since 1.1.0
	 */
	public function ajax_get_known_services() {
		check_ajax_referer( 'turbo_cookie_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}
		wp_send_json_success( array( 'services' => $this->get_known_services_list() ) );
	}

	/**
	 * Built-in known services — purely local, no API.
	 *
	 * @since 1.1.0
	 * @return array
	 */
	private function get_known_services_list() {
		return array(
			array( 'name' => 'Google Analytics',   'category' => 'analytics', 'description' => 'Web analytics by Google.',
				'cookies' => array(
					array( 'name' => '_ga',   'provider' => 'Google', 'category' => 'analytics', 'duration' => '2 years',   'description' => 'Distinguishes unique users' ),
					array( 'name' => '_gid',  'provider' => 'Google', 'category' => 'analytics', 'duration' => '24 hours',  'description' => 'Distinguishes users, 24h' ),
					array( 'name' => '_ga_*', 'provider' => 'Google', 'category' => 'analytics', 'duration' => '2 years',   'description' => 'GA4 session storage' ),
					array( 'name' => '_gat',  'provider' => 'Google', 'category' => 'analytics', 'duration' => '1 minute',  'description' => 'Throttles request rate' ),
				),
			),
			array( 'name' => 'Google Tag Manager', 'category' => 'analytics', 'description' => 'Tag management by Google.',
				'cookies' => array(
					array( 'name' => '_gcl_au', 'provider' => 'Google', 'category' => 'analytics', 'duration' => '3 months', 'description' => 'Conversion linker' ),
				),
			),
			array( 'name' => 'Facebook / Meta Pixel', 'category' => 'marketing', 'description' => 'Meta advertising pixel.',
				'cookies' => array(
					array( 'name' => '_fbp', 'provider' => 'Meta', 'category' => 'marketing', 'duration' => '3 months', 'description' => 'Tracks visits across sites' ),
					array( 'name' => '_fbc', 'provider' => 'Meta', 'category' => 'marketing', 'duration' => '2 years',  'description' => 'Stores last click ID' ),
					array( 'name' => 'fr',   'provider' => 'Meta', 'category' => 'marketing', 'duration' => '3 months', 'description' => 'Advertising and tracking' ),
				),
			),
			array( 'name' => 'Google Ads', 'category' => 'marketing', 'description' => 'Google advertising platform.',
				'cookies' => array(
					array( 'name' => '_gcl_aw', 'provider' => 'Google', 'category' => 'marketing', 'duration' => '3 months', 'description' => 'Click tracking' ),
					array( 'name' => 'IDE',     'provider' => 'Google', 'category' => 'marketing', 'duration' => '2 years',  'description' => 'Ad targeting' ),
				),
			),
			array( 'name' => 'Hotjar', 'category' => 'analytics', 'description' => 'Heatmaps and session recording.',
				'cookies' => array(
					array( 'name' => '_hjid',           'provider' => 'Hotjar', 'category' => 'analytics', 'duration' => '1 year',    'description' => 'User identifier' ),
					array( 'name' => '_hjSessionUser_*','provider' => 'Hotjar', 'category' => 'analytics', 'duration' => '1 year',    'description' => 'Session user data' ),
					array( 'name' => '_hjSession_*',    'provider' => 'Hotjar', 'category' => 'analytics', 'duration' => '30 minutes','description' => 'Current session' ),
				),
			),
			array( 'name' => 'Microsoft Clarity', 'category' => 'analytics', 'description' => 'Free heatmap and recording.',
				'cookies' => array(
					array( 'name' => '_clck', 'provider' => 'Microsoft', 'category' => 'analytics', 'duration' => '1 year', 'description' => 'User identifier' ),
					array( 'name' => '_clsk', 'provider' => 'Microsoft', 'category' => 'analytics', 'duration' => '1 day',  'description' => 'Aggregates pageviews' ),
					array( 'name' => 'MUID',  'provider' => 'Microsoft', 'category' => 'analytics', 'duration' => '1 year', 'description' => 'Unique user ID' ),
				),
			),
			array( 'name' => 'YouTube', 'category' => 'functional', 'description' => 'YouTube video embeds.',
				'cookies' => array(
					array( 'name' => 'YSC',               'provider' => 'YouTube', 'category' => 'functional', 'duration' => 'Session',  'description' => 'Session ID' ),
					array( 'name' => 'VISITOR_INFO1_LIVE','provider' => 'YouTube', 'category' => 'functional', 'duration' => '6 months', 'description' => 'Bandwidth estimation' ),
					array( 'name' => 'PREF',              'provider' => 'YouTube', 'category' => 'functional', 'duration' => '2 years',  'description' => 'User preferences' ),
				),
			),
			array( 'name' => 'Google Maps', 'category' => 'functional', 'description' => 'Embedded maps.',
				'cookies' => array(
					array( 'name' => 'NID',    'provider' => 'Google', 'category' => 'functional', 'duration' => '6 months', 'description' => 'Map preferences' ),
					array( 'name' => 'CONSENT','provider' => 'Google', 'category' => 'functional', 'duration' => '2 years',  'description' => 'Consent state' ),
				),
			),
			array( 'name' => 'LinkedIn Insight', 'category' => 'marketing', 'description' => 'LinkedIn conversion tracking.',
				'cookies' => array(
					array( 'name' => 'li_sugr',          'provider' => 'LinkedIn', 'category' => 'marketing', 'duration' => '3 months', 'description' => 'Browser identifier' ),
					array( 'name' => 'bcookie',          'provider' => 'LinkedIn', 'category' => 'marketing', 'duration' => '2 years',  'description' => 'Browser ID' ),
					array( 'name' => 'UserMatchHistory', 'provider' => 'LinkedIn', 'category' => 'marketing', 'duration' => '30 days',  'description' => 'Ad ID syncing' ),
				),
			),
			array( 'name' => 'TikTok Pixel', 'category' => 'marketing', 'description' => 'TikTok advertising pixel.',
				'cookies' => array(
					array( 'name' => '_ttp',        'provider' => 'TikTok', 'category' => 'marketing', 'duration' => '1 year', 'description' => 'Pixel measurement' ),
					array( 'name' => 'tt_webid_v2', 'provider' => 'TikTok', 'category' => 'marketing', 'duration' => '1 year', 'description' => 'Visitor identifier' ),
				),
			),
			array( 'name' => 'HubSpot', 'category' => 'marketing', 'description' => 'CRM and marketing automation.',
				'cookies' => array(
					array( 'name' => 'hubspotutk', 'provider' => 'HubSpot', 'category' => 'marketing', 'duration' => '13 months', 'description' => 'Visitor identity token' ),
					array( 'name' => '__hstc',     'provider' => 'HubSpot', 'category' => 'marketing', 'duration' => '13 months', 'description' => 'Main tracking cookie' ),
					array( 'name' => '__hssc',     'provider' => 'HubSpot', 'category' => 'marketing', 'duration' => '30 minutes','description' => 'Session cookie' ),
				),
			),
			array( 'name' => 'Stripe', 'category' => 'functional', 'description' => 'Payment processing.',
				'cookies' => array(
					array( 'name' => '__stripe_mid', 'provider' => 'Stripe', 'category' => 'functional', 'duration' => '1 year',    'description' => 'Fraud prevention - machine ID' ),
					array( 'name' => '__stripe_sid', 'provider' => 'Stripe', 'category' => 'functional', 'duration' => '30 minutes','description' => 'Fraud prevention - session ID' ),
				),
			),
			array( 'name' => 'Cloudflare', 'category' => 'essential', 'description' => 'CDN and security.',
				'cookies' => array(
					array( 'name' => '__cf_bm',      'provider' => 'Cloudflare', 'category' => 'essential', 'duration' => '30 minutes','description' => 'Bot management' ),
					array( 'name' => 'cf_clearance', 'provider' => 'Cloudflare', 'category' => 'essential', 'duration' => '30 minutes','description' => 'Challenge clearance' ),
				),
			),
			array( 'name' => 'WooCommerce', 'category' => 'essential', 'description' => 'Shopping cart cookies.',
				'cookies' => array(
					array( 'name' => 'woocommerce_cart_hash',    'provider' => 'WooCommerce', 'category' => 'essential', 'duration' => 'Session', 'description' => 'Cart hash' ),
					array( 'name' => 'woocommerce_items_in_cart','provider' => 'WooCommerce', 'category' => 'essential', 'duration' => 'Session', 'description' => 'Items in cart flag' ),
					array( 'name' => 'wp_woocommerce_session_*','provider' => 'WooCommerce', 'category' => 'essential', 'duration' => '2 days',  'description' => 'Session data' ),
				),
			),
			array( 'name' => 'Vimeo', 'category' => 'functional', 'description' => 'Vimeo video embeds.',
				'cookies' => array(
					array( 'name' => 'vimeo',  'provider' => 'Vimeo', 'category' => 'functional', 'duration' => '2 years',  'description' => 'Player preference' ),
					array( 'name' => '__utmz', 'provider' => 'Vimeo', 'category' => 'functional', 'duration' => '6 months', 'description' => 'Traffic source' ),
				),
			),
			array( 'name' => 'Intercom', 'category' => 'functional', 'description' => 'Customer messaging.',
				'cookies' => array(
					array( 'name' => 'intercom-id-*',      'provider' => 'Intercom', 'category' => 'functional', 'duration' => '9 months', 'description' => 'Anonymous visitor ID' ),
					array( 'name' => 'intercom-session-*', 'provider' => 'Intercom', 'category' => 'functional', 'duration' => '1 week',   'description' => 'Session token' ),
				),
			),
			array( 'name' => 'Tawk.to', 'category' => 'functional', 'description' => 'Free live chat.',
				'cookies' => array(
					array( 'name' => '__tawkuuid',         'provider' => 'Tawk.to', 'category' => 'functional', 'duration' => '6 months', 'description' => 'Unique visitor ID' ),
					array( 'name' => 'TawkConnectionTime', 'provider' => 'Tawk.to', 'category' => 'functional', 'duration' => 'Session',  'description' => 'Connection time' ),
				),
			),
		);
	}
}
