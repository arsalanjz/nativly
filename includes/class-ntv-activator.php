<?php
/**
 * Nativly Activator & Default Schema Handler
 *
 * @package Nativly
 * @author  Arsalan Jabarzare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class NTV_Activator
 *
 * Handles plugin activation routines, initializes default settings schema with
 * autoload disabled for peak query performance, and registers rewrite endpoints.
 */
class NTV_Activator {

	/**
	 * Default settings cache.
	 *
	 * @var array<string,mixed>|null
	 */
	private static $defaults = null;

	/**
	 * Execute activation tasks.
	 *
	 * - Seeds `ntv_settings` if not already present, ensuring `autoload = 'no'`.
	 * - Seeds `ntv_cache_version` to current Unix timestamp for SW cache busting.
	 * - Registers PWA rewrite rules and performs a single flush.
	 *
	 * @return void
	 */
	public static function activate() {
		// Initialize settings only if absent.
		if ( false === get_option( NTV_OPTION_KEY ) ) {
			add_option( NTV_OPTION_KEY, self::get_default_settings(), '', 'no' );
		}

		// Initialize cache version timestamp if absent.
		if ( false === get_option( 'ntv_cache_version' ) ) {
			add_option( 'ntv_cache_version', time(), '', 'no' );
		}

		// Register PWA rewrite rules and flush once.
		self::register_rewrite_rules();
		flush_rewrite_rules();
	}

	/**
	 * Execute deactivation tasks.
	 *
	 * - Performs a single rewrite rule flush to remove plugin endpoints.
	 * - Does NOT delete settings or options (uninstall.php handles that conditionally).
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Register rewrite rules for PWA endpoints.
	 *
	 * Provides clean URL mapping:
	 *   /ntv-manifest.json -> index.php?ntv_manifest=1
	 *   /ntv-sw.js         -> index.php?ntv_sw=1
	 *
	 * @return void
	 */
	public static function register_rewrite_rules() {
		add_rewrite_rule(
			'^ntv-manifest\.json$',
			'index.php?ntv_manifest=1',
			'top'
		);

		add_rewrite_rule(
			'^ntv-sw\.js$',
			'index.php?ntv_sw=1',
			'top'
		);
	}

