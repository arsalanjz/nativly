<?php
/**
 * Nativly Admin Dashboard, Tabbed UI & Live Phone Mockup
 *
 * @package Nativly
 * @author  Arsalan Jabarzare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class NTV_Settings
 *
 * Handles admin menu registration, settings sanitization, live phone mockup rendering,
 * and AJAX handlers for cache purging and factory reset.
 */
class NTV_Settings {

	/**
	 * Singleton instance.
	 *
	 * @var NTV_Settings|null
	 */
	private static $instance = null;

	/**
	 * Admin page hook suffix.
	 *
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Retrieve singleton instance.
	 *
	 * @return NTV_Settings
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
	 * Initialize admin hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( $this, 'register_settings_and_actions' ) );

		// AJAX endpoints.
		add_action( 'wp_ajax_ntv_purge_cache', array( $this, 'ajax_purge_cache' ) );
		add_action( 'wp_ajax_ntv_reset_settings', array( $this, 'ajax_reset_settings' ) );
	}

	/**
	 * Register top-level admin menu page.
	 *
	 * @return void
	 */
	/**
	 * Parse RGBA or HEX color into [hex, opacity_percent].
	 *
	 * @param string $color Color string.
	 * @param string $default_hex Default fallback hex.
	 * @param int    $default_opacity Default fallback opacity percent.
	 * @return array{hex: string, opacity: int}
	 */
	public static function parse_color_and_opacity( $color, $default_hex = '#FFFFFF', $default_opacity = 100 ) {
		$color = trim( (string) $color );
		if ( preg_match( '/rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*([0-9.]+)\s*\)/i', $color, $matches ) ) {
			$r       = (int) $matches[1];
			$g       = (int) $matches[2];
			$b       = (int) $matches[3];
			$a       = (float) $matches[4];
			$hex     = sprintf( '#%02x%02x%02x', $r, $g, $b );
			$opacity = (int) round( $a * 100 );
			return array(
				'hex'     => strtoupper( $hex ),
				'opacity' => max( 0, min( 100, $opacity ) ),
			);
		}
		if ( preg_match( '/^#([a-f0-9]{3}|[a-f0-9]{6})$/i', $color ) ) {
			return array(
				'hex'     => strtoupper( $color ),
				'opacity' => 100,
			);
		}
		return array(
		'hex'     => $default_hex,
		'opacity' => $default_opacity,
		);
	}

	/**
	 * Detect Global Theme Color Palette from active theme or page builder.
	 *
	 * Checks WP Block Themes (FSE theme.json), Kadence, Astra, and Elementor.
	 * Returns an array of distinct HEX color codes.
	 *
	 * @return array<int, string> List of unique color hex codes.
	 */
	/**
	 * Convert HEX color to HSL array.
	 *
	 * @param string $hex Hex color code.
	 * @return array{h: float, s: float, l: float}
	 */
	public static function hex_to_hsl( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( strlen( $hex ) !== 6 ) {
			return array( 'h' => 220, 's' => 1.0, 'l' => 0.5 );
		}
		$r = hexdec( substr( $hex, 0, 2 ) ) / 255;
		$g = hexdec( substr( $hex, 2, 2 ) ) / 255;
		$b = hexdec( substr( $hex, 4, 2 ) ) / 255;

		$max = max( $r, $g, $b );
		$min = min( $r, $g, $b );
		$d   = $max - $min;
		$l   = ( $max + $min ) / 2;
		$h   = 0;
		$s   = 0;

		if ( $d > 0.0001 ) {
			$s = $l > 0.5 ? $d / ( 2 - $max - $min ) : $d / ( $max + $min );
			if ( $max === $r ) {
				$h = ( $g - $b ) / $d + ( $g < $b ? 6 : 0 );
			} elseif ( $max === $g ) {
				$h = ( $b - $r ) / $d + 2;
			} else {
				$h = ( $r - $g ) / $d + 4;
			}
			$h /= 6;
		}

		return array(
			'h' => $h * 360,
			's' => $s,
			'l' => $l,
		);
	}

	/**
	 * Convert HSL to HEX color string.
	 *
	 * @param float $h Hue 0-360.
	 * @param float $s Saturation 0-1.
	 * @param float $l Lightness 0-1.
	 * @return string Hex color code.
	 */
	public static function hsl_to_hex( $h, $s, $l ) {
		$h = fmod( (float) $h, 360 );
		if ( $h < 0 ) {
			$h += 360;
		}
		$s = max( 0.0, min( 1.0, (float) $s ) );
		$l = max( 0.0, min( 1.0, (float) $l ) );

		$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
		$x = $c * ( 1 - abs( fmod( $h / 60, 2 ) - 1 ) );
		$m = $l - $c / 2;

		$r = 0;
		$g = 0;
		$b = 0;

		if ( $h < 60 ) {
			$r = $c; $g = $x; $b = 0;
		} elseif ( $h < 120 ) {
			$r = $x; $g = $c; $b = 0;
		} elseif ( $h < 180 ) {
			$r = 0; $g = $c; $b = $x;
		} elseif ( $h < 240 ) {
			$r = 0; $g = $x; $b = $c;
		} elseif ( $h < 300 ) {
			$r = $x; $g = 0; $b = $c;
		} else {
			$r = $c; $g = 0; $b = $x;
		}

		$r_int = (int) round( ( $r + $m ) * 255 );
		$g_int = (int) round( ( $g + $m ) * 255 );
		$b_int = (int) round( ( $b + $m ) * 255 );

		return sprintf( '#%02X%02X%02X', $r_int, $g_int, $b_int );
	}

	/**
	 * Retrieve site theme color palette (first 3 colors) plus 2 smart harmonious suggested colors.
	 * Extracts from Kadence Theme (palette1, palette2, palette3), Child themes, FSE, Astra, and Elementor.
	 *
	 * @return array<int, array{color: string, type: string, label: string}> List of 5 color definitions.
	 */
	public static function get_site_palette() {
		$theme_colors = array();

		// 1. Kadence Theme / Child Theme global palette.
		// Kadence stores palette in option 'kadence_global_palette' or theme_mods as JSON string.
		$kadence_raw = get_option( 'kadence_global_palette' );
		if ( empty( $kadence_raw ) ) {
			$kadence_raw = get_theme_mod( 'kadence_global_palette' );
		}
		if ( empty( $kadence_raw ) ) {
			$kadence_raw = get_theme_mod( 'palette' );
		}
		if ( ! empty( $kadence_raw ) ) {
			$decoded = is_string( $kadence_raw ) ? json_decode( $kadence_raw, true ) : $kadence_raw;
			if ( is_array( $decoded ) ) {
				$palette_list = array();
				// Check active key in Kadence JSON structure: {"palette":[...],"active":"palette"}
				if ( ! empty( $decoded['active'] ) && ! empty( $decoded[ $decoded['active'] ] ) && is_array( $decoded[ $decoded['active'] ] ) ) {
					$palette_list = $decoded[ $decoded['active'] ];
				} elseif ( ! empty( $decoded['palette'] ) && is_array( $decoded['palette'] ) ) {
					$palette_list = $decoded['palette'];
				} else {
					$palette_list = $decoded;
				}

				if ( is_array( $palette_list ) && ! empty( $palette_list ) ) {
					// Kadence Palette: Extract the exact first 3 items by index (0, 1, 2 — i.e., palette1, palette2, palette3) AS-IS without filtering!
					$palette_items   = array_values( $palette_list );
					$kadence_first_3 = array();
					$count           = count( $palette_items );
					for ( $i = 0; $i < 3 && $i < $count; $i++ ) {
						$item = $palette_items[ $i ];
						$c    = '';
					if ( is_array( $item ) && ! empty( $item['color'] ) ) {
						$c = sanitize_hex_color( $item['color'] );
					} elseif ( is_string( $item ) ) {
						$c = sanitize_hex_color( $item );
					}
					if ( $c ) {
						$kadence_first_3[] = strtoupper( $c );
					}
				}

				if ( ! empty( $kadence_first_3 ) ) {
					$theme_colors = $kadence_first_3;
				}
			}
			}
		}

		// 2. Active Child Theme & Parent Theme style.css / functions.php CSS custom properties if needed.
		if ( count( $theme_colors ) < 3 ) {
			$css_paths = array();
			if ( function_exists( 'get_stylesheet_directory' ) ) {
				$css_paths[] = get_stylesheet_directory() . '/style.css';
			}
			if ( function_exists( 'get_template_directory' ) && get_template_directory() !== get_stylesheet_directory() ) {
				$css_paths[] = get_template_directory() . '/style.css';
			}

			foreach ( $css_paths as $css_path ) {
				if ( file_exists( $css_path ) && is_readable( $css_path ) ) {
					$css_content = file_get_contents( $css_path, false, null, 0, 40000 );
					if ( ! empty( $css_content ) ) {
						if ( preg_match_all( '/--(?:global-palette\d?|primary|accent|brand|theme-color)[a-zA-Z0-9_-]*\s*:\s*(#[a-fA-F0-9]{3,8})/i', $css_content, $matches ) ) {
							foreach ( $matches[1] as $hex ) {
								$sanitized = sanitize_hex_color( $hex );
								if ( $sanitized ) {
									$upper = strtoupper( $sanitized );
									if ( ! in_array( $upper, array( '#FFFFFF', '#FFF', '#F7FAFC' ), true ) && ! in_array( $upper, $theme_colors, true ) ) {
										$theme_colors[] = $upper;
									}
								}
								if ( count( $theme_colors ) >= 3 ) {
									break 2;
								}
							}
						}
					}
				}
			}
		}

		// 3. Check theme_mods for any stored color hex values if needed.
		if ( count( $theme_colors ) < 3 ) {
			$all_mods = get_theme_mods();
			if ( ! empty( $all_mods ) && is_array( $all_mods ) ) {
				foreach ( $all_mods as $mod_key => $mod_val ) {
					if ( is_string( $mod_val ) && preg_match( '/^#[a-fA-F0-9]{3,8}$/', $mod_val ) ) {
						if ( preg_match( '/(?:palette|primary|accent|brand|link_color)/i', $mod_key ) ) {
							$upper = strtoupper( sanitize_hex_color( $mod_val ) );
							if ( $upper && ! in_array( $upper, array( '#FFFFFF', '#FFF', '#F7FAFC' ), true ) && ! in_array( $upper, $theme_colors, true ) ) {
								$theme_colors[] = $upper;
							}
							if ( count( $theme_colors ) >= 3 ) {
								break;
							}
						}
					}
				}
			}
		}

		// 4. WP Block Theme / Full Site Editing (FSE) theme.json palette if needed.
		if ( count( $theme_colors ) < 3 && function_exists( 'wp_get_global_settings' ) ) {
			$fse_custom = wp_get_global_settings( array( 'color', 'palette', 'custom' ) );
			if ( ! empty( $fse_custom ) && is_array( $fse_custom ) ) {
				foreach ( $fse_custom as $c ) {
					if ( ! empty( $c['color'] ) ) {
						$upper = strtoupper( sanitize_hex_color( $c['color'] ) );
						if ( $upper && ! in_array( $upper, array( '#FFFFFF', '#FFF', '#F7FAFC' ), true ) && ! in_array( $upper, $theme_colors, true ) ) {
							$theme_colors[] = $upper;
						}
						if ( count( $theme_colors ) >= 3 ) {
							break;
						}
					}
				}
			}
		}

		// Fallback curated 3 primary/accent colors if none detected.
		if ( empty( $theme_colors ) ) {
			$theme_colors = array( '#0047FF', '#0096C7', '#4A4A4A' );
		}
		$theme_colors = array_slice( $theme_colors, 0, 3 );

		// 5. Smart Color Suggestion Engine (Colors #4 and #5) using HSL color harmony theory.
		$primary_hex = ! empty( $theme_colors[0] ) ? $theme_colors[0] : '#00A8E8';
		$hsl         = self::hex_to_hsl( $primary_hex );

		// Suggestion 1: Warm Amber / Gold Craft Accent (shift hue by +165 degrees, saturation 82%, lightness 50%)
		$h_warm = fmod( (float) $hsl['h'] + 165.0, 360.0 );
		if ( $h_warm < 0 ) {
			$h_warm += 360.0;
		}
		if ( $h_warm >= 340.0 || $h_warm <= 20.0 ) {
			$h_warm = 42.0; // Rich warm gold / amber
		}
		$color_sug1 = strtoupper( self::hsl_to_hex( $h_warm, 0.82, 0.50 ) );

		// Suggestion 2: Soft Emerald / Mint Accent (shift hue by -38 degrees, saturation 74%, lightness 43%)
		$h_analog = fmod( (float) $hsl['h'] - 38.0 + 360.0, 360.0 );
		if ( $h_analog < 0 ) {
			$h_analog += 360.0;
		}
		$color_sug2 = strtoupper( self::hsl_to_hex( $h_analog, 0.74, 0.43 ) );

		// Build final 5 color objects
		$palette = array();
		foreach ( $theme_colors as $tc ) {
			$palette[] = array(
				'color' => $tc,
				'type'  => 'theme',
				'label' => __( 'رنگ قالب', 'nativly' ),
			);
		}
		$palette[] = array(
			'color' => $color_sug1,
			'type'  => 'suggested',
			'label' => __( 'پیشنهاد هوشمند متناسب با پالت سایت شما', 'nativly' ),
		);
		$palette[] = array(
			'color' => $color_sug2,
			'type'  => 'suggested',
			'label' => __( 'پیشنهاد هوشمند متناسب با پالت سایت شما', 'nativly' ),
		);

		return $palette;
	}

