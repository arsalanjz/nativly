<?php
/**
 * Frontend Expanding Pill Glassmorphic Navbar Component
 *
 * @package Nativly
 * @author  Arsalan Jabarzare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class NTV_Navbar
 *
 * Handles frontend rendering, dynamic inline styling, smart active tab detection,
 * script loader optimization bypassing, and visibility rules for the expanding glassmorphic navigation bar.
 */
class NTV_Navbar {

	/**
	 * Singleton instance.
	 *
	 * @var NTV_Navbar|null
	 */
	private static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @return NTV_Navbar
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress actions and filters.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_body_open', array( $this, 'render_navbar' ), 5 );
		add_action( 'wp_footer', array( $this, 'render_navbar' ), 90 );
		add_filter( 'script_loader_tag', array( $this, 'add_no_optimize_attributes' ), 10, 2 );
	}

	/**
	 * Mark Nativly frontend scripts with cache-plugin bypass attributes.
	 *
	 * Prevents delay/defer optimization by WP Rocket, LiteSpeed, Autoptimize, etc.,
	 * ensuring the navbar renders instantly without popping in seconds later.
	 *
	 * @param string $tag    HTML script tag.
	 * @param string $handle Script handle.
	 * @return string Modified script tag.
	 */
	public function add_no_optimize_attributes( $tag, $handle ) {
		if ( in_array( $handle, array( 'ntv-frontend', 'ntv-install-prompt' ), true ) ) {
			if ( false === strpos( $tag, 'data-no-optimize' ) ) {
				$tag = str_replace( '<script ', '<script data-no-optimize="1" data-no-defer="1" data-cfasync="false" ', $tag );
			}
		}
		return $tag;
	}

	/**
	 * Check if navbar should be displayed on current page and for current user.
	 *
	 * @return bool True if navbar is allowed to display.
	 */
	public function should_display() {
		// 1. Strictly bypass in administrative areas, customizer, and page builders.
		if ( NTV_Core::is_editor_or_preview() ) {
			return false;
		}

		$settings = NTV_Core::get_settings();

		// 2. User Authentication & Role Visibility Rules.
		$user_visibility = isset( $settings['user_visibility'] ) ? $settings['user_visibility'] : 'all';
		$is_logged_in    = is_user_logged_in();

		if ( 'logged_in' === $user_visibility && ! $is_logged_in ) {
			return false;
		}

		if ( 'guests' === $user_visibility && $is_logged_in ) {
			return false;
		}

		if ( 'roles' === $user_visibility ) {
			if ( ! $is_logged_in ) {
				return false;
			}
			$allowed_roles = isset( $settings['allowed_roles'] ) && is_array( $settings['allowed_roles'] ) ? $settings['allowed_roles'] : array();
			$current_user  = wp_get_current_user();
			$user_roles    = (array) $current_user->roles;
			$has_role      = (bool) array_intersect( $user_roles, $allowed_roles );
			if ( ! $has_role ) {
				return false;
			}
		}

		// 3. Page Targeting Rules.
		$current_id     = (int) get_queried_object_id();
		$targeting_mode = isset( $settings['page_targeting_mode'] ) ? $settings['page_targeting_mode'] : 'global';

		if ( 'specific' === $targeting_mode ) {
			$specific_ids = isset( $settings['specific_page_ids'] ) && is_array( $settings['specific_page_ids'] ) ? array_map( 'intval', $settings['specific_page_ids'] ) : array();
			if ( ! in_array( $current_id, $specific_ids, true ) ) {
				return false;
			}
		} else {
			// Global mode: check exclusions.
			$excluded_ids = isset( $settings['excluded_page_ids'] ) && is_array( $settings['excluded_page_ids'] ) ? array_map( 'intval', $settings['excluded_page_ids'] ) : array();
			if ( in_array( $current_id, $excluded_ids, true ) ) {
				return false;
			}
		}

		// 4. WooCommerce Cart & Checkout Exclusions.
		if ( function_exists( 'is_cart' ) && ! empty( $settings['exclude_on_cart'] ) && is_cart() ) {
			return false;
		}
		if ( function_exists( 'is_checkout' ) && ! empty( $settings['exclude_on_checkout'] ) && is_checkout() ) {
			return false;
		}

		// 5. Custom URL / Path Exclusions (supports wildcards, e.g. /landing-*, /campaign, https://example.com/promo/).
		if ( ! empty( $settings['excluded_urls'] ) ) {
			$raw_patterns = explode( "\n", str_replace( "\r", '', (string) $settings['excluded_urls'] ) );
			$current_url  = self::get_current_request_url();
			$current_path = (string) wp_parse_url( $current_url, PHP_URL_PATH );
			$current_path = '/' . ltrim( $current_path, '/' );

			foreach ( $raw_patterns as $pattern ) {
				$pattern = trim( $pattern );
				if ( empty( $pattern ) ) {
					continue;
				}

				// If pattern is a full URL.
				if ( 0 === strpos( $pattern, 'http://' ) || 0 === strpos( $pattern, 'https://' ) ) {
					if ( fnmatch( $pattern, $current_url, FNM_CASEFOLD ) || fnmatch( trailingslashit( $pattern ), trailingslashit( $current_url ), FNM_CASEFOLD ) ) {
						return false;
					}
				} else {
					// Relative path pattern.
					$normalized_pattern = '/' . ltrim( $pattern, '/' );
					if ( fnmatch( $normalized_pattern, $current_path, FNM_CASEFOLD ) || fnmatch( trailingslashit( $normalized_pattern ), trailingslashit( $current_path ), FNM_CASEFOLD ) ) {
						return false;
					}
					// Also test wildcard matching on raw pattern.
					if ( fnmatch( $pattern, $current_path, FNM_CASEFOLD ) ) {
						return false;
					}
				}
			}
		}

		/**
		 * Filter whether Nativly navbar should display on frontend.
		 *
		 * @param bool $should_display Whether to show navbar.
		 */
		return (bool) apply_filters( 'ntv_navbar_should_display', true );
	}

	/**
	 * Enqueue frontend styles, scripts, and inject dynamic CSS variables.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->should_display() ) {
			return;
		}

		$cache_version = (string) get_option( 'ntv_cache_version', NTV_VERSION );

		// Enqueue Frontend CSS.
		wp_enqueue_style(
			'ntv-frontend',
			NTV_URL . 'assets/css/ntv-frontend.css',
			array(),
			$cache_version
		);

		// Dynamic CSS Custom Properties.
		$custom_css = $this->generate_dynamic_css();
		if ( ! empty( $custom_css ) ) {
			wp_add_inline_style( 'ntv-frontend', $custom_css );
		}

		// Enqueue Frontend JS.
		wp_enqueue_script(
			'ntv-frontend',
			NTV_URL . 'assets/js/ntv-frontend.js',
			array(),
			$cache_version,
			true
		);

		// Localize script data.
		wp_localize_script(
			'ntv-frontend',
			'ntvFrontendData',
			array(
				'isRTL'          => is_rtl(),
				'hasWooCommerce' => class_exists( 'WooCommerce' ),
				'cartCount'      => class_exists( 'NTV_WooCommerce' ) ? NTV_WooCommerce::get_cart_count() : 0,
			)
		);
	}

	/**
	 * Generate dynamic CSS custom properties based on plugin settings.
	 *
	 * @return string Minified CSS variables string.
	 */
	private function generate_dynamic_css() {
		$settings = NTV_Core::get_settings();

		$active_pill_bg      = ! empty( $settings['active_pill_bg'] ) ? $settings['active_pill_bg'] : '#0047FF';
		$active_text_color   = ! empty( $settings['active_text_color'] ) ? $settings['active_text_color'] : '#FFFFFF';
		$inactive_icon_color = ! empty( $settings['inactive_icon_color'] ) ? $settings['inactive_icon_color'] : 'rgba(255, 255, 255, 0.7)';
		$glass_bg_color      = ! empty( $settings['glass_bg_color'] ) ? $settings['glass_bg_color'] : 'rgba(18, 18, 24, 0.82)';
		$glass_border_color  = ! empty( $settings['glass_border_color'] ) ? $settings['glass_border_color'] : 'rgba(255, 255, 255, 0.12)';
		// Convert percentage settings to CSS pixels with backward compatibility.
		$blur_raw = isset( $settings['glass_blur'] ) ? (int) $settings['glass_blur'] : 50;
		if ( $blur_raw <= 40 && $blur_raw > 0 && isset( $settings['border_radius'] ) && (int) $settings['border_radius'] > 100 ) {
			$blur_percent = (int) round( ( $blur_raw / 40 ) * 100 );
		} else {
			$blur_percent = min( 100, max( 0, $blur_raw ) );
		}
		$glass_blur = (int) round( ( $blur_percent / 100 ) * 40 );

		$radius_raw = isset( $settings['border_radius'] ) ? (int) $settings['border_radius'] : 100;
		if ( $radius_raw >= 100 ) {
			$border_radius = 999;
		} else {
			$border_radius = (int) round( ( min( 100, max( 0, $radius_raw ) ) / 100 ) * 48 );
		}

		$offset_raw = isset( $settings['mobile_bottom_offset'] ) ? (int) $settings['mobile_bottom_offset'] : 25;
		if ( $offset_raw <= 40 && $offset_raw > 0 && isset( $settings['border_radius'] ) && (int) $settings['border_radius'] > 100 ) {
			$offset_percent = (int) round( ( $offset_raw / 64 ) * 100 );
		} else {
			$offset_percent = min( 100, max( 0, $offset_raw ) );
		}
		$bottom_offset = (int) round( ( $offset_percent / 100 ) * 64 );
		$body_bottom_padding = isset( $settings['body_bottom_padding'] ) ? (int) $settings['body_bottom_padding'] : 85;
		$custom_width        = ! empty( $settings['navbar_custom_width'] ) ? $settings['navbar_custom_width'] : '90%';

		$css  = ":root {\n";
		$css .= "\t--ntv-active-bg: " . esc_attr( $active_pill_bg ) . ";\n";
		$css .= "\t--ntv-active-pill-bg: " . esc_attr( $active_pill_bg ) . ";\n";
		$css .= "\t--ntv-active-text: " . esc_attr( $active_text_color ) . ";\n";
		$css .= "\t--ntv-inactive-color: " . esc_attr( $inactive_icon_color ) . ";\n";
		$css .= "\t--ntv-inactive-icon: " . esc_attr( $inactive_icon_color ) . ";\n";
		$css .= "\t--ntv-glass-bg: " . esc_attr( $glass_bg_color ) . ";\n";
		$css .= "\t--ntv-glass-border: " . esc_attr( $glass_border_color ) . ";\n";
		$css .= "\t--ntv-blur: " . esc_attr( (string) $glass_blur ) . "px;\n";
		$css .= "\t--ntv-border-radius: " . esc_attr( (string) $border_radius ) . "px;\n";
		$css .= "\t--ntv-bottom-offset: " . esc_attr( (string) $bottom_offset ) . "px;\n";
		$css .= "\t--ntv-body-padding: " . esc_attr( (string) $body_bottom_padding ) . "px;\n";
		$css .= "\t--ntv-custom-width: " . esc_attr( $custom_width ) . ";\n";
		$css .= "\t--ntv-badge-bg: #EF4444;\n";
		$css .= "\t--ntv-badge-text: #FFFFFF;\n";
		$css .= "}\n";

		// Full Inline Critical CSS (Perfmatters / LiteSpeed RUCSS instant rendering).
		$desktop_position = isset( $settings['desktop_position'] ) ? $settings['desktop_position'] : 'hidden';

		if ( 'hidden' === $desktop_position ) {
			$css .= "@media (max-width: 768px) { body { padding-bottom: var(--ntv-body-padding, 85px) !important; } }\n";
		} else {
			$css .= "body { padding-bottom: var(--ntv-body-padding, 85px) !important; }\n";
		}
		$css .= ".ntv-glass-nav-container { position: fixed !important; bottom: var(--ntv-bottom-offset, 16px) !important; left: 50% !important; transform: translateX(-50%) !important; z-index: 2147483647 !important; display: flex !important; justify-content: center !important; align-items: center !important; opacity: 1 !important; visibility: visible !important; box-sizing: border-box !important; pointer-events: none !important; width: auto; max-width: 100%; }\n";
		$css .= ".ntv-glass-nav-container.ntv-desktop-hidden { display: none !important; }\n";
		$css .= "@media (max-width: 768px) { .ntv-glass-nav-container.ntv-desktop-hidden { display: flex !important; } }\n";

		// Desktop Display & Protection (min-width: 769px)
		if ( 'hidden' === $desktop_position ) {
			$css .= "@media (min-width: 769px) {\n";
			$css .= "\t.ntv-glass-nav-container, #ntv-glass-navbar, #ntv-glass-bubble-popover, .ntv-glass-bubble-popover, .ntv-bubble-popover, #ntv-install-prompt-banner, .ntv-install-prompt-banner, #ntv-install-card, .ntv-glass-install-card, #ntv-ios-guide-modal, #ntv-ios-modal-backdrop, .ntv-ios-backdrop, .ntv-pwa-container, #ntv-pwa-toast-container {\n";
			$css .= "\t\tdisplay: none !important;\n";
			$css .= "\t\tvisibility: hidden !important;\n";
			$css .= "\t\topacity: 0 !important;\n";
			$css .= "\t\tpointer-events: none !important;\n";
			$css .= "\t}\n";
			$css .= "}\n";
		} else {
			$css .= "@media (min-width: 769px) { .ntv-glass-nav-container.ntv-desktop-top { top: var(--ntv-bottom-offset, 16px) !important; bottom: auto !important; } .ntv-glass-nav-container.ntv-desktop-bottom { bottom: var(--ntv-bottom-offset, 16px) !important; top: auto !important; } }\n";
			$css .= "@media (min-width: 769px) { #ntv-install-prompt-banner, .ntv-install-prompt-banner, #ntv-install-card, .ntv-glass-install-card, #ntv-ios-guide-modal, #ntv-ios-modal-backdrop, .ntv-ios-backdrop, .ntv-pwa-container, #ntv-pwa-toast-container { display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; } }\n";
		}
		$css .= ".ntv-glass-nav-container.ntv-width-auto { width: auto; max-width: calc(100vw - 32px); }\n";
		$css .= ".ntv-glass-nav-container.ntv-width-full { width: calc(100% - 24px) !important; max-width: 600px; }\n";
		$css .= ".ntv-glass-nav-container.ntv-width-full .ntv-glass-nav { width: 100% !important; }\n";
		$css .= ".ntv-glass-nav-container.ntv-width-full .ntv-nav-list { width: 100% !important; justify-content: space-around !important; }\n";
		$css .= ".ntv-glass-nav-container.ntv-width-custom { width: var(--ntv-custom-width, 90%) !important; max-width: 600px; }\n";
		$css .= ".ntv-glass-nav-container.ntv-width-custom .ntv-glass-nav { width: 100% !important; }\n";
		$css .= ".ntv-glass-nav-container.ntv-width-custom .ntv-nav-list { width: 100% !important; justify-content: space-around !important; }\n";
		$css .= ".ntv-glass-nav { display: inline-flex !important; align-items: center !important; justify-content: center !important; pointer-events: auto !important; background: var(--ntv-glass-bg, rgba(18, 18, 24, 0.82)) !important; border: 1px solid var(--ntv-glass-border, rgba(255, 255, 255, 0.12)) !important; border-radius: var(--ntv-border-radius, 999px) !important; padding: 6px !important; box-shadow: 0 12px 36px rgba(0, 0, 0, 0.35), 0 2px 8px rgba(0, 0, 0, 0.15) !important; -webkit-backdrop-filter: blur(var(--ntv-blur, 20px)) saturate(180%) !important; backdrop-filter: blur(var(--ntv-blur, 20px)) saturate(180%) !important; box-sizing: border-box !important; }\n";
		$css .= ".ntv-nav-list { display: flex !important; flex-direction: row !important; align-items: center !important; justify-content: space-around !important; list-style: none !important; margin: 0 !important; padding: 0 !important; gap: 4px !important; box-sizing: border-box !important; }\n";
		$css .= ".ntv-nav-item { list-style: none !important; margin: 0 !important; padding: 0 12px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; height: 44px !important; min-width: 44px !important; border-radius: var(--ntv-border-radius, 999px) !important; color: var(--ntv-inactive-color, rgba(255, 255, 255, 0.7)) !important; text-decoration: none !important; font-family: inherit !important; font-size: 13.5px !important; font-weight: 600 !important; white-space: nowrap !important; cursor: pointer !important; background: transparent !important; border: none !important; outline: none !important; box-sizing: border-box !important; position: relative !important; }\n";
		$css .= ".ntv-nav-link { display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 8px !important; text-decoration: none !important; padding: 10px 14px !important; border-radius: 999px !important; white-space: nowrap !important; color: inherit !important; }\n";
		$css .= ".ntv-nav-item:not(.is-active) .ntv-nav-label, .ntv-nav-item:not(.is-active) .ntv-label { display: none !important; max-width: 0 !important; opacity: 0 !important; overflow: hidden !important; }\n";
		$css .= ".ntv-nav-item.is-active { background: var(--ntv-active-bg, #0047FF) !important; color: var(--ntv-active-text, #FFFFFF) !important; padding: 0 18px !important; box-shadow: 0 4px 16px rgba(0, 71, 255, 0.35) !important; }\n";
		$css .= ".ntv-nav-item.is-active .ntv-nav-label, .ntv-nav-item.is-active .ntv-label { display: inline-block !important; font-size: 13px !important; font-weight: 600 !important; color: var(--ntv-active-text, #FFFFFF) !important; opacity: 1 !important; max-width: 140px !important; margin-inline-start: 8px !important; }\n";
		$css .= ".ntv-nav-icon, .ntv-icon-wrap { position: relative !important; display: inline-flex !important; width: 24px !important; height: 24px !important; align-items: center !important; justify-content: center !important; flex-shrink: 0 !important; color: inherit !important; overflow: visible !important; }\n";
		$css .= ".ntv-nav-icon svg, .ntv-icon-wrap svg { width: 22px !important; height: 22px !important; stroke: currentColor !important; fill: none !important; stroke-width: 2.2 !important; display: block !important; }\n";
		$css .= ".ntv-cart-badge, .ntv-badge { position: absolute !important; top: -7px !important; right: -9px !important; left: auto !important; min-width: 16px !important; height: 16px !important; padding: 0 4px !important; border-radius: 999px !important; font-size: 10px !important; font-weight: 700 !important; line-height: 16px !important; text-align: center !important; background: #ef4444 !important; color: #ffffff !important; border: 1.5px solid rgba(18, 18, 24, 0.9) !important; box-sizing: border-box !important; z-index: 5 !important; pointer-events: none !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; }\n";
		$css .= "[dir='rtl'] .ntv-cart-badge, .rtl .ntv-cart-badge { right: auto !important; left: -9px !important; }\n";
		$css .= ".ntv-cart-badge.is-empty, .ntv-badge.is-empty { display: none !important; }\n";
		$css .= "#ntv-top-loader { position: fixed !important; top: 0 !important; left: 0 !important; width: 0%; height: 3px !important; background: linear-gradient(90deg, var(--ntv-active-bg, #0047FF), #60a5fa) !important; box-shadow: 0 0 10px var(--ntv-active-bg, #0047FF) !important; z-index: 2147483647 !important; pointer-events: none !important; opacity: 0; transition: width 0.3s ease, opacity 0.25s ease !important; }\n";
		$css .= "#ntv-top-loader.is-loading { opacity: 1 !important; width: 75% !important; }\n";
		$css .= "#ntv-top-loader.is-done { width: 100% !important; opacity: 0 !important; transition: width 0.15s ease, opacity 0.35s ease 0.15s !important; }\n";
		$css .= ".ntv-glass-bubble-popover, .ntv-bubble-popover { position: fixed !important; bottom: calc(var(--ntv-bottom-offset, 16px) + 74px + env(safe-area-inset-bottom, 0px)) !important; left: 50% !important; transform: translateX(-50%) translateY(12px) scale(0.95) !important; z-index: 2147483647 !important; width: max-content !important; min-width: 200px !important; max-width: calc(100vw - 32px) !important; padding: 12px !important; border-radius: 22px !important; background: var(--ntv-glass-bg, rgba(18, 18, 24, 0.88)) !important; backdrop-filter: blur(var(--ntv-glass-blur, 20px)) !important; -webkit-backdrop-filter: blur(var(--ntv-glass-blur, 20px)) !important; border: 1px solid var(--ntv-glass-border, rgba(255, 255, 255, 0.14)) !important; box-shadow: 0 16px 40px rgba(0, 0, 0, 0.35) !important; opacity: 0 !important; visibility: hidden !important; pointer-events: none !important; transition: opacity 0.25s ease, transform 0.28s cubic-bezier(0.22, 1, 0.36, 1), visibility 0.25s !important; }\n";
		$css .= ".ntv-glass-bubble-popover.is-open, .ntv-glass-bubble-popover.is-visible, .ntv-bubble-popover.is-open, .ntv-bubble-popover.is-visible { display: block !important; opacity: 1 !important; visibility: visible !important; pointer-events: auto !important; transform: translateX(-50%) translateY(0) scale(1) !important; }\n";
		// PWA Install Prompt & iOS Guide Critical Rules
		$css .= ".ntv-ios-guide-modal[aria-hidden='true'], .ntv-ios-guide-modal:not(.is-visible), .ntv-install-prompt-banner[aria-hidden='true'], .ntv-install-prompt-banner:not(.is-visible) { display: none !important; opacity: 0 !important; visibility: hidden !important; pointer-events: none !important; }\n";
		$css .= ".ntv-ios-modal-card, .ntv-install-card { background: rgba(18, 18, 28, 0.94) !important; backdrop-filter: blur(24px) !important; -webkit-backdrop-filter: blur(24px) !important; color: #ffffff !important; border: 1px solid rgba(255, 255, 255, 0.16) !important; border-radius: 24px !important; padding: 24px 20px !important; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5) !important; max-width: 360px !important; width: calc(100vw - 32px) !important; margin: auto !important; text-align: center !important; position: relative !important; }\n";
		$css .= ".ntv-more-trigger:not(.is-active) .ntv-nav-label, .ntv-more-item:not(.is-active) .ntv-nav-label, .ntv-more-trigger:not(.is-active) .ntv-label, .ntv-more-item:not(.is-active) .ntv-label { display: none !important; max-width: 0 !important; opacity: 0 !important; overflow: hidden !important; }\n";
		$css .= ".ntv-more-item.is-open, .ntv-more-item.is-active, #ntv-more-trigger.is-open, #ntv-more-trigger.is-active, .ntv-more-trigger.is-open, .ntv-more-trigger.is-active { background: var(--ntv-active-pill-bg, var(--ntv-active-bg, #0047FF)) !important; color: var(--ntv-active-text, #FFFFFF) !important; box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18) !important; }\n";
		$css .= ".ntv-more-item.is-open svg, .ntv-more-item.is-active svg, #ntv-more-trigger.is-open svg, #ntv-more-trigger.is-active svg, .ntv-more-trigger.is-open svg, .ntv-more-trigger.is-active svg { color: var(--ntv-active-text, #FFFFFF) !important; stroke: var(--ntv-active-text, #FFFFFF) !important; }\n";
		$css .= ".ntv-more-item.is-open:not(.is-active) .ntv-nav-label, #ntv-more-trigger.is-open:not(.is-active) .ntv-nav-label, .ntv-more-trigger.is-open:not(.is-active) .ntv-nav-label { display: none !important; max-width: 0 !important; opacity: 0 !important; overflow: hidden !important; }\n";
		$css .= ".ntv-bubble-item { color: var(--ntv-inactive-icon, var(--ntv-inactive-color, rgba(255, 255, 255, 0.75))) !important; }\n";
		$css .= ".ntv-glass-toast, #ntv-update-toast { background: var(--ntv-glass-bg, rgba(18, 18, 24, 0.92)) !important; border: 1px solid var(--ntv-glass-border, rgba(255, 255, 255, 0.16)) !important; color: var(--ntv-inactive-icon, var(--ntv-inactive-color, #FFFFFF)) !important; }\n";
		$css .= ".ntv-toast-text, .ntv-sw-toast-text { color: var(--ntv-inactive-icon, var(--ntv-inactive-color, #FFFFFF)) !important; }\n";
		$css .= "#ntv-update-btn, .ntv-toast-action { background: var(--ntv-active-pill-bg, var(--ntv-active-bg, #0047FF)) !important; color: var(--ntv-active-text, #FFFFFF) !important; }\n";

		// View transitions feature.
		if ( ! empty( $settings['enable_view_transitions'] ) ) {
			$css .= "@view-transition { navigation: auto; }\n";
		}

		return $css;
	}

	/**
	 * Prepare and filter menu items based on user login state and item-level permissions.
	 *
	 * @return array Processed list of displayable items.
	 */
	public function get_display_items() {
		$settings     = NTV_Core::get_settings();
		$menu_source  = isset( $settings['menu_source'] ) ? $settings['menu_source'] : 'custom';
		$raw_items    = array();
		$is_logged_in = is_user_logged_in();
		$current_user = wp_get_current_user();
		$user_roles   = (array) $current_user->roles;

		if ( 'wp_menu' === $menu_source && ! empty( $settings['wp_nav_menu_id'] ) ) {
			$wp_items = wp_get_nav_menu_items( (int) $settings['wp_nav_menu_id'] );
			if ( is_array( $wp_items ) ) {
				foreach ( $wp_items as $wp_item ) {
					$icon_slug = get_post_meta( $wp_item->ID, '_ntv_icon', true );
					if ( empty( $icon_slug ) ) {
						$icon_slug = 'compass';
					}

					$raw_items[] = array(
						'id'         => 'ntv-wp-item-' . (int) $wp_item->ID,
						'title'      => (string) $wp_item->title,
						'url'        => (string) $wp_item->url,
						'icon_type'  => 'lucide',
						'icon'       => (string) $icon_slug,
						'custom_svg' => (string) get_post_meta( $wp_item->ID, '_ntv_custom_svg', true ),
						'badge_type' => (string) get_post_meta( $wp_item->ID, '_ntv_badge_type', true ),
						'badge_text' => (string) get_post_meta( $wp_item->ID, '_ntv_badge_text', true ),
						'visibility' => 'all',
						'roles'      => array(),
					);
				}
			}
		} else {
			$raw_items = isset( $settings['menu_items'] ) && is_array( $settings['menu_items'] ) ? $settings['menu_items'] : array();
		}

		$hide_cart = ! empty( $settings['hide_cart_for_guests'] ) && ! $is_logged_in;
		$display_items = array();

		foreach ( $raw_items as $item ) {
			$visibility = isset( $item['visibility'] ) ? $item['visibility'] : 'all';

			if ( 'logged_in' === $visibility && ! $is_logged_in ) {
				continue;
			}
			if ( ( 'guests' === $visibility || 'logged_out' === $visibility ) && $is_logged_in ) {
				continue;
			}
			if ( 'roles' === $visibility ) {
				if ( ! $is_logged_in ) {
					continue;
				}
				$allowed_roles = isset( $item['roles'] ) && is_array( $item['roles'] ) ? $item['roles'] : array();
				if ( ! empty( $allowed_roles ) && ! array_intersect( $user_roles, $allowed_roles ) ) {
					continue;
				}
			}

			// Automatic cart tab hiding for guests if option is enabled.
			if ( $hide_cart ) {
				$badge_type = isset( $item['badge_type'] ) ? $item['badge_type'] : '';
				$item_url   = isset( $item['url'] ) ? strtolower( (string) $item['url'] ) : '';
				$is_cart    = ( 'wc_cart_count' === $badge_type || 'cart' === $badge_type );

				if ( ! $is_cart && class_exists( 'WooCommerce' ) && function_exists( 'wc_get_page_permalink' ) ) {
					$cart_permalink = wc_get_page_permalink( 'cart' );
					if ( $cart_permalink ) {
						$cart_path = trailingslashit( strtolower( (string) wp_parse_url( $cart_permalink, PHP_URL_PATH ) ) );
						$cur_path  = trailingslashit( strtolower( (string) wp_parse_url( $item_url, PHP_URL_PATH ) ) );
						if ( ! empty( $cart_path ) && $cart_path === $cur_path ) {
							$is_cart = true;
						}
					}
				}
				if ( ! $is_cart && ( false !== strpos( $item_url, '/cart' ) || false !== strpos( $item_url, 'cart' ) ) ) {
					$is_cart = true;
				}
				if ( $is_cart ) {
					continue;
				}
			}

			$display_items[] = $item;
		}

		return $display_items;
	}

	/**
	 * Retrieve normalized current request URL.
	 *
	 * @return string Current normalized URL.
	 */
	public static function get_current_request_url() {
		if ( isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) && isset( $GLOBALS['wp']->request ) ) {
			return home_url( (string) $GLOBALS['wp']->request );
		}

		$req_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return home_url( $req_uri );
	}

	/**
	 * Smart Active Tab Detection Engine.
	 *
	 * Calculates whether a specific item matches the current page URL, WooCommerce state,
	 * or hierarchical post archive/single relationship.
	 *
	 * @param array $item Menu item data.
	 * @return bool True if active.
	 */
	public function is_item_active( $item ) {
		if ( empty( $item['url'] ) ) {
			return false;
		}

		$item_url     = trailingslashit( strtok( (string) $item['url'], '?' ) );
		$current_url  = trailingslashit( strtok( self::get_current_request_url(), '?' ) );
		$current_path = trailingslashit( (string) wp_parse_url( $current_url, PHP_URL_PATH ) ?: '/' );
		$item_path    = trailingslashit( (string) wp_parse_url( $item_url, PHP_URL_PATH ) ?: '/' );
		$home_path    = trailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ?: '/' );
		$home_url     = trailingslashit( home_url( '/' ) );

		// Home detection: ONLY active when strictly on front page, home, or root path.
		// Never match home as a prefix for subpages (e.g. /blog/ or /shop/).
		$is_home_item = ( $item_url === $home_url || $item_path === $home_path || '/' === $item_path );
		if ( $is_home_item ) {
			return ( is_front_page() || is_home() || $current_path === $home_path );
		}

		// Priority 1: Exact URL or Path Match (for non-home items).
		if ( $current_url === $item_url || ( ! empty( $item_path ) && $item_path === $current_path ) ) {
			return true;
		}

		// Priority 2: WooCommerce Smart Mappings.
		if ( class_exists( 'WooCommerce' ) ) {
			// Shop tab activation for Shop, Product Category, Tag, or Single Product.
			if ( function_exists( 'is_shop' ) && function_exists( 'wc_get_page_permalink' ) ) {
				$shop_permalink = wc_get_page_permalink( 'shop' );
				if ( $shop_permalink ) {
					$shop_path = trailingslashit( wp_parse_url( $shop_permalink, PHP_URL_PATH ) ?: '' );
					if ( ! empty( $shop_path ) && $item_path === $shop_path ) {
						if ( is_shop() || is_product_category() || is_product_tag() || is_singular( 'product' ) ) {
							return true;
						}
					}
				}
			}

			// My Account tab activation for Account endpoints.
			if ( function_exists( 'is_account_page' ) && function_exists( 'wc_get_page_permalink' ) ) {
				$account_permalink = wc_get_page_permalink( 'myaccount' );
				if ( $account_permalink ) {
					$account_path = trailingslashit( wp_parse_url( $account_permalink, PHP_URL_PATH ) ?: '' );
					if ( ! empty( $account_path ) && $item_path === $account_path && is_account_page() ) {
						return true;
					}
				}
			}

			// Cart tab activation.
			if ( function_exists( 'is_cart' ) && function_exists( 'wc_get_page_permalink' ) ) {
				$cart_permalink = wc_get_page_permalink( 'cart' );
				if ( $cart_permalink ) {
					$cart_path = trailingslashit( wp_parse_url( $cart_permalink, PHP_URL_PATH ) ?: '' );
					if ( ! empty( $cart_path ) && $item_path === $cart_path && is_cart() ) {
						return true;
					}
				}
			}
		}

		// Priority 3: Hierarchy & CPT Archive / Single Mappings (strictly non-home items).
		if ( ! empty( $item_path ) && $item_path !== $home_path && '/' !== $item_path && ! empty( $current_path ) ) {
			// Check if current path starts with item path (child page or nested taxonomy/archive).
			if ( 0 === strpos( $current_path, $item_path ) ) {
				return true;
			}

			// Check post ancestors if on a singular page.
			if ( function_exists( 'is_singular' ) && is_singular() ) {
				$post_id   = get_the_ID();
				$ancestors = function_exists( 'get_post_ancestors' ) ? get_post_ancestors( $post_id ) : array();
				if ( ! empty( $ancestors ) ) {
					foreach ( $ancestors as $ancestor_id ) {
						$ancestor_url = trailingslashit( (string) get_permalink( $ancestor_id ) );
						if ( $ancestor_url === $item_url ) {
							return true;
						}
					}
				}
			}
		}

		return false;
	}

	/**
	 * Render the glassmorphic expanding navbar markup in wp_footer.
	 *
	 * @return void
	 */
	public function render_navbar() {
		static $rendered = false;
		if ( $rendered ) {
			return;
		}

		if ( ! $this->should_display() ) {
			return;
		}

		$items = $this->get_display_items();
		if ( empty( $items ) ) {
			return;
		}

		$rendered = true;

		$settings         = NTV_Core::get_settings();
		$desktop_position = isset( $settings['desktop_position'] ) ? $settings['desktop_position'] : 'hidden';
		$width_mode       = isset( $settings['navbar_width_mode'] ) ? $settings['navbar_width_mode'] : 'auto';
		$is_rtl           = is_rtl();
		$dir_attr         = $is_rtl ? 'rtl' : 'ltr';

		// Bubble Menu ("More" hamburger submenu).
		$enable_more_menu = ! empty( $settings['enable_more_menu'] );
		$more_title       = ! empty( $settings['more_menu_title'] ) ? $settings['more_menu_title'] : __( 'More', 'nativly' );
		$more_icon        = ! empty( $settings['more_menu_icon'] ) ? $settings['more_menu_icon'] : 'grid';
		$more_items_raw   = ! empty( $settings['more_menu_items'] ) && is_array( $settings['more_menu_items'] ) ? $settings['more_menu_items'] : array();

		$is_logged_in = is_user_logged_in();
		$hide_cart    = ! empty( $settings['hide_cart_for_guests'] ) && ! $is_logged_in;
		$more_items   = array();
		if ( $enable_more_menu ) {
			foreach ( $more_items_raw as $b_item ) {
				$b_visibility = isset( $b_item['visibility'] ) ? $b_item['visibility'] : 'all';
				if ( 'logged_in' === $b_visibility && ! $is_logged_in ) {
					continue;
				}
				if ( ( 'guests' === $b_visibility || 'logged_out' === $b_visibility ) && $is_logged_in ) {
					continue;
				}
				if ( $hide_cart ) {
					$b_url   = isset( $b_item['url'] ) ? strtolower( (string) $b_item['url'] ) : '';
					$is_cart = ( false !== strpos( $b_url, '/cart' ) || false !== strpos( $b_url, 'cart' ) );
					if ( $is_cart ) {
						continue;
					}
				}
				$more_items[] = $b_item;
			}
		}

		// Priority Check: Does any bubble submenu item match the current page?
		$active_bubble_index = -1;
		if ( $enable_more_menu && ! empty( $more_items ) ) {
			foreach ( $more_items as $b_idx => $b_item ) {
				if ( $this->is_item_active( $b_item ) ) {
					$active_bubble_index = $b_idx;
					break;
				}
			}
		}

		// Prepare active states ensuring STRICTLY ONE tab gets marked as active.
		$active_index   = -1;
		$is_more_active = ( -1 !== $active_bubble_index );

		// If a bubble item is active, "More" is active and NO main item can be active.
		if ( ! $is_more_active ) {
			foreach ( $items as $idx => $item ) {
				if ( $this->is_item_active( $item ) ) {
					$active_index = $idx;
					break;
				}
			}

			// If no tab matched and we are on front page, fallback to first item if it points to home.
			if ( -1 === $active_index && ( is_front_page() || is_home() ) && ! empty( $items[0]['url'] ) ) {
				$first_url = trailingslashit( strtok( (string) $items[0]['url'], '?' ) );
				$home_url  = trailingslashit( home_url( '/' ) );
				if ( $first_url === $home_url ) {
					$active_index = 0;
				}
			}
		}

		$wrapper_classes = array(
			'ntv-glass-nav-container',
			'ntv-desktop-' . sanitize_html_class( $desktop_position ),
			'ntv-width-' . sanitize_html_class( $width_mode ),
		);

		if ( ! empty( $settings['enable_overscroll_none'] ) ) {
			$wrapper_classes[] = 'ntv-overscroll-none';
		}

		if ( ! empty( $settings['disable_ui_select'] ) ) {
			$wrapper_classes[] = 'ntv-no-select';
		}
		$bottom_offset = isset( $settings['mobile_bottom_offset'] ) ? (int) $settings['mobile_bottom_offset'] : 16;
		?>
		<!-- Nativly Critical Inline Layout CSS -->
		<style id="ntv-critical-inline-css" data-no-optimize="1" data-ls-no-optm="1" data-perfmatters-no-rucss="1">
			<?php echo $this->generate_dynamic_css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</style>
		<!-- Nativly SPA Top Loader Bar -->
		<div id="ntv-top-loader" aria-hidden="true"></div>
		<!-- Nativly Expanding Glassmorphic Navbar -->
		<div id="ntv-navbar-root" class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>" dir="<?php echo esc_attr( $dir_attr ); ?>" data-no-optimize="1" data-no-lazy="1">
			<?php if ( $enable_more_menu && ! empty( $more_items ) ) : ?>
				<!-- Liquid Glass Bubble Popover Submenu -->
				<div id="ntv-glass-bubble-popover" class="ntv-bubble-popover ntv-glass-bubble-popover" role="dialog" aria-modal="false" aria-hidden="true">
					<div class="ntv-bubble-glass-card">
						<div class="ntv-bubble-grid">
							<?php
							foreach ( $more_items as $b_idx => $b_item ) :
								$b_is_active = ( $b_idx === $active_bubble_index );
								$b_classes   = array( 'ntv-bubble-item' );
								if ( $b_is_active ) {
									$b_classes[] = 'is-active';
								}
								$b_title = ! empty( $b_item['title'] ) ? $b_item['title'] : '';
								$b_url   = ! empty( $b_item['url'] ) ? $b_item['url'] : '#';
								$b_icon  = ! empty( $b_item['icon'] ) ? $b_item['icon'] : 'compass';
								$b_svg   = ( 'custom' === ( $b_item['icon_type'] ?? '' ) && ! empty( $b_item['custom_svg'] ) )
									? NTV_Icons::get_icon_svg( $b_item['custom_svg'] )
									: NTV_Icons::get_icon_svg( $b_icon );
								if ( empty( $b_svg ) ) {
									$b_svg = NTV_Icons::get_icon_svg( 'compass' );
								}
								?>
								<a href="<?php echo esc_url( $b_url ); ?>" class="<?php echo esc_attr( implode( ' ', $b_classes ) ); ?>" title="<?php echo esc_attr( $b_title ); ?>">
									<span class="ntv-bubble-item-icon" aria-hidden="true">
										<?php echo $b_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</span>
									<span class="ntv-bubble-item-title"><?php echo esc_html( $b_title ); ?></span>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<nav id="ntv-glass-navbar" class="ntv-glass-nav" aria-label="<?php esc_attr_e( 'Main Mobile Navigation', 'nativly' ); ?>" role="navigation" data-no-optimize="1" data-no-lazy="1">
				<div class="ntv-nav-list" role="menubar">
					<?php
					foreach ( $items as $idx => $item ) :
						$is_active    = ( $idx === $active_index );
						$item_classes = array( 'ntv-nav-item' );
						if ( $is_active ) {
							$item_classes[] = 'is-active';
						}

						$item_url   = ! empty( $item['url'] ) ? esc_url( $item['url'] ) : '#';
						$item_title = ! empty( $item['title'] ) ? $item['title'] : '';
						$badge_type = isset( $item['badge_type'] ) ? $item['badge_type'] : 'none';
						$badge_text = isset( $item['badge_text'] ) ? $item['badge_text'] : '';

						// Retrieve SVG or custom icon.
						$icon_markup = '';
						if ( isset( $item['icon_type'] ) && 'custom' === $item['icon_type'] && ! empty( $item['custom_svg'] ) ) {
							$icon_markup = NTV_Icons::get_icon_svg( $item['custom_svg'] );
						} elseif ( ! empty( $item['icon'] ) ) {
							$icon_markup = NTV_Icons::get_icon_svg( $item['icon'] );
						}

						if ( empty( $icon_markup ) ) {
							$icon_markup = NTV_Icons::get_icon_svg( 'home' );
						}
						?>
						<a href="<?php echo esc_url( $item_url ); ?>"
						   class="<?php echo esc_attr( implode( ' ', $item_classes ) ); ?>"
						   role="menuitem"
						   <?php echo $is_active ? 'aria-current="page"' : ''; ?>
						   data-item-id="<?php echo esc_attr( $item['id'] ?? ( 'item-' . $idx ) ); ?>"
						   title="<?php echo esc_attr( $item_title ); ?>">
							<span class="ntv-icon-wrap" aria-hidden="true">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo $icon_markup;
								?>
								<?php if ( 'wc_cart_count' === $badge_type && class_exists( 'NTV_WooCommerce' ) ) : ?>
									<?php
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo NTV_WooCommerce::render_cart_badge_html();
									?>
								<?php elseif ( 'custom' === $badge_type && '' !== trim( $badge_text ) ) : ?>
									<span class="ntv-badge ntv-custom-badge"><?php echo esc_html( $badge_text ); ?></span>
								<?php endif; ?>
							</span>
							<span class="ntv-label"><?php echo esc_html( $item_title ); ?></span>
						</a>
					<?php endforeach; ?>

					<?php if ( $enable_more_menu && ! empty( $more_items ) ) : ?>
						<?php
						$more_btn_classes = array( 'ntv-nav-item', 'ntv-more-trigger', 'ntv-more-item' );
						if ( $is_more_active ) {
							$more_btn_classes[] = 'is-active';
						}
						?>
						<button type="button"
						        class="<?php echo esc_attr( implode( ' ', $more_btn_classes ) ); ?>"
						        id="ntv-more-trigger"
						        aria-haspopup="true"
						        aria-expanded="false"
						        aria-controls="ntv-glass-bubble-popover"
						        title="<?php echo esc_attr( $more_title ); ?>">
							<span class="ntv-icon-wrap" aria-hidden="true">
								<?php echo NTV_Icons::get_icon_svg( $more_icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</span>
							<span class="ntv-label"><?php echo esc_html( $more_title ); ?></span>
						</button>
					<?php endif; ?>
				</div>
			</nav>
		</div>
		<?php
	}
}
