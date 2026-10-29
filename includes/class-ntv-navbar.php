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
 * and visibility rules for the expanding glassmorphic navigation bar.
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
		add_action( 'wp_footer', array( $this, 'render_navbar' ), 90 );
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
		$glass_blur          = isset( $settings['glass_blur'] ) ? (int) $settings['glass_blur'] : 20;
		$border_radius       = isset( $settings['border_radius'] ) ? (int) $settings['border_radius'] : 999;
		$bottom_offset       = isset( $settings['mobile_bottom_offset'] ) ? (int) $settings['mobile_bottom_offset'] : 16;
		$body_bottom_padding = isset( $settings['body_bottom_padding'] ) ? (int) $settings['body_bottom_padding'] : 85;

		$css = ":root {\n";
		$css .= "\t--ntv-active-bg: " . esc_attr( $active_pill_bg ) . ";\n";
		$css .= "\t--ntv-active-text: " . esc_attr( $active_text_color ) . ";\n";
		$css .= "\t--ntv-inactive-color: " . esc_attr( $inactive_icon_color ) . ";\n";
		$css .= "\t--ntv-glass-bg: " . esc_attr( $glass_bg_color ) . ";\n";
		$css .= "\t--ntv-glass-border: " . esc_attr( $glass_border_color ) . ";\n";
		$css .= "\t--ntv-blur: " . esc_attr( (string) $glass_blur ) . "px;\n";
		$css .= "\t--ntv-border-radius: " . esc_attr( (string) $border_radius ) . "px;\n";
		$css .= "\t--ntv-bottom-offset: " . esc_attr( (string) $bottom_offset ) . "px;\n";
		$css .= "\t--ntv-body-padding: " . esc_attr( (string) $body_bottom_padding ) . "px;\n";
		$css .= "}\n";

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
					// Read custom icon attributes if stored in meta or fallback to default.
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

		$display_items = array();

		foreach ( $raw_items as $item ) {
			// Item-level authentication & role checks.
			$visibility = isset( $item['visibility'] ) ? $item['visibility'] : 'all';

			if ( 'logged_in' === $visibility && ! $is_logged_in ) {
				continue;
			}
			if ( 'guests' === $visibility && $is_logged_in ) {
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
		$current_path = (string) wp_parse_url( $current_url, PHP_URL_PATH );
		$item_path    = (string) wp_parse_url( $item_url, PHP_URL_PATH );

		$home_path = trailingslashit( wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ?: '/' );

		// Priority 1: Exact URL or Path Match.
		if ( $current_url === $item_url || ( ! empty( $item_path ) && ! empty( $current_path ) && $item_path === $current_path ) ) {
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

		// Priority 3: Hierarchy & CPT Archive / Single Mappings.
		if ( ! empty( $item_path ) && $item_path !== $home_path && ! empty( $current_path ) ) {
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
		if ( ! $this->should_display() ) {
			return;
		}

		$items = $this->get_display_items();
		if ( empty( $items ) ) {
			return;
		}

		$settings         = NTV_Core::get_settings();
		$desktop_position = isset( $settings['desktop_position'] ) ? $settings['desktop_position'] : 'hidden';
		$is_rtl           = is_rtl();
		$dir_attr         = $is_rtl ? 'rtl' : 'ltr';

		// Prepare active states ensuring STRICTLY ONE tab gets marked as active.
		$active_index = -1;
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

		$wrapper_classes = array(
			'ntv-glass-nav-container',
			'ntv-desktop-' . sanitize_html_class( $desktop_position ),
		);

		if ( ! empty( $settings['enable_overscroll_none'] ) ) {
			$wrapper_classes[] = 'ntv-overscroll-none';
		}

		if ( ! empty( $settings['disable_ui_select'] ) ) {
			$wrapper_classes[] = 'ntv-no-select';
		}

		?>
		<!-- Nativly Expanding Glassmorphic Navbar -->
		<div id="ntv-navbar-root" class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>" dir="<?php echo esc_attr( $dir_attr ); ?>">
			<nav class="ntv-glass-nav" aria-label="<?php esc_attr_e( 'Main Mobile Navigation', 'nativly' ); ?>" role="navigation">
				<div class="ntv-nav-list" role="menubar">
					<?php
					foreach ( $items as $idx => $item ) :
						$is_active   = ( $idx === $active_index );
						$item_classes = array( 'ntv-nav-item' );
						if ( $is_active ) {
							$item_classes[] = 'is-active';
						}

						$item_url   = ! empty( $item['url'] ) ? esc_url( $item['url'] ) : '#';
						$item_title = ! empty( $item['title'] ) ? $item['title'] : '';
						$badge_type = isset( $item['badge_type'] ) ? $item['badge_type'] : 'none';
						$badge_text = isset( $item['badge_text'] ) ? $item['badge_text'] : '';

						// Retrieve SVG Icon.
						$icon_markup = '';
						if ( isset( $item['icon_type'] ) && 'custom' === $item['icon_type'] && ! empty( $item['custom_svg'] ) ) {
							$icon_markup = NTV_Icons::get_icon_svg( $item['custom_svg'] );
						} elseif ( ! empty( $item['icon'] ) ) {
							$icon_markup = NTV_Icons::get_icon_svg( $item['icon'] );
						}

						if ( empty( $icon_markup ) ) {
							// Fallback home icon.
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
				</div>
			</nav>
		</div>
		<?php
	}
}