	/**
	 * Render 3 theme swatches + divider + sparkle badge + 2 suggested swatches.
	 *
	 * @param array<int, array{color: string, type: string, label: string}> $site_palette Palette list.
	 * @return void
	 */
	public static function render_palette_swatches( $site_palette ) {
		// 1. Theme Color Swatches (First 3)
		foreach ( $site_palette as $p_item ) {
			$color_hex  = is_array( $p_item ) ? $p_item['color'] : $p_item;
			$color_type = is_array( $p_item ) && isset( $p_item['type'] ) ? $p_item['type'] : 'theme';
			if ( 'theme' !== $color_type ) {
				continue;
			}
			$swatch_title = sprintf( __( 'رنگ قالب: %s', 'nativly' ), $color_hex );
			?>
			<button type="button" class="ntv-inline-swatch" data-color="<?php echo esc_attr( $color_hex ); ?>" style="background-color: <?php echo esc_attr( $color_hex ); ?>;" title="<?php echo esc_attr( $swatch_title ); ?>" aria-label="<?php echo esc_attr( $swatch_title ); ?>"></button>
			<?php
		}
		?>
		<span class="ntv-swatch-divider" style="width:1px;height:20px;background:#dcdcde;margin:0 4px;display:inline-block;flex-shrink:0;"></span>
		<span class="ntv-suggested-label" title="<?php esc_attr_e( 'رنگ‌های پیشنهادی متناسب با پالت سایت شما', 'nativly' ); ?>" style="font-size:14px;line-height:1;display:inline-flex;align-items:center;margin-left:2px;cursor:default;">✨</span>
		<?php
		// 2. Suggested Harmonious Color Swatches (Next 2)
		foreach ( $site_palette as $p_item ) {
			$color_hex  = is_array( $p_item ) ? $p_item['color'] : $p_item;
			$color_type = is_array( $p_item ) && isset( $p_item['type'] ) ? $p_item['type'] : 'theme';
			if ( 'suggested' !== $color_type ) {
				continue;
			}
			$swatch_title = sprintf( __( 'پیشنهاد هوشمند متناسب با پالت سایت شما: %s', 'nativly' ), $color_hex );
			?>
			<button type="button" class="ntv-inline-swatch ntv-swatch-suggested" data-color="<?php echo esc_attr( $color_hex ); ?>" style="background-color: <?php echo esc_attr( $color_hex ); ?>;" title="<?php echo esc_attr( $swatch_title ); ?>" aria-label="<?php echo esc_attr( $swatch_title ); ?>"></button>
			<?php
		}
	}

	public function register_admin_menu() {
		$this->page_hook = add_menu_page(
			__( 'Nativly PWA & Navbar', 'nativly' ),
			__( 'Nativly', 'nativly' ),
			'manage_options',
			'nativly',
			array( $this, 'render_settings_page' ),
			'dashicons-smartphone',
			65
		);
	}

