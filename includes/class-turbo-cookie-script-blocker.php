<?php
/**
 * Script blocking until consent is given.
 *
 * Blocks <script>, <iframe>, <embed>, and <object> tags for known
 * third-party services. Blocked iframes/embeds show a named placeholder
 * with an "Accept & Load" button instead of blank space.
 *
 * @package TurboCookie
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Turbo_Cookie_Script_Blocker
 *
 * @since 1.0.0
 */
class Turbo_Cookie_Script_Blocker {

	/**
	 * Script/iframe patterns mapped to categories and service labels.
	 * Structure: category => [ pattern => label ]
	 *
	 * @var array
	 */
	private $script_patterns = array();

	/**
	 * Iframe patterns mapped to categories and labels.
	 *
	 * @var array
	 */
	private $iframe_patterns = array();

	/**
	 * Output buffer level opened by this blocker, used to close it safely.
	 *
	 * @var int
	 */
	private $buffer_level = 0;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->init_patterns();
		$this->register_hooks();
	}

	/**
	 * Initialize known service patterns.
	 * Each entry: pattern => [ category, label ]
	 *
	 * @since 1.0.0
	 */
	private function init_patterns() {

		// Script patterns: domain/path string => [ category, human label ]
		$raw_script_patterns = array(
			// Analytics.
			'google-analytics.com'    => array( 'analytics', 'Google Analytics' ),
			'googletagmanager.com'    => array( 'analytics', 'Google Tag Manager' ),
			'gtag/js'                 => array( 'analytics', 'Google Tag' ),
			'analytics.js'            => array( 'analytics', 'Google Analytics' ),
			'ga.js'                   => array( 'analytics', 'Google Analytics' ),
			'plausible.io'            => array( 'analytics', 'Plausible Analytics' ),
			'matomo'                  => array( 'analytics', 'Matomo' ),
			'piwik'                   => array( 'analytics', 'Matomo' ),
			'hotjar.com'              => array( 'analytics', 'Hotjar' ),
			'clarity.ms'              => array( 'analytics', 'Microsoft Clarity' ),
			'mixpanel.com'            => array( 'analytics', 'Mixpanel' ),
			'segment.com'             => array( 'analytics', 'Segment' ),
			'heap-analytics'          => array( 'analytics', 'Heap' ),
			'amplitude.com'           => array( 'analytics', 'Amplitude' ),
			'fullstory.com'           => array( 'analytics', 'FullStory' ),
			'mc.yandex.ru'            => array( 'analytics', 'Yandex Metrica' ),
			'cdn.mouseflow.com'       => array( 'analytics', 'Mouseflow' ),
			'smartlook.com'           => array( 'analytics', 'Smartlook' ),
			'logrocket.com'           => array( 'analytics', 'LogRocket' ),
			// Marketing.
			'facebook.net'            => array( 'marketing', 'Facebook' ),
			'fbevents.js'             => array( 'marketing', 'Facebook Pixel' ),
			'connect.facebook.net'    => array( 'marketing', 'Facebook SDK' ),
			'doubleclick.net'         => array( 'marketing', 'Google Ads' ),
			'googlesyndication.com'   => array( 'marketing', 'Google AdSense' ),
			'googleadservices.com'    => array( 'marketing', 'Google Ads' ),
			'adsbygoogle'             => array( 'marketing', 'Google AdSense' ),
			'linkedin.com/insight'    => array( 'marketing', 'LinkedIn Insight' ),
			'snap.licdn.com'          => array( 'marketing', 'LinkedIn' ),
			'ads-twitter.com'         => array( 'marketing', 'Twitter Ads' ),
			'tiktok.com/i18n'         => array( 'marketing', 'TikTok Pixel' ),
			'analytics.tiktok.com'    => array( 'marketing', 'TikTok Analytics' ),
			'pinterest.com/ct'        => array( 'marketing', 'Pinterest Tag' ),
			'bat.bing.com'            => array( 'marketing', 'Microsoft Advertising' ),
			'criteo.com'              => array( 'marketing', 'Criteo' ),
			'outbrain.com'            => array( 'marketing', 'Outbrain' ),
			'taboola.com'             => array( 'marketing', 'Taboola' ),
			'tr.snapchat.com'         => array( 'marketing', 'Snapchat Pixel' ),
			'static.ads-twitter.com'  => array( 'marketing', 'Twitter/X Ads' ),
			'sc-static.net'           => array( 'marketing', 'Snapchat' ),
			'reddit.com/ad'           => array( 'marketing', 'Reddit Ads' ),
			// Functional.
			'maps.googleapis.com'     => array( 'functional', 'Google Maps' ),
			'maps.google.com'         => array( 'functional', 'Google Maps' ),
			'recaptcha'               => array( 'functional', 'Google reCAPTCHA' ),
			'platform.twitter.com'    => array( 'functional', 'Twitter/X Embed' ),
			'instagram.com/embed'     => array( 'functional', 'Instagram Embed' ),
			'disqus.com'              => array( 'functional', 'Disqus Comments' ),
			'addthis.com'             => array( 'functional', 'AddThis' ),
			'sharethis.com'           => array( 'functional', 'ShareThis' ),
			'tawk.to'                 => array( 'functional', 'Tawk.to Chat' ),
			'intercom'                => array( 'functional', 'Intercom Chat' ),
			'drift.com'               => array( 'functional', 'Drift Chat' ),
			'crisp.chat'              => array( 'functional', 'Crisp Chat' ),
			'livechatinc.com'         => array( 'functional', 'LiveChat' ),
			'zopim.com'               => array( 'functional', 'Zendesk Chat' ),
			'tidio.com'               => array( 'functional', 'Tidio Chat' ),
			'freshchat.com'           => array( 'functional', 'Freshchat' ),
		);

		// Iframe patterns: domain/path => [ category, label ]
		$raw_iframe_patterns = array(
			'youtube.com/embed'       => array( 'functional', 'YouTube' ),
			'youtube-nocookie.com'    => array( 'functional', 'YouTube' ),
			'player.vimeo.com'        => array( 'functional', 'Vimeo' ),
			'maps.google.com'         => array( 'functional', 'Google Maps' ),
			'maps.googleapis.com'     => array( 'functional', 'Google Maps' ),
			'google.com/maps'         => array( 'functional', 'Google Maps' ),
			'platform.twitter.com'    => array( 'functional', 'Twitter/X' ),
			'instagram.com/p/'        => array( 'functional', 'Instagram' ),
			'facebook.com/plugins'    => array( 'functional', 'Facebook Widget' ),
			'open.spotify.com'        => array( 'functional', 'Spotify' ),
			'w.soundcloud.com'        => array( 'functional', 'SoundCloud' ),
			'embed.ted.com'           => array( 'functional', 'TED' ),
			'player.twitch.tv'        => array( 'functional', 'Twitch' ),
			'dailymotion.com/embed'   => array( 'functional', 'Dailymotion' ),
			'disqus.com'              => array( 'functional', 'Disqus' ),
			'doubleclick.net'         => array( 'marketing', 'Google Ads' ),
			'googlesyndication.com'   => array( 'marketing', 'Google AdSense' ),
			'tiktok.com/embed'        => array( 'functional', 'TikTok' ),
		);

		/**
		 * Filter script blocking patterns.
		 *
		 * @since 1.0.0
		 * @param array $patterns Pattern => [category, label] pairs.
		 */
		$this->script_patterns = apply_filters( 'turbo_cookie_script_patterns', $raw_script_patterns );

		/**
		 * Filter iframe blocking patterns.
		 *
		 * @since 1.0.0
		 * @param array $patterns Pattern => [category, label] pairs.
		 */
		$this->iframe_patterns = apply_filters( 'turbo_cookie_iframe_patterns', $raw_iframe_patterns );
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	private function register_hooks() {
		$settings = Turbo_Cookie::get_settings();

		if ( empty( $settings['script_blocking'] ) || empty( $settings['enabled'] ) ) {
			return;
		}

		if ( is_admin() || wp_doing_ajax() || wp_doing_cron()
			|| ( defined( 'WP_CLI' ) && WP_CLI )
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		// WordPress 6.9+ ships a standardized template enhancement output buffer.
		// Prefer it so core manages the buffer lifecycle (it is opened and closed
		// by WordPress itself, so it can never be left open).
		if ( function_exists( 'wp_should_output_buffer_template_for_enhancement' ) ) {
			add_filter( 'wp_template_enhancement_output_buffer', array( $this, 'process_buffer' ) );
		} else {
			// WP < 6.9 fallback: open a buffer manually and always close it on shutdown.
			add_action( 'template_redirect', array( $this, 'start_buffer' ), 1 );
		}

		add_filter( 'script_loader_tag', array( $this, 'filter_script_tag' ), 999, 3 );
	}

	/**
	 * Start output buffering (WordPress < 6.9 fallback only).
	 *
	 * On WordPress 6.9+ the plugin uses the core template enhancement output
	 * buffer instead, so this method is not used there.
	 *
	 * @since 1.0.0
	 */
	public function start_buffer() {
		$consent = new Turbo_Cookie_Consent();
		$state   = $consent->get_consent_state();

		if ( $state && ! empty( $state['action'] ) && 'accepted' === $state['action'] ) {
			return;
		}

		ob_start( array( $this, 'process_buffer' ) );
		$this->buffer_level = ob_get_level();

		// Explicitly close the buffer on shutdown so it is never left open.
		add_action( 'shutdown', array( $this, 'close_buffer' ) );
	}

	/**
	 * Explicitly close the output buffer opened by this blocker.
	 *
	 * @since 1.0.1
	 */
	public function close_buffer() {
		if ( $this->buffer_level > 0 && ob_get_level() === $this->buffer_level ) {
			ob_end_flush();
			$this->buffer_level = 0;
		}
	}

	/**
	 * Process full page HTML.
	 *
	 * @since 1.0.0
	 * @param string $html Full page HTML.
	 * @return string Modified HTML.
	 */
	public function process_buffer( $html ) {
		if ( empty( $html ) ) {
			return $html;
		}

		if ( false === strpos( $html, '</html>' ) && false === strpos( $html, '</HTML>' ) ) {
			return $html;
		}

		$html = $this->block_inline_scripts( $html );
		$html = $this->block_iframes( $html );
		$html = $this->block_embeds( $html );
		$html = $this->block_objects( $html );

		return $html;
	}

	/**
	 * Block inline and external script tags.
	 *
	 * @since 1.0.0
	 * @param string $html Page HTML.
	 * @return string Modified HTML.
	 */
	private function block_inline_scripts( $html ) {
		return preg_replace_callback(
			'/<script\b([^>]*)>(.*?)<\/script>/is',
			function ( $matches ) {
				$attributes = $matches[1];
				$content    = $matches[2];
				$full_tag   = $matches[0];

				if ( false !== strpos( $attributes, 'data-turbo-cookie' ) ) {
					return $full_tag;
				}
			if ( false !== strpos( $attributes, 'turbo-cookie' )
					|| false !== strpos( $attributes, 'turbo_cookie' ) ) {
					return $full_tag;
				}

				$result = $this->detect_service( $attributes . ' ' . $content, 'script' );
				if ( empty( $result ) ) {
					return $full_tag;
				}

				$new_attrs = preg_replace( '/\btype\s*=\s*["\'][^"\']*["\']/i', '', $attributes );
				$new_attrs = ' type="text/plain"'
					. ' data-turbo-cookie-category="' . esc_attr( $result['category'] ) . '"'
					. ' data-turbo-cookie-service="' . esc_attr( $result['label'] ) . '"'
					. ' data-turbo-cookie-blocked="true"'
					. $new_attrs;

				return '<script' . $new_attrs . '>' . $content . '</script>';
			},
			$html
		);
	}

	/**
	 * Block iframes — replace with named placeholder.
	 *
	 * @since 1.0.0
	 * @param string $html Page HTML.
	 * @return string Modified HTML.
	 */
	private function block_iframes( $html ) {
		return preg_replace_callback(
			'/<iframe\b([^>]*)(?:>(.*?)<\/iframe>|\s*\/>)/is',
			function ( $matches ) {
				$attributes = $matches[1];
				$full_tag   = $matches[0];

				if ( false !== strpos( $attributes, 'data-turbo-cookie' ) ) {
					return $full_tag;
				}

				$result = $this->detect_service( $attributes, 'iframe' );
				if ( empty( $result ) ) {
					return $full_tag;
				}

				// Extract src.
				$src = '';
				if ( preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $attributes, $m ) ) {
					$src = $m[1];
				}

				if ( empty( $src ) ) {
					return $full_tag;
				}

				// Extract width/height for placeholder sizing.
				$width  = '';
				$height = '';
				if ( preg_match( '/\bwidth\s*=\s*["\']?(\d+)/i', $attributes, $m ) ) {
					$width = $m[1];
				}
				if ( preg_match( '/\bheight\s*=\s*["\']?(\d+)/i', $attributes, $m ) ) {
					$height = $m[1];
				}

				// Strip src from attributes.
				$new_attrs = preg_replace( '/\bsrc\s*=\s*["\'][^"\']*["\']/i', '', $attributes );

				return $this->build_placeholder( 'iframe', $src, $new_attrs, $result, $width, $height );
			},
			$html
		);
	}

	/**
	 * Block embed tags — replace with named placeholder.
	 *
	 * @since 1.0.0
	 * @param string $html Page HTML.
	 * @return string Modified HTML.
	 */
	private function block_embeds( $html ) {
		return preg_replace_callback(
			'/<embed\b([^>]*)\/?>/is',
			function ( $matches ) {
				$attributes = $matches[1];
				$full_tag   = $matches[0];

				if ( false !== strpos( $attributes, 'data-turbo-cookie' ) ) {
					return $full_tag;
				}

				$result = $this->detect_service( $attributes, 'iframe' );
				if ( empty( $result ) ) {
					return $full_tag;
				}

				$src = '';
				if ( preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $attributes, $m ) ) {
					$src = $m[1];
				}
				if ( empty( $src ) ) {
					return $full_tag;
				}

				$new_attrs = preg_replace( '/\bsrc\s*=\s*["\'][^"\']*["\']/i', '', $attributes );

				return $this->build_placeholder( 'embed', $src, $new_attrs, $result );
			},
			$html
		);
	}

	/**
	 * Block object tags — replace with named placeholder.
	 *
	 * @since 1.0.0
	 * @param string $html Page HTML.
	 * @return string Modified HTML.
	 */
	private function block_objects( $html ) {
		return preg_replace_callback(
			'/<object\b([^>]*?)(?:\s*\/>|>(.*?)<\/object>)/is',
			function ( $matches ) {
				$attributes = $matches[1];
				$inner      = $matches[2] ?? '';
				$full_tag   = $matches[0];

				if ( false !== strpos( $attributes, 'data-turbo-cookie' ) ) {
					return $full_tag;
				}

				// Check data= attribute or inner param value.
				$url = '';
				if ( preg_match( '/(?<![-\w])data\s*=\s*["\']([^"\']+)["\']/i', $attributes, $m ) ) {
					$url = $m[1];
				} elseif ( preg_match( '/<param\b[^>]*\bname\s*=\s*["\'](?:movie|src|url)["\'][^>]*\bvalue\s*=\s*["\']([^"\']+)["\']/is', $inner, $m ) ) {
					$url = $m[1];
				}

				if ( empty( $url ) ) {
					return $full_tag;
				}

				$result = $this->detect_service( $url . ' ' . $attributes, 'iframe' );
				if ( empty( $result ) ) {
					return $full_tag;
				}

				$new_attrs = preg_replace( '/(?<![-\w])data\s*=\s*["\'][^"\']*["\']/i', '', $attributes );

				return $this->build_placeholder( 'object', $url, $new_attrs, $result, '', '', $inner );
			},
			$html
		);
	}

	/**
	 * Build a blocked embed placeholder HTML.
	 *
	 * @since 1.0.0
	 * @param string $tag       iframe|embed|object.
	 * @param string $url       Original src/data URL.
	 * @param string $attrs     Remaining tag attributes (src stripped).
	 * @param array  $service   [ category, label ].
	 * @param string $width     Optional width.
	 * @param string $height    Optional height.
	 * @param string $inner     Inner HTML for object tags.
	 * @return string Placeholder HTML.
	 */
	private function build_placeholder( $tag, $url, $attrs, $service, $width = '', $height = '', $inner = '' ) {
		$category = esc_attr( $service['category'] );
		$label    = esc_html( $service['label'] );

		// Wrapper style — use dimensions if available.
		if ( ! empty( $height ) ) {
			$wrapper_style = 'width:100%;height:' . (int) $height . 'px;';
		} else {
			$wrapper_style = 'width:100%;min-height:200px;';
		}

		$url_attr  = ( 'object' === $tag ) ? 'data-turbo-cookie-data' : 'data-turbo-cookie-src';
		$tag_clean = in_array( $tag, array( 'iframe', 'embed', 'object' ), true ) ? $tag : 'iframe';
		$attrs     = trim( (string) $attrs );

		$html  = '<div class="turbo-cookie-placeholder turbo-cookie-placeholder-' . esc_attr( $tag_clean ) . '"';
		$html .= ' data-turbo-cookie-category="' . $category . '"';
		if ( ! empty( $width ) ) {
			$html .= ' data-turbo-cookie-width="' . (int) $width . '"';
		}
		if ( ! empty( $height ) ) {
			$html .= ' data-turbo-cookie-height="' . (int) $height . '"';
		}
		$html .= ' style="' . esc_attr( $wrapper_style ) . '">';

		// Overlay.
		$html .= '<div class="turbo-cookie-placeholder-inner">';
		$html .= '<div class="turbo-cookie-placeholder-icon">&#128274;</div>';
		$html .= '<p class="turbo-cookie-placeholder-text">';
		/* translators: %s: Service name e.g. YouTube */
		$html .= sprintf( esc_html__( 'This content is blocked because it requires %s cookies.', 'turbo-cookie-gdpr' ), '<strong>' . $label . '</strong>' );
		$html .= '</p>';
		$html .= '<button type="button"'
			. ' class="turbo-cookie-placeholder-btn"'
			. ' data-turbo-cookie-action="accept-category"'
			. ' data-turbo-cookie-category="' . $category . '"'
			// translators: %s: service name e.g. "YouTube".
			. ' aria-label="' . esc_attr( sprintf( __( 'Accept %s cookies and load content', 'turbo-cookie-gdpr' ), $service['label'] ) ) . '">';
		$html .= esc_html__( 'Accept &amp; Load', 'turbo-cookie-gdpr' );
		$html .= '</button>';
		$html .= '</div>';

		// Hidden original element (restored by JS on consent).
		$html .= '<' . $tag_clean;
		$html .= ' ' . $url_attr . '="' . esc_url( $url ) . '"';
		$html .= ' data-turbo-cookie-category="' . $category . '"';
		$html .= ' data-turbo-cookie-blocked="true"';
		if ( ! empty( $attrs ) ) {
			$html .= ' ' . $attrs;
		}
		$html .= ' style="display:none;"';

		if ( 'embed' === $tag_clean ) {
			$html .= ' />';
		} else {
			$html .= '>' . $inner . '</' . $tag_clean . '>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Filter enqueued script tags.
	 *
	 * @since 1.0.0
	 * @param string $tag    Script tag HTML.
	 * @param string $handle Script handle.
	 * @param string $src    Script source URL.
	 * @return string Modified tag.
	 */
	public function filter_script_tag( $tag, $handle, $src ) {
		if ( false !== strpos( $handle, 'turbo-cookie' )
			|| false !== strpos( $handle, 'turbo_cookie' ) ) {
			return $tag;
		}

		if ( false !== strpos( $tag, 'data-turbo-cookie-blocked' ) ) {
			return $tag;
		}

		$result = $this->detect_service( $src, 'script' );
		if ( empty( $result ) ) {
			return $tag;
		}

		$tag = preg_replace( '/type\s*=\s*["\'][^"\']*["\']/i', '', $tag );
		$tag = str_replace(
			'<script ',
			'<script type="text/plain"'
				. ' data-turbo-cookie-category="' . esc_attr( $result['category'] ) . '"'
				. ' data-turbo-cookie-service="' . esc_attr( $result['label'] ) . '"'
				. ' data-turbo-cookie-blocked="true" ',
			$tag
		);

		return $tag;
	}

	/**
	 * Detect which service a string belongs to.
	 * Returns [ category, label ] or null.
	 *
	 * @since 1.0.0
	 * @param string $content  String to check (src, attributes, inline content).
	 * @param string $type     'script' or 'iframe'.
	 * @return array|null
	 */
	private function detect_service( $content, $type = 'script' ) {
		$content_lower = strtolower( $content );
		$patterns      = ( 'iframe' === $type ) ? $this->iframe_patterns : $this->script_patterns;

		foreach ( $patterns as $pattern => $data ) {
			if ( false !== strpos( $content_lower, strtolower( $pattern ) ) ) {
				return array(
					'category' => $data[0],
					'label'    => $data[1],
				);
			}
		}

		// For scripts, also check iframe patterns (some are in both).
		if ( 'script' === $type ) {
			foreach ( $this->script_patterns as $pattern => $data ) {
				if ( false !== strpos( $content_lower, strtolower( $pattern ) ) ) {
					return array(
						'category' => $data[0],
						'label'    => $data[1],
					);
				}
			}
		}

		return null;
	}

	/**
	 * Get all patterns (for admin display).
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_patterns() {
		return array(
			'scripts' => $this->script_patterns,
			'iframes' => $this->iframe_patterns,
		);
	}
}
