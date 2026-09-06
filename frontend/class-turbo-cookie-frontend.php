<?php
/**
 * Frontend banner and preferences modal rendering.
 *
 * @package TurboCookie
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Turbo_Cookie_Frontend
 *
 * @since 1.0.0
 */
class Turbo_Cookie_Frontend {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$settings = Turbo_Cookie::get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_banner' ), 100 );
		add_action( 'wp_footer', array( $this, 'render_preferences_modal' ), 101 );
		add_shortcode( 'turbo_cookie_preferences', array( $this, 'shortcode_preferences_button' ) );
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'turbo-cookie-frontend',
			TURBO_COOKIE_URL . 'frontend/css/turbo-cookie-frontend.css',
			array(),
			TURBO_COOKIE_VERSION
		);

		wp_enqueue_script(
			'turbo-cookie-frontend',
			TURBO_COOKIE_URL . 'frontend/js/turbo-cookie-frontend.js',
			array(),
			TURBO_COOKIE_VERSION,
			true
		);

		$settings   = Turbo_Cookie::get_settings();
		$banner     = Turbo_Cookie::get_banner_settings();
		$categories = Turbo_Cookie::get_categories();
		$gcm        = Turbo_Cookie::get_gcm_settings();

		wp_localize_script( 'turbo-cookie-frontend', 'turboCookieConfig', array(
			'restUrl'          => rest_url( 'turbo-cookie/v1/' ),
			'nonce'            => wp_create_nonce( 'wp_rest' ),
			'cookieName'       => $settings['cookie_name'],
			'consentExpiry'    => absint( $settings['consent_expiry'] ),
			'reconsentVersion' => $settings['reconsent_version'],
			'gcmEnabled'       => ! empty( $settings['gcm_enabled'] ),
			'categories'       => $categories,
			'banner'           => $banner,
		) );

		// Inline CSS variables for banner customization.
		$custom_css = $this->generate_custom_css( $banner );
		wp_add_inline_style( 'turbo-cookie-frontend', $custom_css );
	}

	/**
	 * Render the cookie consent banner.
	 *
	 * @since 1.0.0
	 */
	public function render_banner() {
		$banner     = Turbo_Cookie::get_banner_settings();
		$settings   = Turbo_Cookie::get_settings();
		$position   = $banner['position'];
		$layout     = $banner['layout'];
		$animation  = $banner['animation'];
		?>
		<div id="turbo-cookie-banner"
			 class="turbo-cookie-banner turbo-cookie-position-<?php echo esc_attr( $position ); ?> turbo-cookie-layout-<?php echo esc_attr( $layout ); ?> turbo-cookie-anim-<?php echo esc_attr( $animation ); ?>"
			 role="dialog"
			 aria-label="<?php esc_attr_e( 'Cookie Consent', 'turbo-cookie-gdpr' ); ?>"
			 aria-modal="false"
			 style="display:none;">

			<div class="turbo-cookie-banner-inner">
				<?php if ( ! empty( $banner['show_logo'] ) && ! empty( $banner['logo_url'] ) ) : ?>
					<div class="turbo-cookie-banner-logo">
						<img src="<?php echo esc_url( $banner['logo_url'] ); ?>" alt="" />
					</div>
				<?php endif; ?>

				<div class="turbo-cookie-banner-content">
					<?php if ( ! empty( $banner['title'] ) ) : ?>
						<h3 class="turbo-cookie-banner-title"><?php echo esc_html( $banner['title'] ); ?></h3>
					<?php endif; ?>

					<p class="turbo-cookie-banner-message">
						<?php echo wp_kses_post( $banner['message'] ); ?>
						<?php if ( ! empty( $banner['privacy_policy_url'] ) ) : ?>
							<a href="<?php echo esc_url( $banner['privacy_policy_url'] ); ?>" class="turbo-cookie-policy-link" target="_blank" rel="noopener">
								<?php echo esc_html( $banner['privacy_policy_text'] ); ?>
							</a>
						<?php endif; ?>
					</p>
				</div>

				<div class="turbo-cookie-banner-actions">
					<button type="button" class="turbo-cookie-btn turbo-cookie-btn-accept" data-turbo-cookie-action="accept-all">
						<?php echo esc_html( $banner['accept_text'] ); ?>
					</button>

					<button type="button" class="turbo-cookie-btn turbo-cookie-btn-essential" data-turbo-cookie-action="accept-essential">
						<?php esc_html_e( 'Only Essential', 'turbo-cookie-gdpr' ); ?>
					</button>

					<?php if ( ! empty( $banner['show_decline'] ) ) : ?>
					<button type="button" class="turbo-cookie-btn turbo-cookie-btn-decline" data-turbo-cookie-action="decline">
						<?php echo esc_html( $banner['decline_text'] ); ?>
					</button>
					<?php endif; ?>

					<button type="button" class="turbo-cookie-btn turbo-cookie-btn-preferences" data-turbo-cookie-action="open-preferences">
						<?php echo esc_html( $banner['preferences_text'] ); ?>
					</button>
				</div>

				<?php if ( ! empty( $settings['show_credit'] ) ) : ?>
				<div class="turbo-cookie-banner-credit">
					<a href="https://wp-turbo.com/turbo-cookie/" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Powered by Turbo Cookie', 'turbo-cookie-gdpr' ); ?>
					</a>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the preferences modal.
	 *
	 * @since 1.0.0
	 */
	public function render_preferences_modal() {
		$banner     = Turbo_Cookie::get_banner_settings();
		$categories = Turbo_Cookie::get_categories();
		?>
		<div id="turbo-cookie-modal-overlay" class="turbo-cookie-modal-overlay" style="display:none;"></div>

		<div id="turbo-cookie-modal"
			 class="turbo-cookie-modal"
			 role="dialog"
			 aria-label="<?php esc_attr_e( 'Cookie Preferences', 'turbo-cookie-gdpr' ); ?>"
			 aria-modal="true"
			 style="display:none;">

			<div class="turbo-cookie-modal-inner">
				<div class="turbo-cookie-modal-header">
					<h2><?php esc_html_e( 'Cookie Preferences', 'turbo-cookie-gdpr' ); ?></h2>
					<button type="button" class="turbo-cookie-modal-close" data-turbo-cookie-action="close-modal" aria-label="<?php esc_attr_e( 'Close', 'turbo-cookie-gdpr' ); ?>">
						<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
					</button>
				</div>

				<div class="turbo-cookie-modal-body">
					<p class="turbo-cookie-modal-description">
						<?php esc_html_e( 'Choose which cookie categories you want to allow. You can change these preferences at any time.', 'turbo-cookie-gdpr' ); ?>
					</p>

					<div class="turbo-cookie-categories">
						<?php foreach ( $categories as $slug => $category ) : ?>
							<?php if ( empty( $category['enabled'] ) ) continue; ?>
							<div class="turbo-cookie-category" data-category="<?php echo esc_attr( $slug ); ?>">
								<div class="turbo-cookie-category-header">
									<div class="turbo-cookie-category-info">
										<h4 class="turbo-cookie-category-name"><?php echo esc_html( $category['name'] ); ?></h4>
										<?php if ( ! empty( $category['required'] ) ) : ?>
											<span class="turbo-cookie-always-on"><?php esc_html_e( 'Always Active', 'turbo-cookie-gdpr' ); ?></span>
										<?php endif; ?>
									</div>
									<div class="turbo-cookie-category-toggle">
										<?php if ( ! empty( $category['required'] ) ) : ?>
											<input type="checkbox" checked disabled id="turbo-cat-<?php echo esc_attr( $slug ); ?>" />
										<?php else : ?>
											<label class="turbo-cookie-switch">
												<input type="checkbox" id="turbo-cat-<?php echo esc_attr( $slug ); ?>" data-category="<?php echo esc_attr( $slug ); ?>" />
												<span class="turbo-cookie-slider"></span>
											</label>
										<?php endif; ?>
									</div>
								</div>
								<p class="turbo-cookie-category-desc"><?php echo esc_html( $category['description'] ); ?></p>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="turbo-cookie-modal-footer">
					<button type="button" class="turbo-cookie-btn turbo-cookie-btn-save" data-turbo-cookie-action="save-preferences">
						<?php echo esc_html( $banner['save_text'] ); ?>
					</button>
					<button type="button" class="turbo-cookie-btn turbo-cookie-btn-accept" data-turbo-cookie-action="accept-all">
						<?php echo esc_html( $banner['accept_text'] ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Shortcode for cookie preferences re-open button.
	 *
	 * Usage: [turbo_cookie_preferences text="Cookie Settings"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_preferences_button( $atts ) {
		$atts = shortcode_atts( array(
			'text'  => __( 'Cookie Preferences', 'turbo-cookie-gdpr' ),
			'class' => '',
		), $atts, 'turbo_cookie_preferences' );

		$class = 'turbo-cookie-reopen-btn';
		if ( ! empty( $atts['class'] ) ) {
			$class .= ' ' . esc_attr( $atts['class'] );
		}

		return sprintf(
			'<button type="button" class="%s" data-turbo-cookie-action="open-preferences">%s</button>',
			esc_attr( $class ),
			esc_html( $atts['text'] )
		);
	}

	/**
	 * Generate inline CSS custom properties from banner settings.
	 *
	 * @since 1.0.0
	 * @param array $banner Banner settings.
	 * @return string CSS.
	 */
	private function generate_custom_css( $banner ) {
		$css = ':root {';
		$css .= '--tc-bg: ' . Turbo_Cookie::sanitize_css_color( $banner['bg_color'], '#1e293b' ) . ';';
		$css .= '--tc-text: ' . Turbo_Cookie::sanitize_css_color( $banner['text_color'], '#f1f5f9' ) . ';';
		$css .= '--tc-btn-accept-bg: ' . Turbo_Cookie::sanitize_css_color( $banner['btn_accept_bg'], '#3b82f6' ) . ';';
		$css .= '--tc-btn-accept-text: ' . Turbo_Cookie::sanitize_css_color( $banner['btn_accept_text'], '#ffffff' ) . ';';
		$css .= '--tc-btn-decline-bg: ' . Turbo_Cookie::sanitize_css_color( $banner['btn_decline_bg'], 'transparent' ) . ';';
		$css .= '--tc-btn-decline-text: ' . Turbo_Cookie::sanitize_css_color( $banner['btn_decline_text'], '#94a3b8' ) . ';';
		$css .= '--tc-btn-decline-border: ' . Turbo_Cookie::sanitize_css_color( $banner['btn_decline_border'], '#475569' ) . ';';
		$css .= '--tc-btn-prefs-bg: ' . Turbo_Cookie::sanitize_css_color( $banner['btn_prefs_bg'], 'transparent' ) . ';';
		$css .= '--tc-btn-prefs-text: ' . Turbo_Cookie::sanitize_css_color( $banner['btn_prefs_text'], '#94a3b8' ) . ';';
		$css .= '--tc-btn-prefs-border: ' . Turbo_Cookie::sanitize_css_color( $banner['btn_prefs_border'], '#475569' ) . ';';
		$css .= '}';

		return $css;
	}
}