	/**
	 * Enqueue admin CSS and JS strictly on the Nativly settings screen.
	 *
	 * @param string $hook Current admin screen hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( $hook !== $this->page_hook ) {
			return;
		}

		$cache_version = (string) get_option( 'ntv_cache_version', NTV_VERSION );

		// Enqueue WordPress Core Pickers & Media.
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script( 'jquery-ui-sortable' );

		// Nativly Frontend Styles for true glassmorphic live preview in phone mockup.
		wp_enqueue_style(
			'ntv-frontend-css',
			NTV_URL . 'assets/css/ntv-frontend.css',
			array(),
			$cache_version
		);

		// Nativly Admin Styles.
		wp_enqueue_style(
			'ntv-admin-css',
			NTV_URL . 'assets/css/ntv-admin.css',
			array( 'wp-color-picker', 'ntv-frontend-css' ),
			$cache_version
		);

		// Nativly Admin Script.
		wp_enqueue_script(
			'ntv-admin-js',
			NTV_URL . 'assets/js/ntv-admin.js',
			array( 'jquery', 'wp-color-picker', 'jquery-ui-sortable' ),
			$cache_version,
			true
		);

		// Localize script data with Lucide catalog and current settings.
		$icons_catalog = class_exists( 'NTV_Icons' ) ? NTV_Icons::get_all_icons() : array();

		wp_localize_script(
			'ntv-admin-js',
			'ntvAdminData',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'ntv_admin_nonce' ),
				'isRTL'           => is_rtl(),
				'icons'           => $icons_catalog,
				'iconSvgs'        => $icons_catalog,
				'currentSettings' => NTV_Core::get_settings(),
				'i18n'            => array(
					'purgeSuccess' => __( 'کش PWA با موفقیت پاک‌سازی و بروزرسانی شد!', 'nativly' ),
					'resetConfirm' => __( 'آیا از بازنشانی کلیه تنظیمات به حالت اولیه اطمینان دارید؟ این عمل غیرقابل بازگشت است.', 'nativly' ),
					'selectIcon'   => __( 'انتخاب آیکون', 'nativly' ),
					'copied'       => __( 'کپی شد!', 'nativly' ),
					'saveSuccess'  => __( 'تنظیمات با موفقیت ذخیره شدند.', 'nativly' ),
				),
			)
		);
	}

	/**
	 * Register settings, handle Export, Import, and manual form submissions.
	 *
	 * @return void
	 */
	public function register_settings_and_actions() {
		// Handle JSON Export.
		if ( isset( $_GET['action'] ) && 'export_settings' === sanitize_text_field( wp_unslash( $_GET['action'] ) ) ) {
			check_admin_referer( 'ntv_export_action', 'ntv_export_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'اجازه دسترسی ندارید.', 'nativly' ) );
			}

			$settings = NTV_Core::get_settings();
			$json     = wp_json_encode( $settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
			$filename = 'nativly-settings-' . gmdate( 'Y-m-d' ) . '.json';

			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=' . $filename );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate' );
			header( 'Pragma: public' );
			header( 'Content-Length: ' . strlen( $json ) );
			echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}

		// Handle JSON Import.
		if ( isset( $_POST['ntv_import_settings_submit'] ) ) {
			check_admin_referer( 'ntv_import_action', 'ntv_import_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'اجازه دسترسی ندارید.', 'nativly' ) );
			}

			if ( ! empty( $_FILES['ntv_import_file']['tmp_name'] ) ) {
				$file_path     = sanitize_text_field( wp_unslash( $_FILES['ntv_import_file']['tmp_name'] ) );
				$file_content  = file_get_contents( $file_path );
				$imported_data = json_decode( $file_content, true );

				if ( is_array( $imported_data ) ) {
					$sanitized = $this->sanitize_settings( $imported_data );
					NTV_Core::update_settings( $sanitized );
					add_settings_error( 'ntv_settings', 'ntv_import_success', __( 'تنظیمات با موفقیت درون‌ریزی شدند.', 'nativly' ), 'updated' );
				} else {
					add_settings_error( 'ntv_settings', 'ntv_import_error', __( 'فایل انتخابی معتبر نیست.', 'nativly' ), 'error' );
				}
			}
		}

		// Save Settings from Form POST.
		if ( isset( $_POST['ntv_save_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ntv_save_settings_nonce'] ) ), 'ntv_save_settings' ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'اجازه دسترسی ندارید.', 'nativly' ) );
			}

			$raw_input = isset( $_POST['ntv_settings'] ) && is_array( $_POST['ntv_settings'] ) ? wp_unslash( $_POST['ntv_settings'] ) : array();
			$sanitized = $this->sanitize_settings( $raw_input );

			// Check if a new PWA icon was uploaded to auto-generate 192 and 180 icons.
			if ( ! empty( $sanitized['pwa_icon_id'] ) ) {
				$current_settings = NTV_Core::get_settings();
				if ( (int) $current_settings['pwa_icon_id'] !== (int) $sanitized['pwa_icon_id'] ) {
					if ( class_exists( 'NTV_PWA' ) ) {
						NTV_PWA::generate_pwa_icons( (int) $sanitized['pwa_icon_id'] );
						// Reload updated settings after generation.
						$sanitized = NTV_Core::get_settings();
					}
				}
			}

			NTV_Core::update_settings( $sanitized );

			// Purge all caching layers (LiteSpeed, WP Rocket, Super Cache, W3TC, SG, Perfmatters, Object Cache).
			self::purge_all_caches();

			$active_tab = isset( $_POST['ntv_active_tab'] ) ? sanitize_key( wp_unslash( $_POST['ntv_active_tab'] ) ) : 'menu';
			if ( ! in_array( $active_tab, array( 'menu', 'styling', 'theme', 'pwa', 'tools', 'diagnostics' ), true ) ) {
				$active_tab = 'menu';
			}

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'             => 'nativly',
						'tab'              => $active_tab,
						'settings-updated' => 'true',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
	}

	/**
	 * Comprehensive Sanitization of all Settings fields.
	 *
	 * @param array $input Raw input array.
	 * @return array Sanitized array.
	 */
	public function sanitize_settings( array $input ) {
		$defaults = class_exists( 'NTV_Activator' ) ? NTV_Activator::get_default_settings() : array();
		$clean    = $defaults;

		// 1. Menu Builder & Source.
		$clean['menu_source']    = ( isset( $input['menu_source'] ) && 'wp_menu' === $input['menu_source'] ) ? 'wp_menu' : 'custom';
		$clean['wp_nav_menu_id'] = isset( $input['wp_nav_menu_id'] ) ? (int) $input['wp_nav_menu_id'] : 0;

		$clean['menu_items'] = array();
		if ( isset( $input['menu_items'] ) && is_array( $input['menu_items'] ) ) {
			foreach ( $input['menu_items'] as $item ) {
				if ( empty( $item['title'] ) && empty( $item['url'] ) ) {
					continue;
				}

				$custom_svg = '';
				if ( ! empty( $item['custom_svg'] ) && class_exists( 'NTV_Icons' ) ) {
					$custom_svg = NTV_Icons::sanitize_svg( (string) $item['custom_svg'] );
				}

				$clean['menu_items'][] = array(
					'id'         => ! empty( $item['id'] ) ? sanitize_key( $item['id'] ) : uniqid( 'item_' ),
					'title'      => sanitize_text_field( $item['title'] ?? '' ),
					'url'        => esc_url_raw( $item['url'] ?? '' ),
					'icon_type'  => ( isset( $item['icon_type'] ) && 'custom' === $item['icon_type'] ) ? 'custom' : 'lucide',
					'icon'       => sanitize_key( $item['icon'] ?? 'home' ),
					'custom_svg' => $custom_svg,
					'badge_type' => in_array( $item['badge_type'] ?? '', array( 'none', 'wc_cart_count', 'custom' ), true ) ? $item['badge_type'] : 'none',
					'badge_text' => sanitize_text_field( $item['badge_text'] ?? '' ),
					'visibility' => in_array( $item['visibility'] ?? '', array( 'all', 'logged_in', 'guests', 'roles' ), true ) ? $item['visibility'] : 'all',
					'roles'      => isset( $item['roles'] ) && is_array( $item['roles'] ) ? array_map( 'sanitize_key', $item['roles'] ) : array(),
				);
			}
		}

		// Bubble Menu ("More" hamburger submenu).
		$clean['enable_more_menu'] = ! empty( $input['enable_more_menu'] ) ? 1 : 0;
		$clean['more_menu_title']  = ! empty( $input['more_menu_title'] ) ? sanitize_text_field( $input['more_menu_title'] ) : __( 'بیشتر', 'nativly' );
		$clean['more_menu_icon']   = ! empty( $input['more_menu_icon'] ) ? sanitize_key( $input['more_menu_icon'] ) : 'grid';
		$clean['more_menu_items']  = array();

		if ( ! empty( $input['more_menu_items'] ) && is_array( $input['more_menu_items'] ) ) {
			foreach ( $input['more_menu_items'] as $b_item ) {
				if ( empty( $b_item['title'] ) && empty( $b_item['url'] ) ) {
					continue;
				}

				$custom_svg = '';
				if ( ! empty( $b_item['custom_svg'] ) && class_exists( 'NTV_Icons' ) ) {
					$custom_svg = NTV_Icons::sanitize_svg( (string) $b_item['custom_svg'] );
				}

				$clean['more_menu_items'][] = array(
					'id'         => ! empty( $b_item['id'] ) ? sanitize_key( $b_item['id'] ) : uniqid( 'bitem_' ),
					'title'      => sanitize_text_field( $b_item['title'] ?? '' ),
					'url'        => esc_url_raw( $b_item['url'] ?? '' ),
					'icon_type'  => ( isset( $b_item['icon_type'] ) && 'custom' === $b_item['icon_type'] ) ? 'custom' : 'lucide',
					'icon'       => sanitize_key( $b_item['icon'] ?? 'compass' ),
					'custom_svg' => $custom_svg,
					'visibility' => in_array( $b_item['visibility'] ?? '', array( 'all', 'logged_in', 'guests' ), true ) ? $b_item['visibility'] : 'all',
					'roles'      => isset( $b_item['roles'] ) && is_array( $b_item['roles'] ) ? array_map( 'sanitize_key', $b_item['roles'] ) : array(),
				);
			}
		}

		// 2. Glass Styling & Presets.
		$clean['active_preset']       = sanitize_key( $input['active_preset'] ?? 'dark_liquid' );
		$clean['active_pill_bg']      = sanitize_hex_color( $input['active_pill_bg'] ?? '#0047FF' ) ?: '#0047FF';
		$clean['active_text_color']   = sanitize_hex_color( $input['active_text_color'] ?? '#FFFFFF' ) ?: '#FFFFFF';
		$clean['inactive_icon_color'] = sanitize_text_field( $input['inactive_icon_color'] ?? 'rgba(255, 255, 255, 0.7)' );
		$clean['glass_bg_color']      = sanitize_text_field( $input['glass_bg_color'] ?? 'rgba(18, 18, 24, 0.82)' );
		$clean['glass_border_color']  = sanitize_text_field( $input['glass_border_color'] ?? 'rgba(255, 255, 255, 0.12)' );
		$clean['glass_blur']          = isset( $input['glass_blur'] ) ? min( 100, max( 0, absint( $input['glass_blur'] ) ) ) : 50;
		$clean['glass_opacity']       = isset( $input['glass_opacity'] ) ? floatval( $input['glass_opacity'] ) : 0.82;
		$clean['border_radius']       = isset( $input['border_radius'] ) ? min( 100, max( 0, absint( $input['border_radius'] ) ) ) : 100;

		// Navbar Width Mode.
		$clean['navbar_width_mode']   = in_array( $input['navbar_width_mode'] ?? '', array( 'auto', 'full', 'custom' ), true ) ? $input['navbar_width_mode'] : 'auto';
		$clean['navbar_custom_width'] = ! empty( $input['navbar_custom_width'] ) ? sanitize_text_field( $input['navbar_custom_width'] ) : '90%';

		$clean['desktop_position']    = in_array( $input['desktop_position'] ?? '', array( 'hidden', 'top', 'bottom' ), true ) ? $input['desktop_position'] : 'hidden';
		$clean['mobile_bottom_offset'] = isset( $input['mobile_bottom_offset'] ) ? min( 100, max( 0, absint( $input['mobile_bottom_offset'] ) ) ) : 25;
		$clean['body_bottom_padding'] = isset( $input['body_bottom_padding'] ) ? absint( $input['body_bottom_padding'] ) : 85;

		// 3. Theme & Native App Feel.
		$clean['hide_theme_header']      = ! empty( $input['hide_theme_header'] ) ? 1 : 0;
		$clean['header_hiding_preset']   = sanitize_key( $input['header_hiding_preset'] ?? 'kadence' );
		$clean['custom_header_selector'] = sanitize_text_field( $input['custom_header_selector'] ?? '' );
		$clean['hide_header_device']     = in_array( $input['hide_header_device'] ?? '', array( 'mobile', 'mobile_tablet', 'all' ), true ) ? $input['hide_header_device'] : 'mobile';

		$clean['enable_safe_area']         = ! empty( $input['enable_safe_area'] ) ? 1 : 0;
		$clean['enable_overscroll_none']   = ! empty( $input['enable_overscroll_none'] ) ? 1 : 0;
		$clean['disable_ui_select']        = ! empty( $input['disable_ui_select'] ) ? 1 : 0;
		$clean['enable_view_transitions']  = ! empty( $input['enable_view_transitions'] ) ? 1 : 0;

		$clean['page_targeting_mode']  = ( isset( $input['page_targeting_mode'] ) && 'specific' === $input['page_targeting_mode'] ) ? 'specific' : 'global';
		$clean['specific_page_ids']    = isset( $input['specific_page_ids'] ) && is_array( $input['specific_page_ids'] ) ? array_map( 'absint', $input['specific_page_ids'] ) : array();
		$clean['excluded_page_ids']    = isset( $input['excluded_page_ids'] ) && is_array( $input['excluded_page_ids'] ) ? array_map( 'absint', $input['excluded_page_ids'] ) : array();
		$clean['excluded_urls']        = ! empty( $input['excluded_urls'] ) ? sanitize_textarea_field( $input['excluded_urls'] ) : '';
		$clean['exclude_on_cart']      = ! empty( $input['exclude_on_cart'] ) ? 1 : 0;
		$clean['exclude_on_checkout']  = ! empty( $input['exclude_on_checkout'] ) ? 1 : 0;
		$clean['hide_cart_for_guests'] = ! empty( $input['hide_cart_for_guests'] ) ? 1 : 0;
		$clean['user_visibility']      = in_array( $input['user_visibility'] ?? '', array( 'all', 'logged_in', 'guests', 'roles' ), true ) ? $input['user_visibility'] : 'all';
		$clean['allowed_roles']        = isset( $input['allowed_roles'] ) && is_array( $input['allowed_roles'] ) ? array_map( 'sanitize_key', $input['allowed_roles'] ) : array();

		// 4. PWA & Install Prompt.
		$clean['enable_pwa']                  = ! empty( $input['enable_pwa'] ) ? 1 : 0;
		$clean['app_name']                    = sanitize_text_field( $input['app_name'] ?? get_bloginfo( 'name' ) );
		$clean['app_short_name']              = sanitize_text_field( $input['app_short_name'] ?? get_bloginfo( 'name' ) );
		$clean['app_description']             = sanitize_textarea_field( $input['app_description'] ?? get_bloginfo( 'description' ) );
		$clean['theme_color']                 = sanitize_hex_color( $input['theme_color'] ?? '#0047FF' ) ?: '#0047FF';
		$clean['background_color']            = sanitize_hex_color( $input['background_color'] ?? '#121218' ) ?: '#121218';
		$clean['pwa_icon_id']                 = isset( $input['pwa_icon_id'] ) ? absint( $input['pwa_icon_id'] ) : 0;
		$clean['pwa_icon_512']                = esc_url_raw( $input['pwa_icon_512'] ?? '' );
		$clean['pwa_icon_192']                = esc_url_raw( $input['pwa_icon_192'] ?? '' );
		$clean['pwa_icon_180']                = esc_url_raw( $input['pwa_icon_180'] ?? '' );
		$clean['offline_page_id']             = isset( $input['offline_page_id'] ) ? absint( $input['offline_page_id'] ) : 0;
		$clean['enable_install_prompt']       = ! empty( $input['enable_install_prompt'] ) ? 1 : 0;
		$clean['install_prompt_delay']        = isset( $input['install_prompt_delay'] ) ? absint( $input['install_prompt_delay'] ) : 3;
		$clean['install_prompt_min_views']    = isset( $input['install_prompt_min_views'] ) ? absint( $input['install_prompt_min_views'] ) : 2;
		$clean['install_prompt_cooldown_days'] = isset( $input['install_prompt_cooldown_days'] ) ? absint( $input['install_prompt_cooldown_days'] ) : 7;

		// 5. General & Uninstall.
		$clean['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] ) ? 1 : 0;

		return $clean;
	}

	/**
	 * AJAX Handler: Purge PWA Cache and bust client Service Workers.
	 *
	 * @return void
	 */
	/**
	 * Purge all major WordPress caching plugins, optimizer caches, and object caches.
	 *
	 * Supports LiteSpeed, WP Rocket, WP Super Cache, W3 Total Cache,
	 * WP Fastest Cache, SiteGround Optimizer, Perfmatters, and WordPress Object Cache.
	 *
	 * @return void
	 */
	public static function purge_all_caches() {
		// 1. LiteSpeed Cache
		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
		}

		// 2. WP Rocket
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		// 3. WP Super Cache
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		// 4. W3 Total Cache
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		// 5. WP Fastest Cache
		if ( isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
			$GLOBALS['wp_fastest_cache']->deleteCache( true );
		}

		// 6. SiteGround Optimizer
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}

		// 7. Perfmatters Used CSS / Cache
		if ( class_exists( '\Perfmatters\CSS' ) && method_exists( '\Perfmatters\CSS', 'clear_used_css' ) ) {
			\Perfmatters\CSS::clear_used_css();
		}

		// 8. Core WordPress Object Cache
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	public function ajax_purge_cache() {
		check_ajax_referer( 'ntv_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'اجازه دسترسی ندارید.', 'nativly' ) ) );
		}

		// Update cache version to timestamp.
		$new_version = time();
		update_option( 'ntv_cache_version', $new_version, false );

		// Purge all caching layers.
		self::purge_all_caches();

		wp_send_json_success(
			array(
				'message' => __( 'کش با موفقیت پاک‌سازی شد.', 'nativly' ),
				'version' => $new_version,
			)
		);
	}

	/**
	 * AJAX Handler: Reset all settings to defaults.
	 *
	 * @return void
	 */
	public function ajax_reset_settings() {
		check_ajax_referer( 'ntv_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'اجازه دسترسی ندارید.', 'nativly' ) ) );
		}

		$defaults = class_exists( 'NTV_Activator' ) ? NTV_Activator::get_default_settings() : array();
		NTV_Core::update_settings( $defaults );

		wp_send_json_success(
			array(
				'message' => __( 'تمامی تنظیمات با موفقیت به حالت پیش‌فرض بازگردانی شدند.', 'nativly' ),
			)
		);
	}

	/**
	 * Render Nativly Admin Settings Page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		$settings   = NTV_Core::get_settings();
		$is_rtl     = is_rtl();
		$dir        = $is_rtl ? 'rtl' : 'ltr';
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'menu';
		if ( ! in_array( $active_tab, array( 'menu', 'styling', 'theme', 'pwa', 'tools', 'diagnostics' ), true ) ) {
			$active_tab = 'menu';
		}

		if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) {
			add_settings_error( 'ntv_settings', 'ntv_settings_saved', __( 'تنظیمات Nativly با موفقیت ذخیره شدند.', 'nativly' ), 'updated' );
		}
		?>
		<div class="wrap ntv-admin-wrap" dir="<?php echo esc_attr( $dir ); ?>">
			<!-- WP Core Header Anchor to ensure admin notices stay above header -->
			<hr class="wp-header-end">

			<!-- Header -->
			<div class="ntv-admin-header">
				<div class="ntv-admin-brand">
					<div class="ntv-brand-icon">
						<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
							<line x1="12" y1="18" x2="12.01" y2="18"></line>
						</svg>
					</div>
					<div>
						<h1 class="ntv-admin-title">Nativly</h1>
						<p class="ntv-admin-tagline"><?php esc_html_e( 'تبدیل وردپرس به اپلیکیشن بومی با نوبار گسترش‌پذیر شیشه‌ای و PWA', 'nativly' ); ?> <span class="ntv-version-tag">v<?php echo esc_html( NTV_VERSION ); ?></span></p>
					</div>
				</div>
				<div class="ntv-header-actions">
					<button type="button" id="ntv-purge-cache-btn" class="button button-secondary ntv-btn-action">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'پاک‌سازی کش', 'nativly' ); ?>
					</button>
				</div>
			</div>

			<!-- Notices -->
			<?php settings_errors( 'ntv_settings' ); ?>
			<div id="ntv-ajax-notice" class="notice" style="display:none;"><p></p></div>

			<!-- Main Two-Column Layout -->
			<div class="ntv-layout-container">
				<!-- Settings Column -->
				<div class="ntv-settings-col">
					<!-- Tab Navigation -->
					<nav class="ntv-tabs-nav" role="tablist">
						<button type="button" class="ntv-tab-btn <?php echo 'menu' === $active_tab ? 'is-active' : ''; ?>" data-tab="menu"><?php esc_html_e( 'آیتم‌های نوبار', 'nativly' ); ?></button>
						<button type="button" class="ntv-tab-btn <?php echo 'styling' === $active_tab ? 'is-active' : ''; ?>" data-tab="styling"><?php esc_html_e( 'طراحی شیشه‌ای و استایل', 'nativly' ); ?></button>
						<button type="button" class="ntv-tab-btn <?php echo 'theme' === $active_tab ? 'is-active' : ''; ?>" data-tab="theme"><?php esc_html_e( 'قالب و حس اپلیکیشن', 'nativly' ); ?></button>
						<button type="button" class="ntv-tab-btn <?php echo 'pwa' === $active_tab ? 'is-active' : ''; ?>" data-tab="pwa"><?php esc_html_e( 'اپلیکیشن و PWA', 'nativly' ); ?></button>
						<button type="button" class="ntv-tab-btn <?php echo 'tools' === $active_tab ? 'is-active' : ''; ?>" data-tab="tools"><?php esc_html_e( 'ابزارها و کش', 'nativly' ); ?></button>
						<button type="button" class="ntv-tab-btn <?php echo 'diagnostics' === $active_tab ? 'is-active' : ''; ?>" data-tab="diagnostics"><?php esc_html_e( 'وضعیت و عیب‌یابی', 'nativly' ); ?></button>
					</nav>

					<!-- Settings Form (Tabs 1-4) -->
					<form id="ntv-settings-form" method="post" action="">
						<?php wp_nonce_field( 'ntv_save_settings', 'ntv_save_settings_nonce' ); ?>
						<input type="hidden" name="ntv_active_tab" id="ntv-active-tab-input" value="<?php echo esc_attr( $active_tab ); ?>">

						<!-- Tab 1: Menu Items -->
						<div id="ntv-tab-menu" class="ntv-tab-content <?php echo 'menu' === $active_tab ? 'is-active' : ''; ?>">
							<div class="ntv-card">
								<h3 class="ntv-card-title"><?php esc_html_e( 'منبع آیتم‌های نوبار', 'nativly' ); ?></h3>
								<div class="ntv-radio-group">
									<label>
										<input type="radio" name="ntv_settings[menu_source]" value="custom" <?php checked( $settings['menu_source'], 'custom' ); ?>>
										<strong><?php esc_html_e( 'منوساز اختصاصی Nativly (پیشنهادی)', 'nativly' ); ?></strong>
										<span class="ntv-hint"><?php esc_html_e( 'قابلیت تغییر آیکون، نشان (Badge)، و تعیین دسترسی اختصاصی برای هر آیتم.', 'nativly' ); ?></span>
									</label>
									<label>
										<input type="radio" name="ntv_settings[menu_source]" value="wp_menu" <?php checked( $settings['menu_source'], 'wp_menu' ); ?>>
										<strong><?php esc_html_e( 'فهرست پیش‌فرض وردپرس', 'nativly' ); ?></strong>
										<span class="ntv-hint"><?php esc_html_e( 'استفاده از فهرست‌های استاندارد وردپرس (مناسب برای سازگاری خودکار با WPML و Polylang).', 'nativly' ); ?></span>
									</label>
								</div>

								<div id="ntv-wp-menu-select-wrap" class="ntv-field-group" style="<?php echo 'wp_menu' === $settings['menu_source'] ? '' : 'display:none;'; ?>">
									<label for="ntv-wp-menu-id"><?php esc_html_e( 'انتخاب فهرست وردپرس:', 'nativly' ); ?></label>
									<select name="ntv_settings[wp_nav_menu_id]" id="ntv-wp-menu-id" class="regular-text">
										<option value="0"><?php esc_html_e( '— انتخاب فهرست —', 'nativly' ); ?></option>
										<?php
										$menus = wp_get_nav_menus();
										foreach ( $menus as $menu ) {
											echo '<option value="' . esc_attr( (string) $menu->term_id ) . '" ' . selected( $settings['wp_nav_menu_id'], $menu->term_id, false ) . '>' . esc_html( $menu->name ) . '</option>';
										}
										?>
									</select>
								</div>
							</div>

							<!-- Custom Menu Repeater -->
							<div id="ntv-custom-repeater-wrap" class="ntv-card ntv-mt-4" style="<?php echo 'custom' === $settings['menu_source'] ? '' : 'display:none;'; ?>">
								<div class="ntv-card-header-flex">
									<div>
										<h3 class="ntv-card-title"><?php esc_html_e( 'آیتم‌های نوار ناوبری', 'nativly' ); ?></h3>
										<p class="ntv-card-subtitle"><?php esc_html_e( 'با درگ اند دراپ ترتیب آیتم‌ها را تغییر دهید. تب فعال به طور خودکار باز می‌شود.', 'nativly' ); ?></p>
									</div>
									<button type="button" id="ntv-add-menu-item-btn" class="button button-secondary">
										<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'افزودن آیتم جدید', 'nativly' ); ?>
									</button>
								</div>

								<div id="ntv-menu-items-list" class="ntv-sortable-list">
									<?php
									$items = ! empty( $settings['menu_items'] ) && is_array( $settings['menu_items'] ) ? $settings['menu_items'] : array();
									foreach ( $items as $idx => $item ) :
										$this->render_menu_item_row( $idx, $item );
									endforeach;
									?>
								</div>
							</div>

							<!-- Glass Bubble Popover Submenu ("More" Hamburger Item) -->
							<div class="ntv-card ntv-mt-4">
								<div class="ntv-card-header-flex">
									<div>
										<h3 class="ntv-card-title"><?php esc_html_e( 'منوی حبابی بیشتر (Glass Bubble Submenu)', 'nativly' ); ?></h3>
										<p class="ntv-card-subtitle"><?php esc_html_e( 'نمایش لینک‌های فرعی (مانند درباره ما، تماس با ما، مقالات و...) در یک حباب شیشه‌ای شناور در بالای نوبار هنگام کلیک روی دکمه بیشتر.', 'nativly' ); ?></p>
									</div>
									<label class="ntv-switch-label">
										<input type="checkbox" name="ntv_settings[enable_more_menu]" id="ntv-enable-more-menu" value="1" <?php checked( ! empty( $settings['enable_more_menu'] ) ); ?>>
										<span><?php esc_html_e( 'فعال‌سازی منوی حبابی', 'nativly' ); ?></span>
									</label>
								</div>

								<div id="ntv-more-menu-settings-wrap" style="<?php echo ! empty( $settings['enable_more_menu'] ) ? '' : 'display:none;'; ?>">
									<div class="ntv-grid-2">
										<div class="ntv-field-group">
											<label for="ntv-more-menu-title"><?php esc_html_e( 'عنوان دکمه بازکننده:', 'nativly' ); ?></label>
											<input type="text" name="ntv_settings[more_menu_title]" id="ntv-more-menu-title" value="<?php echo esc_attr( $settings['more_menu_title'] ?? __( 'بیشتر', 'nativly' ) ); ?>" class="regular-text">
										</div>
										<div class="ntv-field-group">
											<label><?php esc_html_e( 'آیکون دکمه بازکننده:', 'nativly' ); ?></label>
											<div style="display:flex; align-items:center; gap:10px;">
												<div class="ntv-row-preview-icon">
													<button type="button" class="ntv-row-icon-trigger" id="ntv-more-menu-icon-trigger" title="<?php esc_attr_e( 'تغییر آیکون', 'nativly' ); ?>">
														<span class="ntv-row-current-icon" id="ntv-more-menu-icon-preview">
															<?php echo NTV_Icons::get_icon_svg( $settings['more_menu_icon'] ?? 'grid' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
														</span>
													</button>
												</div>
												<input type="hidden" name="ntv_settings[more_menu_icon]" id="ntv-more-menu-icon-input" value="<?php echo esc_attr( $settings['more_menu_icon'] ?? 'grid' ); ?>">
												<span class="ntv-hint"><?php esc_html_e( 'کلیک کنید تا آیکون تغییر کند (پیش‌فرض: grid یا menu)', 'nativly' ); ?></span>
											</div>
										</div>
									</div>

									<div class="ntv-mt-3">
										<div class="ntv-card-header-flex">
											<div>
												<h4 style="margin:0 0 4px; font-size:14px; font-weight:700; color:#0F172A;"><?php esc_html_e( 'آیتم‌های منوی حبابی', 'nativly' ); ?></h4>
												<p class="ntv-hint"><?php esc_html_e( 'آیتم‌ها و لینک‌هایی که داخل حباب شیشه‌ای نمایش داده خواهند شد.', 'nativly' ); ?></p>
											</div>
											<button type="button" id="ntv-add-bubble-item-btn" class="button button-secondary">
												<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'افزودن آیتم حبابی', 'nativly' ); ?>
											</button>
										</div>

										<div id="ntv-bubble-menu-items-list" class="ntv-sortable-list">
											<?php
											$bubble_items = ! empty( $settings['more_menu_items'] ) && is_array( $settings['more_menu_items'] ) ? $settings['more_menu_items'] : array();
											foreach ( $bubble_items as $b_idx => $b_item ) :
												$this->render_bubble_menu_item_row( $b_idx, $b_item );
											endforeach;
											?>
										</div>
									</div>
								</div>
							</div>
						</div>

						<!-- Tab 2: Glass Styling & Presets -->
						<div id="ntv-tab-styling" class="ntv-tab-content <?php echo 'styling' === $active_tab ? 'is-active' : ''; ?>">
							<div class="ntv-card">
								<h3 class="ntv-card-title"><?php esc_html_e( 'پالت‌ها و پریست‌های آماده', 'nativly' ); ?></h3>
								<p class="ntv-card-subtitle"><?php esc_html_e( 'یکی از پریست‌های استاندارد را انتخاب کنید یا رنگ‌ها را مطابق سلیقه خود تغییر دهید:', 'nativly' ); ?></p>
								<div class="ntv-presets-grid">
									<button type="button" class="ntv-preset-card" data-preset="dark_liquid">
										<span class="ntv-preset-color" style="background:#0047FF;"></span>
										<strong>Dark Liquid Pill</strong>
										<small><?php esc_html_e( 'شیشه تیره + کپسول آبی نئون', 'nativly' ); ?></small>
									</button>
									<button type="button" class="ntv-preset-card" data-preset="light_glass">
										<span class="ntv-preset-color" style="background:#2563EB;"></span>
										<strong>Frosted White</strong>
										<small><?php esc_html_e( 'شیشه مات روشن + متن تیره', 'nativly' ); ?></small>
									</button>
									<button type="button" class="ntv-preset-card" data-preset="cyber_emerald">
										<span class="ntv-preset-color" style="background:#10B981;"></span>
										<strong>Cyber Emerald</strong>
										<small><?php esc_html_e( 'شیشه تیره + کپسول زمردی', 'nativly' ); ?></small>
									</button>
									<button type="button" class="ntv-preset-card" data-preset="sunset_violet">
										<span class="ntv-preset-color" style="background:#8B5CF6;"></span>
										<strong>Sunset Violet</strong>
										<small><?php esc_html_e( 'شیشه بنفش بلور + کپسول یاسی', 'nativly' ); ?></small>
									</button>
								</div>
								<input type="hidden" name="ntv_settings[active_preset]" id="ntv-active-preset-input" value="<?php echo esc_attr( $settings['active_preset'] ); ?>">
							</div>

							<!-- Color Pickers Card (Single Clean Horizontal Rows) -->
							<div class="ntv-card ntv-mt-4">
								<h3 class="ntv-card-title"><?php esc_html_e( 'تنظیمات رنگ و شفافیت کپسول شیشه‌ای', 'nativly' ); ?></h3>
								<p class="ntv-card-subtitle"><?php esc_html_e( 'تنظیم رنگ‌های اختصاصی برای کپسول فعال، آیکون‌ها و شیشه زمینه. با کلیک روی رنگ‌های سراسری قالب (سمت چپ هر ردیف) کد رنگ فوراً اعمال می‌شود:', 'nativly' ); ?></p>

								<?php
								$site_palette    = self::get_site_palette();
								$parsed_inactive = self::parse_color_and_opacity( $settings['inactive_icon_color'], '#FFFFFF', 70 );
								$parsed_bg       = self::parse_color_and_opacity( $settings['glass_bg_color'], '#121218', 82 );
								$parsed_border   = self::parse_color_and_opacity( $settings['glass_border_color'], '#FFFFFF', 12 );
								?>

								<div class="ntv-color-rows-wrap">
									<!-- Row 1: Active Pill Background -->
									<div class="ntv-color-row-inline" data-target="ntv-active-pill-bg">
										<div class="ntv-color-row-label">
											<label for="ntv-active-pill-bg"><?php esc_html_e( 'رنگ پس‌زمینه کپسول فعال:', 'nativly' ); ?></label>
										</div>
										<div class="ntv-color-row-controls">
											<input type="text" name="ntv_settings[active_pill_bg]" id="ntv-active-pill-bg" value="<?php echo esc_attr( $settings['active_pill_bg'] ); ?>" class="ntv-color-picker" data-default-color="#0047FF">
										</div>
										<div class="ntv-color-row-swatches">
											<?php self::render_palette_swatches( $site_palette ); ?>
										</div>
									</div>

									<!-- Row 2: Active Text & Icon -->
									<div class="ntv-color-row-inline" data-target="ntv-active-text-color">
										<div class="ntv-color-row-label">
											<label for="ntv-active-text-color"><?php esc_html_e( 'رنگ متن و آیکون کپسول فعال:', 'nativly' ); ?></label>
										</div>
										<div class="ntv-color-row-controls">
											<input type="text" name="ntv_settings[active_text_color]" id="ntv-active-text-color" value="<?php echo esc_attr( $settings['active_text_color'] ); ?>" class="ntv-color-picker" data-default-color="#FFFFFF">
										</div>
										<div class="ntv-color-row-swatches">
											<?php self::render_palette_swatches( $site_palette ); ?>
										</div>
									</div>

									<!-- Row 3: Inactive Icon Color -->
									<div class="ntv-color-row-inline ntv-color-with-opacity" data-target="ntv-inactive-icon-color">
										<div class="ntv-color-row-label">
											<label><?php esc_html_e( 'رنگ آیکون‌های غیرفعال:', 'nativly' ); ?></label>
										</div>
										<div class="ntv-color-row-controls">
											<input type="text" class="ntv-color-picker ntv-rgba-base-color" value="<?php echo esc_attr( $parsed_inactive['hex'] ); ?>" data-default-color="#FFFFFF">
											<div class="ntv-opacity-control ntv-opacity-slider-inline">
												<span class="ntv-opacity-slider-title"><?php esc_html_e( 'شفافیت:', 'nativly' ); ?></span>
												<input type="range" class="ntv-opacity-slider" min="0" max="100" step="1" value="<?php echo esc_attr( (string) $parsed_inactive['opacity'] ); ?>">
												<span class="ntv-opacity-val" id="ntv-inactive-opacity-val"><?php echo esc_html( (string) $parsed_inactive['opacity'] ); ?>%</span>
											</div>
											<input type="hidden" name="ntv_settings[inactive_icon_color]" id="ntv-inactive-icon-color" value="<?php echo esc_attr( $settings['inactive_icon_color'] ); ?>">
										</div>
										<div class="ntv-color-row-swatches">
											<?php self::render_palette_swatches( $site_palette ); ?>
										</div>
									</div>

									<!-- Row 4: Glass Background -->
									<div class="ntv-color-row-inline ntv-color-with-opacity" data-target="ntv-glass-bg-color">
										<div class="ntv-color-row-label">
											<label><?php esc_html_e( 'رنگ و شفافیت شیشه نوبار:', 'nativly' ); ?></label>
										</div>
										<div class="ntv-color-row-controls">
											<input type="text" class="ntv-color-picker ntv-rgba-base-color" value="<?php echo esc_attr( $parsed_bg['hex'] ); ?>" data-default-color="#121218">
											<div class="ntv-opacity-control ntv-opacity-slider-inline">
												<span class="ntv-opacity-slider-title"><?php esc_html_e( 'شفافیت:', 'nativly' ); ?></span>
												<input type="range" class="ntv-opacity-slider" min="0" max="100" step="1" value="<?php echo esc_attr( (string) $parsed_bg['opacity'] ); ?>">
												<span class="ntv-opacity-val" id="ntv-bg-opacity-val"><?php echo esc_html( (string) $parsed_bg['opacity'] ); ?>%</span>
											</div>
											<input type="hidden" name="ntv_settings[glass_bg_color]" id="ntv-glass-bg-color" value="<?php echo esc_attr( $settings['glass_bg_color'] ); ?>">
										</div>
										<div class="ntv-color-row-swatches">
											<?php self::render_palette_swatches( $site_palette ); ?>
										</div>
									</div>

									<!-- Row 5: Glass Border -->
									<div class="ntv-color-row-inline ntv-color-with-opacity" data-target="ntv-glass-border-color">
										<div class="ntv-color-row-label">
											<label><?php esc_html_e( 'رنگ بوردر شیشه‌ای (Border):', 'nativly' ); ?></label>
										</div>
										<div class="ntv-color-row-controls">
											<input type="text" class="ntv-color-picker ntv-rgba-base-color" value="<?php echo esc_attr( $parsed_border['hex'] ); ?>" data-default-color="#FFFFFF">
											<div class="ntv-opacity-control ntv-opacity-slider-inline">
												<span class="ntv-opacity-slider-title"><?php esc_html_e( 'شفافیت:', 'nativly' ); ?></span>
												<input type="range" class="ntv-opacity-slider" min="0" max="100" step="1" value="<?php echo esc_attr( (string) $parsed_border['opacity'] ); ?>">
												<span class="ntv-opacity-val" id="ntv-border-opacity-val"><?php echo esc_html( (string) $parsed_border['opacity'] ); ?>%</span>
											</div>
											<input type="hidden" name="ntv_settings[glass_border_color]" id="ntv-glass-border-color" value="<?php echo esc_attr( $settings['glass_border_color'] ); ?>">
										</div>
										<div class="ntv-color-row-swatches">
											<?php self::render_palette_swatches( $site_palette ); ?>
										</div>
									</div>
								</div>

								<?php
								// Normalize values to percentages (0-100) with backward compatibility.
								$blur_raw = isset( $settings['glass_blur'] ) ? (int) $settings['glass_blur'] : 50;
								if ( $blur_raw <= 40 && $blur_raw > 0 && isset( $settings['border_radius'] ) && (int) $settings['border_radius'] > 100 ) {
									$blur_percent = (int) round( ( $blur_raw / 40 ) * 100 );
								} else {
									$blur_percent = min( 100, max( 0, $blur_raw ) );
								}

								$radius_raw = isset( $settings['border_radius'] ) ? (int) $settings['border_radius'] : 100;
								if ( $radius_raw > 100 ) {
									$radius_percent = 100;
								} else {
									$radius_percent = min( 100, max( 0, $radius_raw ) );
								}

								$offset_raw = isset( $settings['mobile_bottom_offset'] ) ? (int) $settings['mobile_bottom_offset'] : 25;
								if ( $offset_raw <= 40 && $offset_raw > 0 && isset( $settings['border_radius'] ) && (int) $settings['border_radius'] > 100 ) {
									$offset_percent = (int) round( ( $offset_raw / 64 ) * 100 );
								} else {
									$offset_percent = min( 100, max( 0, $offset_raw ) );
								}
								?>
								<div class="ntv-sliders-stack ntv-mt-4">
									<div class="ntv-slider-row">
										<div class="ntv-slider-label">
											<label for="ntv-glass-blur"><?php esc_html_e( 'شدت تاری پس‌زمینه (Blur):', 'nativly' ); ?></label>
										</div>
										<div class="ntv-slider-control">
											<input type="range" name="ntv_settings[glass_blur]" id="ntv-glass-blur" min="0" max="100" step="1" value="<?php echo esc_attr( (string) $blur_percent ); ?>" class="ntv-slider">
										</div>
										<div class="ntv-slider-badge-wrap">
											<span class="ntv-slider-val-badge" id="ntv-blur-val"><?php echo esc_html( (string) $blur_percent ); ?>%</span>
										</div>
									</div>
									<div class="ntv-slider-row">
										<div class="ntv-slider-label">
											<label for="ntv-border-radius"><?php esc_html_e( 'گردی گوشه‌ها (Border Radius):', 'nativly' ); ?></label>
										</div>
										<div class="ntv-slider-control">
											<input type="range" name="ntv_settings[border_radius]" id="ntv-border-radius" min="0" max="100" step="1" value="<?php echo esc_attr( (string) $radius_percent ); ?>" class="ntv-slider">
										</div>
										<div class="ntv-slider-badge-wrap">
											<span class="ntv-slider-val-badge" id="ntv-radius-val"><?php echo esc_html( (string) $radius_percent ); ?>%</span>
										</div>
									</div>
									<div class="ntv-slider-row">
										<div class="ntv-slider-label">
											<label for="ntv-mobile-bottom-offset"><?php esc_html_e( 'فاصله از پایین صفحه (Bottom Offset):', 'nativly' ); ?></label>
										</div>
										<div class="ntv-slider-control">
											<input type="range" name="ntv_settings[mobile_bottom_offset]" id="ntv-mobile-bottom-offset" min="0" max="100" step="1" value="<?php echo esc_attr( (string) $offset_percent ); ?>" class="ntv-slider">
										</div>
										<div class="ntv-slider-badge-wrap">
											<span class="ntv-slider-val-badge" id="ntv-offset-val"><?php echo esc_html( (string) $offset_percent ); ?>%</span>
										</div>
									</div>
								</div>
							</div>

							<!-- Navbar Width Control Card -->
							<div class="ntv-card ntv-mt-4">
								<h3 class="ntv-card-title"><?php esc_html_e( 'تنظیم عرض نوبار (Navbar Width Mode)', 'nativly' ); ?></h3>
								<p class="ntv-card-subtitle"><?php esc_html_e( 'نحوه نمایش و پهنای نوار شیشه‌ای در صفحه موبایل را انتخاب کنید:', 'nativly' ); ?></p>

								<div class="ntv-radio-group">
									<label>
										<input type="radio" name="ntv_settings[navbar_width_mode]" value="auto" <?php checked( ( $settings['navbar_width_mode'] ?? 'auto' ), 'auto' ); ?>>
										<strong><?php esc_html_e( 'عرض خودکار کپسولی (Auto Capsule - پیش‌فرض)', 'nativly' ); ?></strong>
										<span class="ntv-hint"><?php esc_html_e( 'عرض نوبار به صورت شناور و متناسب با تعداد و اندازه آیتم‌ها تنظیم می‌شود.', 'nativly' ); ?></span>
									</label>
									<label>
										<input type="radio" name="ntv_settings[navbar_width_mode]" value="full" <?php checked( ( $settings['navbar_width_mode'] ?? 'auto' ), 'full' ); ?>>
										<strong><?php esc_html_e( 'تمام‌عرض (Full Width)', 'nativly' ); ?></strong>
										<span class="ntv-hint"><?php esc_html_e( 'نوبار تقریباً تمام عرض صفحه را پر می‌کند (با حاشیه ۱۶ پیکسلی از طرفین: calc(100% - 32px)).', 'nativly' ); ?></span>
									</label>
									<label>
										<input type="radio" name="ntv_settings[navbar_width_mode]" value="custom" <?php checked( ( $settings['navbar_width_mode'] ?? 'auto' ), 'custom' ); ?>>
										<strong><?php esc_html_e( 'عرض سفارشی (Custom Width)', 'nativly' ); ?></strong>
										<span class="ntv-hint"><?php esc_html_e( 'می‌توانید یک مقدار درصدی مانند 90% یا مقدار پیکسلی ثابت مانند 420px تعیین کنید.', 'nativly' ); ?></span>
									</label>
								</div>

								<div id="ntv-custom-width-wrap" class="ntv-field-group" style="<?php echo ( 'custom' === ( $settings['navbar_width_mode'] ?? 'auto' ) ) ? '' : 'display:none;'; ?>">
									<label for="ntv-navbar-custom-width"><?php esc_html_e( 'مقدار عرض سفارشی (درصد یا پیکسل):', 'nativly' ); ?></label>
									<input type="text" name="ntv_settings[navbar_custom_width]" id="ntv-navbar-custom-width" value="<?php echo esc_attr( $settings['navbar_custom_width'] ?? '90%' ); ?>" class="regular-text" placeholder="مثال: 90% یا 420px">
									<span class="ntv-hint"><?php esc_html_e( 'پیشنهاد: از مقادیر بین 85% تا 95% یا 360px تا 480px استفاده کنید.', 'nativly' ); ?></span>
								</div>
							</div>

							<!-- Desktop Position Card -->
							<div class="ntv-card ntv-mt-4">
								<h3 class="ntv-card-title"><?php esc_html_e( 'نمایش در رایانه و دسکتاپ (Desktop Visibility)', 'nativly' ); ?></h3>
								<p class="ntv-card-subtitle"><?php esc_html_e( 'حفاظت کامل از قالب: به صورت پیش‌فرض نوبار در دسکتاپ مخفی است تا هیچ تداخلی با منوهای اصلی قالب شما نداشته باشد.', 'nativly' ); ?></p>
								<div class="ntv-radio-group">
									<label>
										<input type="radio" name="ntv_settings[desktop_position]" value="hidden" <?php checked( $settings['desktop_position'], 'hidden' ); ?>>
										<strong><?php esc_html_e( 'مخفی در دسکتاپ (فقط موبایل و تبلت - پیش‌فرض امن)', 'nativly' ); ?></strong>
										<span class="ntv-hint"><?php esc_html_e( 'نوبار در صفحات عریض‌تر از 768px مخفی است و قالب اصلی کاملاً دست‌نخورده باقی می‌ماند.', 'nativly' ); ?></span>
									</label>
									<label>
										<input type="radio" name="ntv_settings[desktop_position]" value="bottom" <?php checked( $settings['desktop_position'], 'bottom' ); ?>>
										<strong><?php esc_html_e( 'شناور پایین در دسکتاپ (Floating Bottom)', 'nativly' ); ?></strong>
										<span class="ntv-hint"><?php esc_html_e( 'نمایش کپسول مدرن شیشه‌ای در مرکز پایین صفحه نمایش بزرگ.', 'nativly' ); ?></span>
									</label>
								</div>
							</div>
						</div>

						<!-- Tab 3: Theme & Native App Feel -->
						<div id="ntv-tab-theme" class="ntv-tab-content <?php echo 'theme' === $active_tab ? 'is-active' : ''; ?>">
							<div class="ntv-card">
								<h3 class="ntv-card-title"><?php esc_html_e( 'مخفی‌سازی هوشمند هدر قالب (حس اپلیکیشن واقعی)', 'nativly' ); ?></h3>
								<p class="ntv-card-subtitle"><?php esc_html_e( 'برای تبدیل کامل وبسایت به اپلیکیشن، هدر سنگین قالب را در موبایل مخفی کنید تا کاربر فقط محتوای بومی را ببیند:', 'nativly' ); ?></p>

								<label class="ntv-switch-label">
									<input type="checkbox" name="ntv_settings[hide_theme_header]" value="1" <?php checked( $settings['hide_theme_header'], 1 ); ?>>
									<span><?php esc_html_e( 'مخفی‌سازی هدر قالب در دستگاه‌های موبایل', 'nativly' ); ?></span>
								</label>

								<div class="ntv-mt-3">
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'پریست شناسایی خودکار قالب:', 'nativly' ); ?></label>
										<select name="ntv_settings[header_hiding_preset]" class="regular-text">
											<option value="kadence" <?php selected( $settings['header_hiding_preset'], 'kadence' ); ?>>Kadence Theme</option>
											<option value="hello_elementor" <?php selected( $settings['header_hiding_preset'], 'hello_elementor' ); ?>>Hello Elementor</option>
											<option value="elementor_pro" <?php selected( $settings['header_hiding_preset'], 'elementor_pro' ); ?>>Elementor Pro Header Theme Builder</option>
											<option value="astra_gp" <?php selected( $settings['header_hiding_preset'], 'astra_gp' ); ?>>Astra / GeneratePress</option>
											<option value="custom" <?php selected( $settings['header_hiding_preset'], 'custom' ); ?>><?php esc_html_e( 'سلکتور سفارشی CSS', 'nativly' ); ?></option>
										</select>
									</div>

									<div class="ntv-field-group ntv-mt-2">
										<label><?php esc_html_e( 'کلاس یا سلکتور اختصاصی هدر (در صورت نیاز):', 'nativly' ); ?></label>
										<input type="text" name="ntv_settings[custom_header_selector]" value="<?php echo esc_attr( $settings['custom_header_selector'] ); ?>" class="large-text" placeholder=".site-header, header#masthead">
									</div>
								</div>
							</div>

							<!-- Native Experience Enhancements -->
							<div class="ntv-card ntv-mt-4">
								<h3 class="ntv-card-title"><?php esc_html_e( 'بهبودهای تجربه بومی (Native UX)', 'nativly' ); ?></h3>
								<div class="ntv-toggles-list">
									<label class="ntv-switch-label">
										<input type="checkbox" name="ntv_settings[enable_safe_area]" value="1" <?php checked( $settings['enable_safe_area'], 1 ); ?>>
										<span><?php esc_html_e( 'پشتیبانی از Safe Area نوار ناوبری پایین گوشی‌های مدرن (env(safe-area-inset-bottom))', 'nativly' ); ?></span>
									</label>

									<label class="ntv-switch-label">
										<input type="checkbox" name="ntv_settings[enable_overscroll_none]" value="1" <?php checked( $settings['enable_overscroll_none'], 1 ); ?>>
										<span><?php esc_html_e( 'جلوگیری از پرش ارتجاعی هدر در کشیدن صفحه به پایین (overscroll-behavior-y: none)', 'nativly' ); ?></span>
									</label>

									<label class="ntv-switch-label">
										<input type="checkbox" name="ntv_settings[disable_ui_select]" value="1" <?php checked( $settings['disable_ui_select'], 1 ); ?>>
										<span><?php esc_html_e( 'غیرفعال‌سازی هایلایت آبی لمس در المان‌های UI (user-select: none)', 'nativly' ); ?></span>
									</label>

									<label class="ntv-switch-label">
										<input type="checkbox" name="ntv_settings[enable_view_transitions]" value="1" <?php checked( $settings['enable_view_transitions'], 1 ); ?>>
										<span><?php esc_html_e( 'انیمیشن روان جابجایی بین صفحات (View Transitions API در مرورگرهای سازگار)', 'nativly' ); ?></span>
									</label>
								</div>
							</div>

							<!-- Page Targeting & Access Control -->
							<div class="ntv-card ntv-mt-4">
								<h3 class="ntv-card-title"><?php esc_html_e( 'قوانین هدف‌گذاری و استثنائات صفحات', 'nativly' ); ?></h3>

								<div class="ntv-field-group">
									<label><?php esc_html_e( 'نحوه نمایش در صفحات:', 'nativly' ); ?></label>
									<select name="ntv_settings[page_targeting_mode]" class="regular-text">
										<option value="global" <?php selected( $settings['page_targeting_mode'], 'global' ); ?>><?php esc_html_e( 'نمایش در کل سایت (سراسری)', 'nativly' ); ?></option>
										<option value="specific" <?php selected( $settings['page_targeting_mode'], 'specific' ); ?>><?php esc_html_e( 'فقط در برگه/پست‌های مشخص', 'nativly' ); ?></option>
									</select>
								</div>

								<div class="ntv-toggles-list ntv-mt-3">
									<label class="ntv-switch-label">
										<input type="checkbox" name="ntv_settings[exclude_on_cart]" value="1" <?php checked( ! empty( $settings['exclude_on_cart'] ) ); ?>>
										<span><?php esc_html_e( 'مخفی‌سازی نوبار در برگه سبد خرید ووکامرس (تمرکز بر خرید)', 'nativly' ); ?></span>
									</label>

									<label class="ntv-switch-label">
										<input type="checkbox" name="ntv_settings[exclude_on_checkout]" value="1" <?php checked( ! empty( $settings['exclude_on_checkout'] ) ); ?>>
										<span><?php esc_html_e( 'مخفی‌سازی نوبار در برگه تسویه‌حساب ووکامرس (بهبود نرخ تبدیل)', 'nativly' ); ?></span>
									</label>

									<label class="ntv-switch-label">
										<input type="checkbox" name="ntv_settings[hide_cart_for_guests]" value="1" <?php checked( ! empty( $settings['hide_cart_for_guests'] ) ); ?>>
										<span><?php esc_html_e( 'مخفی‌سازی تب سبد خرید برای کاربران واردنشده (مهمان)', 'nativly' ); ?></span>
									</label>
								</div>

								<!-- Excluded URLs Textarea -->
								<div class="ntv-field-group ntv-mt-4">
									<label for="ntv-excluded-urls"><strong><?php esc_html_e( 'استثنا کردن لینک‌ها و لندینگ‌پیج‌ها (Excluded URLs / Paths):', 'nativly' ); ?></strong></label>
									<textarea name="ntv_settings[excluded_urls]" id="ntv-excluded-urls" rows="4" class="large-text code" placeholder="/landing-page&#10;/campaign-*&#10;https://example.com/promo/"><?php echo esc_textarea( $settings['excluded_urls'] ?? '' ); ?></textarea>
									<span class="ntv-hint"><?php esc_html_e( 'هر آدرس، مسیر یا الگوی وایلدکارد (*) را در یک سطر وارد کنید. نوبار در تمام صفحاتی که با این الگوها مطابقت دارند نمایش داده نخواهد شد.', 'nativly' ); ?></span>
								</div>

								<div class="ntv-field-group ntv-mt-3">
									<label><?php esc_html_e( 'محدودیت دسترسی نقش‌های کاربری:', 'nativly' ); ?></label>
									<select name="ntv_settings[user_visibility]" class="regular-text">
										<option value="all" <?php selected( $settings['user_visibility'], 'all' ); ?>><?php esc_html_e( 'همه کاربران (وارد شده و مهمان)', 'nativly' ); ?></option>
										<option value="logged_in" <?php selected( $settings['user_visibility'], 'logged_in' ); ?>><?php esc_html_e( 'فقط کاربران وارد شده به سایت', 'nativly' ); ?></option>
										<option value="guests" <?php selected( $settings['user_visibility'], 'guests' ); ?>><?php esc_html_e( 'فقط کاربران مهمان (وارد نشده)', 'nativly' ); ?></option>
									</select>
								</div>
							</div>
						</div>

						<!-- Tab 4: PWA & Install Prompt -->
						<div id="ntv-tab-pwa" class="ntv-tab-content <?php echo 'pwa' === $active_tab ? 'is-active' : ''; ?>">
							<div class="ntv-card">
								<h3 class="ntv-card-title"><?php esc_html_e( 'تنظیمات اپلیکیشن وب پیش‌رونده (PWA)', 'nativly' ); ?></h3>
								<label class="ntv-switch-label">
									<input type="checkbox" name="ntv_settings[enable_pwa]" value="1" <?php checked( $settings['enable_pwa'], 1 ); ?>>
									<span><?php esc_html_e( 'فعال‌سازی سرویس‌ورکر، مانیفست و قابلیت نصب PWA', 'nativly' ); ?></span>
								</label>

								<div class="ntv-grid-2 ntv-mt-3">
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'نام کامل اپلیکیشن:', 'nativly' ); ?></label>
										<input type="text" name="ntv_settings[app_name]" value="<?php echo esc_attr( $settings['app_name'] ); ?>" class="regular-text">
									</div>
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'نام کوتاه (نمایش زیر آیکون در صفحه اصلی):', 'nativly' ); ?></label>
										<input type="text" name="ntv_settings[app_short_name]" value="<?php echo esc_attr( $settings['app_short_name'] ); ?>" class="regular-text">
									</div>
								</div>

								<div class="ntv-field-group ntv-mt-2">
									<label><?php esc_html_e( 'توضیحات اپلیکیشن:', 'nativly' ); ?></label>
									<textarea name="ntv_settings[app_description]" class="large-text" rows="2"><?php echo esc_textarea( $settings['app_description'] ); ?></textarea>
								</div>

								<div class="ntv-grid-2 ntv-mt-3">
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'رنگ نوار مرورگر موبایل (Theme Color):', 'nativly' ); ?></label>
										<input type="text" name="ntv_settings[theme_color]" value="<?php echo esc_attr( $settings['theme_color'] ); ?>" class="ntv-color-picker">
									</div>
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'رنگ پس‌زمینه اسپلش‌اسکرین (Background Color):', 'nativly' ); ?></label>
										<input type="text" name="ntv_settings[background_color]" value="<?php echo esc_attr( $settings['background_color'] ); ?>" class="ntv-color-picker">
									</div>
								</div>

								<div class="ntv-field-group ntv-mt-4">
									<label><?php esc_html_e( 'آیکون بزرگ اپلیکیشن (512x512 پیکسل PNG):', 'nativly' ); ?></label>
									<div class="ntv-media-uploader">
										<div class="ntv-icon-preview-box" id="ntv-icon-512-preview">
											<?php if ( ! empty( $settings['pwa_icon_512'] ) ) : ?>
												<img src="<?php echo esc_url( $settings['pwa_icon_512'] ); ?>" alt="PWA Icon 512">
											<?php else : ?>
												<span class="dashicons dashicons-format-image"></span>
											<?php endif; ?>
										</div>
										<div>
											<button type="button" class="button button-secondary" id="ntv-upload-icon-btn">
												<span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'انتخاب از رسانه / بارگذاری', 'nativly' ); ?>
											</button>
											<input type="hidden" name="ntv_settings[pwa_icon_id]" id="ntv-pwa-icon-id" value="<?php echo esc_attr( (string) $settings['pwa_icon_id'] ); ?>">
											<input type="hidden" name="ntv_settings[pwa_icon_512]" id="ntv-pwa-icon-512" value="<?php echo esc_url( $settings['pwa_icon_512'] ); ?>">
											<input type="hidden" name="ntv_settings[pwa_icon_192]" id="ntv-pwa-icon-192" value="<?php echo esc_url( $settings['pwa_icon_192'] ); ?>">
											<input type="hidden" name="ntv_settings[pwa_icon_180]" id="ntv-pwa-icon-180" value="<?php echo esc_url( $settings['pwa_icon_180'] ); ?>">
											<p class="ntv-hint"><?php esc_html_e( 'با بارگذاری آیکون اصلی، سایر سایزها (192px برای اندروید و 180px برای آیفون) به طور خودکار تولید می‌شوند.', 'nativly' ); ?></p>
										</div>
									</div>
								</div>
							</div>

							<!-- Install Prompt Configuration -->
							<div class="ntv-card ntv-mt-4">
								<h3 class="ntv-card-title"><?php esc_html_e( 'بنر هوشمند نصب اپلیکیشن (Install Prompt)', 'nativly' ); ?></h3>
								<label class="ntv-switch-label">
									<input type="checkbox" name="ntv_settings[enable_install_prompt]" value="1" <?php checked( $settings['enable_install_prompt'], 1 ); ?>>
									<span><?php esc_html_e( 'نمایش پیام هوشمند نصب (شامل بنر پاپ‌آپ سفارشی و راهنمای ۳ مرحله‌ای در iOS Safari)', 'nativly' ); ?></span>
								</label>

								<div class="ntv-grid-3 ntv-mt-3">
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'تاخیر در نمایش (ثانیه):', 'nativly' ); ?></label>
										<input type="number" name="ntv_settings[install_prompt_delay]" value="<?php echo esc_attr( (string) $settings['install_prompt_delay'] ); ?>" min="0" max="60" class="small-text">
									</div>
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'حداقل بازدید از صفحات:', 'nativly' ); ?></label>
										<input type="number" name="ntv_settings[install_prompt_min_views]" value="<?php echo esc_attr( (string) $settings['install_prompt_min_views'] ); ?>" min="1" max="20" class="small-text">
									</div>
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'توقف نمایش پس از رد کردن (روز):', 'nativly' ); ?></label>
										<input type="number" name="ntv_settings[install_prompt_cooldown_days]" value="<?php echo esc_attr( (string) $settings['install_prompt_cooldown_days'] ); ?>" min="1" max="90" class="small-text">
									</div>
								</div>
							</div>
						</div>

					<!-- Tab 5: Tools & Cache -->
					<div id="ntv-tab-tools" class="ntv-tab-content <?php echo 'tools' === $active_tab ? 'is-active' : ''; ?>">
						<div class="ntv-card">
							<h3 class="ntv-card-title"><?php esc_html_e( 'مدیریت و پاک‌سازی کش', 'nativly' ); ?></h3>
							<p class="ntv-card-subtitle"><?php esc_html_e( 'در صورتی که تغییری در صفحات یا کدهای سایت ایجاد کرده‌اید، با این دکمه کش سرویس‌ورکر کلاینت‌ها را فورس‌آپدیت کنید:', 'nativly' ); ?></p>
							<button type="button" id="ntv-tools-purge-btn" class="ntv-btn ntv-btn-primary">
								<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'پاک‌سازی فوری کش', 'nativly' ); ?>
							</button>
						</div>

						<div class="ntv-card ntv-mt-4">
							<h3 class="ntv-card-title"><?php esc_html_e( 'درون‌ریزی و برون‌بری تنظیمات (JSON)', 'nativly' ); ?></h3>
							<div class="ntv-grid-2">
								<div>
									<h4><?php esc_html_e( 'برون‌بری تنظیمات', 'nativly' ); ?></h4>
									<p class="ntv-hint"><?php esc_html_e( 'دانلود یک نسخه پشتیبان کامل از تمامی آیتم‌ها و استایل‌ها به صورت فایل JSON.', 'nativly' ); ?></p>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=nativly&action=export_settings' ), 'ntv_export_action', 'ntv_export_nonce' ) ); ?>" class="button button-secondary">
										<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'دانلود فایل تنظیمات', 'nativly' ); ?>
									</a>
								</div>
								<div>
									<h4><?php esc_html_e( 'درون‌ریزی تنظیمات', 'nativly' ); ?></h4>
									<p class="ntv-hint"><?php esc_html_e( 'بارگذاری فایل تنظیمات JSON از پیش ذخیره شده.', 'nativly' ); ?></p>
									<div class="ntv-import-fields-wrap">
										<input type="file" name="ntv_import_file" id="ntv-import-file-input" accept=".json" form="ntv-standalone-import-form" required>
										<button type="submit" name="ntv_import_settings_submit" form="ntv-standalone-import-form" class="button button-secondary ntv-mt-2">
											<span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'درون‌ریزی فایل', 'nativly' ); ?>
										</button>
									</div>
								</div>
							</div>
						</div>

						<div class="ntv-card ntv-mt-4 ntv-danger-card">
							<h3 class="ntv-card-title"><?php esc_html_e( 'بازنشانی به حالت کارخانه', 'nativly' ); ?></h3>
							<p class="ntv-card-subtitle"><?php esc_html_e( 'بازگردانی تمامی رنگ‌ها، آیتم‌ها و تنظیمات به مقادیر اولیه افزونه:', 'nativly' ); ?></p>
							<button type="button" id="ntv-reset-settings-btn" class="button button-link-delete">
								<?php esc_html_e( 'بازنشانی کلیه تنظیمات به حالت اولیه', 'nativly' ); ?>
							</button>
						</div>
					</div>

					<!-- Tab 6: Diagnostics -->
					<div id="ntv-tab-diagnostics" class="ntv-tab-content <?php echo 'diagnostics' === $active_tab ? 'is-active' : ''; ?>">
						<?php
						if ( class_exists( 'NTV_Diagnostics' ) ) {
							NTV_Diagnostics::render_tab_content();
						}
						?>
					</div>

					<!-- Prominent Sticky Bottom Save Bar (Inside Settings Form) -->
					<div class="ntv-form-footer-sticky">
						<div class="ntv-footer-sticky-info">
							<span class="dashicons dashicons-saved"></span>
							<span class="ntv-save-status-hint"><?php esc_html_e( 'تغییرات شما پس از ذخیره، فوراً در پیش‌نمایش زنده و سایت اعمال خواهند شد.', 'nativly' ); ?></span>
						</div>
						<button type="submit" class="button button-primary ntv-btn-primary button-hero ntv-save-submit-btn">
							<span class="dashicons dashicons-saved"></span>
							<?php esc_html_e( 'ذخیره تغییرات', 'nativly' ); ?>
						</button>
					</div>
				</form>

				<!-- Standalone JSON Import Form (outside main settings form) -->
				<form id="ntv-standalone-import-form" method="post" enctype="multipart/form-data" action="" style="display:none;">
					<?php wp_nonce_field( 'ntv_import_action', 'ntv_import_nonce' ); ?>
				</form>
			</div>

				<!-- Live Interactive Mobile Preview Column -->
				<div class="ntv-preview-col">
					<div class="ntv-sticky-preview">
						<div class="ntv-phone-mockup">
							<div class="ntv-phone-speaker"></div>
							<div class="ntv-phone-screen">
								<div class="ntv-screen-status-bar">
									<span>9:41</span>
									<span>⚡ 5G</span>
								</div>
								<div class="ntv-screen-content">
									<div class="ntv-dummy-app-header">
										<div class="ntv-dummy-avatar"></div>
										<div class="ntv-dummy-title-line"></div>
									</div>
									<div class="ntv-dummy-banner"></div>
									<div class="ntv-dummy-grid">
										<div class="ntv-dummy-card"></div>
										<div class="ntv-dummy-card"></div>
									</div>
									<div class="ntv-dummy-text-lines">
										<div class="ntv-dummy-line" style="width:85%;"></div>
										<div class="ntv-dummy-line" style="width:65%;"></div>
									</div>
								</div>

								<!-- Bubble Popover in Mockup -->
								<div id="ntv-mockup-bubble-popover" class="ntv-bubble-popover ntv-mockup-bubble-popover" style="display:none;" aria-hidden="true">
									<div class="ntv-bubble-glass-card">
										<div class="ntv-bubble-grid" id="ntv-mockup-bubble-grid">
											<!-- Injected dynamically via ntv-admin.js -->
										</div>
									</div>
								</div>

								<!-- Live Expanding Navbar Inside Phone Mockup -->
								<div id="ntv-preview-nav-root" class="ntv-mockup-nav-container ntv-glass-nav-container">
									<nav class="ntv-glass-nav" role="navigation">
										<div class="ntv-nav-list" id="ntv-preview-nav-list">
											<!-- Injected dynamically via ntv-admin.js -->
										</div>
									</nav>
								</div>
							</div>
							<div class="ntv-phone-home-indicator"></div>
						</div>
						<div class="ntv-preview-caption">
							<span class="dashicons dashicons-visibility"></span>
							<span><?php esc_html_e( 'پیش‌نمایش زنده تعاملی (برای تست انیمیشن روی تب‌ها کلیک کنید)', 'nativly' ); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Lucide Icon Picker Modal -->
		<div id="ntv-icon-picker-modal" class="ntv-modal-backdrop" style="display:none;">
			<div class="ntv-modal-box">
				<div class="ntv-modal-header">
					<h3><?php esc_html_e( 'انتخاب آیکون مدرن (Lucide Icons)', 'nativly' ); ?></h3>
					<button type="button" class="ntv-modal-close-btn">&times;</button>
				</div>
				<div class="ntv-modal-search">
					<input type="text" id="ntv-icon-search-input" placeholder="<?php esc_attr_e( 'جستجوی نام آیکون...', 'nativly' ); ?>">
				</div>
				<div class="ntv-modal-upload-bar">
					<button type="button" id="ntv-modal-upload-icon-btn" class="button button-secondary">
						<span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'آپلود آیکون سفارشی / رسانه', 'nativly' ); ?>
					</button>
				</div>
				<div class="ntv-modal-icons-grid" id="ntv-modal-icons-grid">
					<!-- Icon Cards Injected via JS -->
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a single regular menu item repeater row.
	 *
	 * @param int|string $idx  Row index.
	 * @param array      $item Item data.
	 * @return void
	 */
	public function render_menu_item_row( $idx, $item ) {
		$item_id    = ! empty( $item['id'] ) ? $item['id'] : 'ntv-item-' . $idx;
		$title      = ! empty( $item['title'] ) ? $item['title'] : '';
		$url        = ! empty( $item['url'] ) ? $item['url'] : '';
		$icon       = ! empty( $item['icon'] ) ? $item['icon'] : 'home';
		$custom_svg = ! empty( $item['custom_svg'] ) ? $item['custom_svg'] : '';
		$icon_type  = ! empty( $item['icon_type'] ) ? $item['icon_type'] : 'lucide';
		$badge_type = ! empty( $item['badge_type'] ) ? $item['badge_type'] : 'none';
		$badge_text = ! empty( $item['badge_text'] ) ? $item['badge_text'] : '';
		$visibility = ! empty( $item['visibility'] ) ? $item['visibility'] : 'all';
		$roles      = ! empty( $item['roles'] ) && is_array( $item['roles'] ) ? $item['roles'] : array();

		$svg_markup = ( 'custom' === $icon_type && ! empty( $custom_svg ) )
			? NTV_Icons::get_icon_svg( $custom_svg )
			: NTV_Icons::get_icon_svg( $icon );

		if ( empty( $svg_markup ) ) {
			$svg_markup = NTV_Icons::get_icon_svg( 'home' );
		}
		?>
		<div class="ntv-menu-item-row" data-index="<?php echo esc_attr( (string) $idx ); ?>">
			<div class="ntv-row-drag-handle" title="<?php esc_attr_e( 'جابجایی آیتم', 'nativly' ); ?>">
				<span class="dashicons dashicons-menu"></span>
			</div>

			<div class="ntv-row-preview-icon">
				<button type="button" class="ntv-row-icon-trigger" title="<?php esc_attr_e( 'انتخاب آیکون', 'nativly' ); ?>">
					<span class="ntv-row-current-icon">
						<?php echo $svg_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
				</button>
				<input type="hidden" name="ntv_settings[menu_items][<?php echo esc_attr( (string) $idx ); ?>][icon]" class="ntv-item-icon-input" value="<?php echo esc_attr( $icon ); ?>">
				<input type="hidden" name="ntv_settings[menu_items][<?php echo esc_attr( (string) $idx ); ?>][icon_type]" class="ntv-item-icon-type-input" value="<?php echo esc_attr( $icon_type ); ?>">
				<input type="hidden" name="ntv_settings[menu_items][<?php echo esc_attr( (string) $idx ); ?>][custom_svg]" class="ntv-item-custom-svg-input" value="<?php echo esc_attr( $custom_svg ); ?>">
				<input type="hidden" name="ntv_settings[menu_items][<?php echo esc_attr( (string) $idx ); ?>][id]" value="<?php echo esc_attr( $item_id ); ?>">
			</div>

			<div class="ntv-row-fields">
				<div class="ntv-row-main-inputs">
					<input type="text" name="ntv_settings[menu_items][<?php echo esc_attr( (string) $idx ); ?>][title]" class="regular-text ntv-item-title-input" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( 'عنوان آیتم (مثلاً: فروشگاه)', 'nativly' ); ?>" required>
					<input type="url" name="ntv_settings[menu_items][<?php echo esc_attr( (string) $idx ); ?>][url]" class="regular-text ntv-item-url-input" value="<?php echo esc_attr( $url ); ?>" placeholder="<?php esc_attr_e( 'لینک مقصد (مثلاً: /shop)', 'nativly' ); ?>" required>
				</div>

				<div class="ntv-row-options-toggle">
					<button type="button" class="ntv-toggle-options-btn">
						<span class="dashicons dashicons-arrow-down-alt2"></span> <?php esc_html_e( 'گزینه‌های پیشرفته (نشان، آیکون سفارشی و دسترسی)', 'nativly' ); ?>
					</button>
				</div>

				<div class="ntv-row-extra-options" style="display:none;">
					<div class="ntv-grid-2">
						<div class="ntv-field-group">
							<label><?php esc_html_e( 'نوع نشان (Badge):', 'nativly' ); ?></label>
							<select name="ntv_settings[menu_items][<?php echo esc_attr( (string) $idx ); ?>][badge_type]" class="ntv-item-badge-type-select">
								<option value="none" <?php selected( $badge_type, 'none' ); ?>><?php esc_html_e( 'بدون نشان', 'nativly' ); ?></option>
								<option value="wc_cart_count" <?php selected( $badge_type, 'wc_cart_count' ); ?>><?php esc_html_e( 'تعداد سبد خرید ووکامرس (همگام زنده)', 'nativly' ); ?></option>
								<option value="custom" <?php selected( $badge_type, 'custom' ); ?>><?php esc_html_e( 'متن یا عدد سفارشی', 'nativly' ); ?></option>
							</select>
						</div>

						<div class="ntv-field-group ntv-custom-badge-text-wrap" style="<?php echo 'custom' === $badge_type ? '' : 'display:none;'; ?>">
							<label><?php esc_html_e( 'متن نشان:', 'nativly' ); ?></label>
							<input type="text" name="ntv_settings[menu_items][<?php echo esc_attr( (string) $idx ); ?>][badge_text]" class="ntv-item-badge-text-input" value="<?php echo esc_attr( $badge_text ); ?>" placeholder="<?php esc_attr_e( 'مثال: جدید یا 5', 'nativly' ); ?>">
						</div>
					</div>

					<div class="ntv-field-group ntv-mt-2">
						<label><?php esc_html_e( 'کد SVG سفارشی (جایگزین آیکون‌های پیش‌فرض):', 'nativly' ); ?></label>
						<textarea name="ntv_settings[menu_items][<?php echo esc_attr( (string) $idx ); ?>][custom_svg]" class="large-text ntv-item-custom-svg-textarea" rows="2" placeholder="<svg viewBox='0 0 24 24'>...</svg>"><?php echo esc_textarea( $custom_svg ); ?></textarea>
					</div>

					<div class="ntv-field-group ntv-mt-2">
						<label><?php esc_html_e( 'سطح دسترسی نمایش:', 'nativly' ); ?></label>
						<select name="ntv_settings[menu_items][<?php echo esc_attr( (string) $idx ); ?>][visibility]" class="ntv-item-visibility-select">
							<option value="all" <?php selected( $visibility, 'all' ); ?>><?php esc_html_e( 'نمایش برای همه کاربران', 'nativly' ); ?></option>
							<option value="logged_in" <?php selected( $visibility, 'logged_in' ); ?>><?php esc_html_e( 'فقط کاربران وارد شده', 'nativly' ); ?></option>
							<option value="guests" <?php selected( $visibility, 'guests' ); ?>><?php esc_html_e( 'فقط کاربران مهمان', 'nativly' ); ?></option>
						</select>
					</div>
				</div>
			</div>

			<div class="ntv-row-actions">
				<button type="button" class="ntv-delete-item-btn" title="<?php esc_attr_e( 'حذف آیتم', 'nativly' ); ?>">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a single bubble menu item repeater row.
	 *
	 * @param int|string $idx  Row index.
	 * @param array      $item Item data.
	 * @return void
	 */
	public function render_bubble_menu_item_row( $idx, $item ) {
		$item_id    = ! empty( $item['id'] ) ? $item['id'] : 'ntv-bubble-item-' . $idx;
		$title      = ! empty( $item['title'] ) ? $item['title'] : '';
		$url        = ! empty( $item['url'] ) ? $item['url'] : '';
		$icon       = ! empty( $item['icon'] ) ? $item['icon'] : 'compass';
		$custom_svg = ! empty( $item['custom_svg'] ) ? $item['custom_svg'] : '';
		$icon_type  = ! empty( $item['icon_type'] ) ? $item['icon_type'] : 'lucide';
		$visibility = ! empty( $item['visibility'] ) ? $item['visibility'] : 'all';

		$svg_markup = ( 'custom' === $icon_type && ! empty( $custom_svg ) )
			? NTV_Icons::get_icon_svg( $custom_svg )
			: NTV_Icons::get_icon_svg( $icon );

		if ( empty( $svg_markup ) ) {
			$svg_markup = NTV_Icons::get_icon_svg( 'compass' );
		}
		?>
		<div class="ntv-menu-item-row ntv-bubble-item-row" data-index="<?php echo esc_attr( (string) $idx ); ?>">
			<div class="ntv-row-drag-handle" title="<?php esc_attr_e( 'جابجایی آیتم', 'nativly' ); ?>">
				<span class="dashicons dashicons-menu"></span>
			</div>

			<div class="ntv-row-preview-icon">
				<button type="button" class="ntv-row-icon-trigger" title="<?php esc_attr_e( 'انتخاب آیکون', 'nativly' ); ?>">
					<span class="ntv-row-current-icon">
						<?php echo $svg_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
				</button>
				<input type="hidden" name="ntv_settings[more_menu_items][<?php echo esc_attr( (string) $idx ); ?>][icon]" class="ntv-item-icon-input" value="<?php echo esc_attr( $icon ); ?>">
				<input type="hidden" name="ntv_settings[more_menu_items][<?php echo esc_attr( (string) $idx ); ?>][icon_type]" class="ntv-item-icon-type-input" value="<?php echo esc_attr( $icon_type ); ?>">
				<input type="hidden" name="ntv_settings[more_menu_items][<?php echo esc_attr( (string) $idx ); ?>][custom_svg]" class="ntv-item-custom-svg-input" value="<?php echo esc_attr( $custom_svg ); ?>">
				<input type="hidden" name="ntv_settings[more_menu_items][<?php echo esc_attr( (string) $idx ); ?>][id]" value="<?php echo esc_attr( $item_id ); ?>">
			</div>

			<div class="ntv-row-fields">
				<div class="ntv-row-main-inputs">
					<input type="text" name="ntv_settings[more_menu_items][<?php echo esc_attr( (string) $idx ); ?>][title]" class="regular-text ntv-item-title-input" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( 'عنوان آیتم (مثلاً: درباره ما)', 'nativly' ); ?>" required>
					<input type="url" name="ntv_settings[more_menu_items][<?php echo esc_attr( (string) $idx ); ?>][url]" class="regular-text ntv-item-url-input" value="<?php echo esc_attr( $url ); ?>" placeholder="<?php esc_attr_e( 'لینک مقصد (مثلاً: /about)', 'nativly' ); ?>" required>
				</div>

				<div class="ntv-row-options-toggle">
					<button type="button" class="ntv-toggle-options-btn">
						<span class="dashicons dashicons-arrow-down-alt2"></span> <?php esc_html_e( 'گزینه‌های پیشرفته (آیکون سفارشی و دسترسی)', 'nativly' ); ?>
					</button>
				</div>

				<div class="ntv-row-extra-options" style="display:none;">
					<div class="ntv-field-group">
						<label><?php esc_html_e( 'سطح دسترسی نمایش:', 'nativly' ); ?></label>
						<select name="ntv_settings[more_menu_items][<?php echo esc_attr( (string) $idx ); ?>][visibility]" class="ntv-item-visibility-select">
							<option value="all" <?php selected( $visibility, 'all' ); ?>><?php esc_html_e( 'نمایش برای همه کاربران', 'nativly' ); ?></option>
							<option value="logged_in" <?php selected( $visibility, 'logged_in' ); ?>><?php esc_html_e( 'فقط کاربران وارد شده', 'nativly' ); ?></option>
							<option value="guests" <?php selected( $visibility, 'guests' ); ?>><?php esc_html_e( 'فقط کاربران مهمان', 'nativly' ); ?></option>
						</select>
					</div>
					<div class="ntv-field-group ntv-mt-2">
						<label><?php esc_html_e( 'کد SVG سفارشی یا آدرس آیکون تصویر:', 'nativly' ); ?></label>
						<div style="display:flex; gap:8px; align-items:flex-start;">
							<textarea name="ntv_settings[more_menu_items][<?php echo esc_attr( (string) $idx ); ?>][custom_svg]" class="large-text ntv-item-custom-svg-textarea" rows="2" placeholder="<?php esc_attr_e( '<svg viewBox=\'0 0 24 24\'>...</svg> یا https://.../icon.png', 'nativly' ); ?>"><?php echo esc_textarea( $custom_svg ); ?></textarea>
							<button type="button" class="button button-secondary ntv-upload-custom-icon-btn" title="<?php esc_attr_e( 'آپلود / انتخاب آیکون از رسانه', 'nativly' ); ?>" style="white-space:nowrap; height:42px; display:inline-flex; align-items:center; gap:6px;">
								<span class="dashicons dashicons-admin-media"></span>
								<?php esc_html_e( 'انتخاب رسانه', 'nativly' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>

			<div class="ntv-row-actions">
				<button type="button" class="ntv-delete-item-btn" title="<?php esc_attr_e( 'حذف آیتم', 'nativly' ); ?>">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</div>
		</div>
		<?php
	}
}
