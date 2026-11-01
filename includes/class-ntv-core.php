<?php
/**
 * Core Plugin Orchestrator & Compatibility Engine
 *
 * @package Nativly
 * @author  Arsalan Jabarzare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class NTV_Core
 *
 * Main singleton orchestrating settings, builders exclusion, theme header hiding,
 * rewrite endpoints, and subcomponent initialization.
 */
class NTV_Core {

	/**
	 * Singleton instance.
	 *
	 * @var NTV_Core|null
	 */
	private static $instance = null;

	/**
	 * In-memory settings cache.
	 *
	 * @var array|null
	 */
	private static $settings_cache = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @return NTV_Core
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
		$this->load_subcomponents();
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Initialize core WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Internationalization & Rewrite Rules.
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );
		add_action( 'init', array( $this, 'register_rewrite_rules' ), 5 );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );

		// Header hiding injection in frontend wp_head.
		add_action( 'wp_head', array( $this, 'render_header_hiding_style' ), 99 );
	}

	/**
	 * Load plugin textdomain for translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'nativly',
			false,
			dirname( plugin_basename( NTV_PATH . 'nativly.php' ) ) . '/languages'
		);
	}

	/**
	 * Register runtime rewrite rules for Manifest and Service Worker.
	 *
	 * @return void
	 */
	public function register_rewrite_rules() {
		if ( class_exists( 'NTV_Activator' ) ) {
			NTV_Activator::register_rewrite_rules();
		}
	}

	/**
	 * Register custom query variables.
	 *
	 * @param array<int,string> $vars Query variables.
	 * @return array<int,string> Filtered query variables.
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'ntv_manifest';
		$vars[] = 'ntv_sw';
		return $vars;
	}

	/**
	 * Retrieve cached plugin settings merged with defaults.
	 *
	 * @param string|null $key     Optional specific setting key.
	 * @param mixed       $default Default fallback value if key does not exist.
	 * @return mixed Full settings array or specific setting value.
	 */
	public static function get_settings( $key = null, $default = null ) {
		if ( null === self::$settings_cache ) {
			$saved = get_option( 'ntv_settings', array() );
			if ( ! is_array( $saved ) ) {
				$saved = array();
			}

			$defaults = class_exists( 'NTV_Activator' ) ? NTV_Activator::get_default_settings() : array();

			// Merge saved settings over defaults so all keys are always defined.
			self::$settings_cache = array_merge( $defaults, $saved );
		}

		if ( null === $key ) {
			return self::$settings_cache;
		}

		return array_key_exists( $key, self::$settings_cache ) ? self::$settings_cache[ $key ] : $default;
	}

	/**
	 * Clear settings cache.
	 *
	 * @return void
	 */
	public static function clear_settings_cache() {
		self::$settings_cache = null;
	}

	/**
	 * Update plugin settings in single option, bust cache, and increment dynamic version.
	 *
	 * @param array $new_settings Settings array to save.
	 * @return bool True on success, false on failure.
	 */
	public static function update_settings( array $new_settings ) {
		$defaults = class_exists( 'NTV_Activator' ) ? NTV_Activator::get_default_settings() : array();
		$merged   = array_merge( $defaults, $new_settings );

		$updated = update_option( 'ntv_settings', $merged, false );

		// Always increment cache version when settings change.
		update_option( 'ntv_cache_version', time(), false );
		self::clear_settings_cache();

		return $updated;
	}

	/**
	 * Check if current request is inside an administrative screen, customizer, or page builder editor/preview.
	 *
	 * Used to protect builders and prevent frontend components from breaking editor DOMs.
	 *
	 * @return bool True if inside editor, preview, or admin dashboard.
	 */
	public static function is_editor_or_preview() {
		// 1. WordPress Admin Dashboard.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return true;
		}

		// 2. WordPress Theme Customizer Preview.
		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return true;
		}

		// 3. Elementor Editor & Preview.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['elementor-preview'] ) ) {
			return true;
		}
		if ( isset( $_GET['action'] ) && 'elementor' === sanitize_text_field( wp_unslash( $_GET['action'] ) ) ) {
			return true;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( class_exists( '\Elementor\Plugin' ) ) {
			if ( isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				return true;
			}
			if ( isset( \Elementor\Plugin::$instance->preview ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
				return true;
			}
		}

		// 4. Gutenberg Block Editor & Site Editor REST Requests.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_REQUEST['context'] ) && 'edit' === sanitize_text_field( wp_unslash( $_REQUEST['context'] ) ) ) {
			return true;
		}

		// 5. Divi Visual Builder.
		if ( isset( $_GET['et_fb'] ) || isset( $_GET['et_pb_preview'] ) ) {
			return true;
		}

		// 6. Bricks Builder.
		if ( isset( $_GET['bricks'] ) && 'run' === sanitize_text_field( wp_unslash( $_GET['bricks'] ) ) ) {
			return true;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
			return true;
		}
		if ( function_exists( 'bricks_is_builder_main' ) && bricks_is_builder_main() ) {
			return true;
		}

		// 7. Oxygen Builder.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['ct_builder'] ) ) {
			return true;
		}

		// 8. Beaver Builder.
		if ( isset( $_GET['fl_builder'] ) ) {
			return true;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		/**
		 * Filter editor or preview detection.
		 *
		 * @param bool $is_editor Whether current context is an editor or preview.
		 */
		return (bool) apply_filters( 'ntv_is_editor_or_preview', false );
	}

	/**
	 * Output dynamic CSS in wp_head for Theme Header Hiding.
	 *
	 * Scoped strictly to the selected media query (default mobile: max-width: 768px)
	 * so desktop headers are never accidentally hidden.
	 *
	 * @return void
	 */
	public function render_header_hiding_style() {
		// Never hide headers inside editors or customizer.
		if ( self::is_editor_or_preview() ) {
			return;
		}

		$settings = self::get_settings();

		// Check master toggle for theme header hiding.
		if ( empty( $settings['hide_theme_header'] ) ) {
			return;
		}

		$preset          = isset( $settings['header_hiding_preset'] ) ? (string) $settings['header_hiding_preset'] : 'none';
		$custom_selector = isset( $settings['custom_header_selector'] ) ? trim( (string) $settings['custom_header_selector'] ) : '';

		// Preset selector mapping.
		$presets = array(
			'kadence'          => 'header#masthead, .site-header, #mobile-header, .kadence-sticky-header',
			'hello_elementor'  => 'header#site-header, .site-header',
			'elementor_pro'    => '.elementor-location-header, header.elementor-element',
			'astra_gp'         => '#masthead, .main-header-bar',
		);

		$selector = '';
		if ( 'custom' === $preset ) {
			$selector = $custom_selector;
		} elseif ( isset( $presets[ $preset ] ) ) {
			$selector = $presets[ $preset ];
		}

		if ( empty( $selector ) || 'none' === $preset ) {
			return;
		}

		// Sanitize CSS selector safely.
		$selector = wp_strip_all_tags( $selector );
		$selector = str_replace( array( '<', '>', '{', '}', ';', "\0", "\r", "\n" ), '', $selector );
		$selector = trim( $selector );

		if ( empty( $selector ) ) {
			return;
		}

		// Device media query targeting: Default is 'mobile' (max-width: 768px).
		$device = isset( $settings['hide_header_device'] ) ? (string) $settings['hide_header_device'] : 'mobile';

		$media_query_start = '';
		$media_query_end   = '';

		switch ( $device ) {
			case 'mobile_tablet':
				$media_query_start = '@media (max-width: 1024px) {';
				$media_query_end   = '}';
				break;
			case 'all':
				$media_query_start = '';
				$media_query_end   = '';
				break;
			case 'mobile':
			default:
				$media_query_start = '@media (max-width: 768px) {';
				$media_query_end   = '}';
				break;
		}

		echo "\n<!-- Nativly: Theme Header Hiding -->\n";
		echo "<style id=\"ntv-theme-header-hiding\">\n";
		if ( ! empty( $media_query_start ) ) {
			echo esc_html( $media_query_start ) . "\n";
			echo "\t" . esc_html( $selector ) . " {\n";
			echo "\t\tdisplay: none !important;\n";
			echo "\t}\n";
			echo esc_html( $media_query_end ) . "\n";
		} else {
			echo esc_html( $selector ) . " {\n";
			echo "\tdisplay: none !important;\n";
			echo "}\n";
		}
		echo "</style>\n";
	}

	/**
	 * Conditionally load and initialize subcomponents if their class files exist.
	 *
	 * Allows seamless integration across development phases.
	 *
	 * @return void
	 */
	private function load_subcomponents() {
		// Frontend Navbar Component (Phase 2).
		if ( file_exists( NTV_PATH . 'includes/class-ntv-navbar.php' ) ) {
			require_once NTV_PATH . 'includes/class-ntv-navbar.php';
			if ( class_exists( 'NTV_Navbar' ) && method_exists( 'NTV_Navbar', 'get_instance' ) ) {
				NTV_Navbar::get_instance();
			}
		}

		// WooCommerce Integration Component (Phase 2).
		if ( class_exists( 'WooCommerce' ) && file_exists( NTV_PATH . 'includes/class-ntv-woocommerce.php' ) ) {
			require_once NTV_PATH . 'includes/class-ntv-woocommerce.php';
			if ( class_exists( 'NTV_WooCommerce' ) && method_exists( 'NTV_WooCommerce', 'get_instance' ) ) {
				NTV_WooCommerce::get_instance();
			}
		}

		// Progressive Web App Component (Phase 3).
		if ( file_exists( NTV_PATH . 'includes/class-ntv-pwa.php' ) ) {
			require_once NTV_PATH . 'includes/class-ntv-pwa.php';
			if ( class_exists( 'NTV_PWA' ) && method_exists( 'NTV_PWA', 'get_instance' ) ) {
				NTV_PWA::get_instance();
			}
		}

		// Diagnostics & Cache Optimization Rules (loaded globally for cache plugin exclusions).
		if ( file_exists( NTV_PATH . 'includes/class-ntv-diagnostics.php' ) ) {
			require_once NTV_PATH . 'includes/class-ntv-diagnostics.php';
			if ( class_exists( 'NTV_Diagnostics' ) && method_exists( 'NTV_Diagnostics', 'get_instance' ) ) {
				NTV_Diagnostics::get_instance();
			}
		}

		// Admin Settings Component (Phase 4).
		if ( is_admin() ) {
			if ( file_exists( NTV_PATH . 'includes/class-ntv-settings.php' ) ) {
				require_once NTV_PATH . 'includes/class-ntv-settings.php';
				if ( class_exists( 'NTV_Settings' ) && method_exists( 'NTV_Settings', 'get_instance' ) ) {
					NTV_Settings::get_instance();
				}
			}

			// Updater Component (Phase 5).
			if ( file_exists( NTV_PATH . 'includes/class-ntv-updater.php' ) ) {
				require_once NTV_PATH . 'includes/class-ntv-updater.php';
				if ( class_exists( 'NTV_Updater' ) && method_exists( 'NTV_Updater', 'get_instance' ) ) {
					NTV_Updater::get_instance();
				}
			}
		}
	}
}