	/**
	 * Get the complete, hardened default configuration array.
	 *
	 * Strict production-ready schema with no placeholder arrays or null values.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_default_settings() {
		if ( null !== self::$defaults ) {
			return self::$defaults;
		}

		$site_title = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : 'Nativly';
		$site_desc  = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'description' ) : 'Native-like Progressive Web App';
		$home_url   = function_exists( 'home_url' ) ? home_url( '/' ) : '/';

		self::$defaults = array(
			// Navigation items source: 'custom' or 'wp_menu'.
			'menu_source'               => 'custom',
			'wp_nav_menu_id'            => 0,

			// Default 5 navbar items.
			'menu_items'                => array(
				array(
					'id'         => 'ntv-item-1',
					'title'      => __( 'Home', 'nativly' ),
					'url'        => $home_url,
					'icon_type'  => 'lucide',
					'icon'       => 'home',
					'custom_svg' => '',
					'badge_type' => 'none',
					'badge_text' => '',
					'visibility' => 'all',
					'roles'      => array(),
				),
				array(
					'id'         => 'ntv-item-2',
					'title'      => __( 'Courses', 'nativly' ),
					'url'        => function_exists( 'home_url' ) ? home_url( '/courses/' ) : '/courses/',
					'icon_type'  => 'lucide',
					'icon'       => 'book-open',
					'custom_svg' => '',
					'badge_type' => 'none',
					'badge_text' => '',
					'visibility' => 'all',
					'roles'      => array(),
				),
				array(
					'id'         => 'ntv-item-3',
					'title'      => __( 'Shop', 'nativly' ),
					'url'        => function_exists( 'home_url' ) ? home_url( '/shop/' ) : '/shop/',
					'icon_type'  => 'lucide',
					'icon'       => 'shopping-bag',
					'custom_svg' => '',
					'badge_type' => 'none',
					'badge_text' => '',
					'visibility' => 'all',
					'roles'      => array(),
				),
				array(
					'id'         => 'ntv-item-4',
					'title'      => __( 'Cart', 'nativly' ),
					'url'        => function_exists( 'home_url' ) ? home_url( '/cart/' ) : '/cart/',
					'icon_type'  => 'lucide',
					'icon'       => 'shopping-cart',
					'custom_svg' => '',
					'badge_type' => 'wc_cart_count',
					'badge_text' => '',
					'visibility' => 'all',
					'roles'      => array(),
				),
				array(
					'id'         => 'ntv-item-5',
					'title'      => __( 'My Account', 'nativly' ),
					'url'        => function_exists( 'home_url' ) ? home_url( '/my-account/' ) : '/my-account/',
					'icon_type'  => 'lucide',
					'icon'       => 'user',
					'custom_svg' => '',
					'badge_type' => 'none',
					'badge_text' => '',
					'visibility' => 'all',
					'roles'      => array(),
				),
			),

			// Glass Bubble Popover Submenu ("More" Item).
			'enable_more_menu'          => 0,
			'more_menu_title'           => __( 'More', 'nativly' ),
			'more_menu_icon'            => 'grid',
			'more_menu_items'           => array(),

			// Style & Glassmorphic Appearance.
			'style_preset'              => 'dark_liquid_pill',
			'active_preset'             => 'dark_liquid',
			'active_pill_bg'            => '#0047FF',
			'active_text_color'         => '#FFFFFF',
			'inactive_icon_color'       => 'rgba(255, 255, 255, 0.7)',
			'glass_bg_color'            => 'rgba(18, 18, 24, 0.82)',
			'glass_border_color'        => 'rgba(255, 255, 255, 0.12)',
			'glass_opacity'             => 0.82,
			'glass_blur'                => 20,
			'border_radius'             => 999,

			// Navbar Width Control: 'auto' (capsule), 'full' (calc(100% - 32px)), 'custom'.
			'navbar_width_mode'         => 'auto',
			'navbar_custom_width'       => '90%',

			// Desktop Layout Protection: default 'hidden' leaves desktop 100% untouched.
			'desktop_position'          => 'hidden',
			'mobile_bottom_offset'      => 16,
			'body_bottom_padding'       => 85,

			// Theme Header Hiding & Native App Feel.
			'hide_theme_header'         => 0,
			'header_hiding_preset'      => 'none',
			'custom_header_selector'    => '',
			// CRITICAL: default scoped to mobile (max-width: 768px).
			'hide_header_device'        => 'mobile',
			'enable_safe_area'          => 1,
			'enable_overscroll_none'    => 1,
			'disable_ui_select'         => 0,
			'enable_view_transitions'   => 0,

			// Page Targeting & Access Control.
			'page_targeting_mode'       => 'global',
			'excluded_page_ids'         => array(),
			'specific_page_ids'         => array(),
			'excluded_urls'             => '',
			'exclude_on_cart'           => 0,
			'exclude_on_checkout'       => 0,
			'hide_cart_for_guests'     => 0,
			'user_visibility'           => 'all',
			'allowed_roles'             => array(),

			// Progressive Web App Configuration.
			'enable_pwa'                => 1,
			'app_name'                  => ! empty( $site_title ) ? $site_title : 'Nativly App',
			'app_short_name'            => ! empty( $site_title ) ? $site_title : 'Nativly',
			'app_description'           => ! empty( $site_desc ) ? $site_desc : 'Progressive Web App',
			'theme_color'               => '#0047FF',
			'background_color'          => '#121218',
			'pwa_icon_id'               => 0,
			'pwa_icon_512'              => '',
			'pwa_icon_192'              => '',
			'pwa_icon_180'              => '',
			'offline_page_id'           => 0,

			// Install Prompt Settings.
			'enable_install_prompt'       => 1,
			'install_prompt_delay'        => 3,
			'install_prompt_min_views'    => 2,
			'install_prompt_cooldown_days' => 7,

			// Data Retention on Deletion.
			'delete_data_on_uninstall'  => 0,
		);

		return self::$defaults;
	}
}
