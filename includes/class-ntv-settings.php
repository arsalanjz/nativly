<?php
/**
 * Nativly Professional Admin Settings Panel & Controller
 *
 * @package Nativly
 * @author  Arsalan Jabarzare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class NTV_Settings
 *
 * Manages admin menu registration, settings persistence, Live Interactive Mobile Preview,
 * Lucide icon picker modal, AJAX cache purging, and JSON import/export/reset tools.
 */
class NTV_Settings {

	/**
	 * Singleton instance.
	 *
	 * @var NTV_Settings|null
	 */
	private static $instance = null;

	/**
	 * Page hook suffix.
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
	 * Initialize WordPress admin hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings_and_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX Endpoints.
		add_action( 'wp_ajax_ntv_purge_cache', array( $this, 'ajax_purge_cache' ) );
		add_action( 'wp_ajax_ntv_reset_settings', array( $this, 'ajax_reset_settings' ) );
	}

	/**
	 * Register top-level Nativly admin menu.
	 *
	 * @return void
	 */
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

		// Enqueue WordPress Core Pickers.
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script( 'jquery-ui-sortable' );

		// Nativly Admin Styles.
		wp_enqueue_style(
			'ntv-admin-css',
			NTV_URL . 'assets/css/ntv-admin.css',
			array( 'wp-color-picker' ),
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
		wp_localize_script(
			'ntv-admin-js',
			'ntvAdminData',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'ntv_admin_nonce' ),
				'isRTL'       => is_rtl(),
				'icons'       => class_exists( 'NTV_Icons' ) ? array_keys( NTV_Icons::get_catalog() ) : array(),
				'iconSvgs'    => class_exists( 'NTV_Icons' ) ? NTV_Icons::get_catalog() : array(),
				'currentSettings' => NTV_Core::get_settings(),
				'i18n'        => array(
					'purgeSuccess' => __( 'کش PWA با موفقیت پاک‌سازی و بروزرسانی شد!', 'nativly' ),
					'resetConfirm' => __( 'آیا از بازنشانی کلیه تنظیمات به حالت اولیه اطمینان دارید؟', 'nativly' ),
					'selectIcon'   => __( 'انتخاب آیکون', 'nativly' ),
					'copied'       => __( 'کپی شد!', 'nativly' ),
				),
			)
		);
	}

	/**
	 * Register settings sanitization and handle export/import actions.
	 *
	 * @return void
	 */
	public function register_settings_and_actions() {
		// Handle JSON Export.
		if ( isset( $_GET['page'], $_GET['action'] ) && 'nativly' === $_GET['page'] && 'export_settings' === $_GET['action'] ) {
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
			echo $json;
			exit;
		}

		// Handle JSON Import.
		if ( isset( $_POST['ntv_import_settings_submit'] ) ) {
			check_admin_referer( 'ntv_import_action', 'ntv_import_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'اجازه دسترسی ندارید.', 'nativly' ) );
			}

			if ( ! empty( $_FILES['ntv_import_file']['tmp_name'] ) ) {
				$file_content = file_get_contents( $_FILES['ntv_import_file']['tmp_name'] );
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
			add_settings_error( 'ntv_settings', 'ntv_settings_saved', __( 'تنظیمات Nativly با موفقیت ذخیره شدند.', 'nativly' ), 'updated' );
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

		// 2. Glass Styling & Presets.
		$clean['active_preset']       = sanitize_key( $input['active_preset'] ?? 'dark_liquid' );
		$clean['active_pill_bg']      = sanitize_hex_color( $input['active_pill_bg'] ?? '#0047FF' ) ?: '#0047FF';
		$clean['active_text_color']   = sanitize_hex_color( $input['active_text_color'] ?? '#FFFFFF' ) ?: '#FFFFFF';
		$clean['inactive_icon_color'] = sanitize_text_field( $input['inactive_icon_color'] ?? 'rgba(255, 255, 255, 0.7)' );
		$clean['glass_bg_color']      = sanitize_text_field( $input['glass_bg_color'] ?? 'rgba(18, 18, 24, 0.82)' );
		$clean['glass_border_color']  = sanitize_text_field( $input['glass_border_color'] ?? 'rgba(255, 255, 255, 0.12)' );
		$clean['glass_blur']          = isset( $input['glass_blur'] ) ? absint( $input['glass_blur'] ) : 20;
		$clean['glass_opacity']       = isset( $input['glass_opacity'] ) ? floatval( $input['glass_opacity'] ) : 0.82;
		$clean['border_radius']       = isset( $input['border_radius'] ) ? absint( $input['border_radius'] ) : 999;
		$clean['desktop_position']    = in_array( $input['desktop_position'] ?? '', array( 'hidden', 'top', 'bottom' ), true ) ? $input['desktop_position'] : 'hidden';
		$clean['mobile_bottom_offset'] = isset( $input['mobile_bottom_offset'] ) ? absint( $input['mobile_bottom_offset'] ) : 16;
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
		$clean['exclude_on_cart']      = ! empty( $input['exclude_on_cart'] ) ? 1 : 0;
		$clean['exclude_on_checkout']  = ! empty( $input['exclude_on_checkout'] ) ? 1 : 0;
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
	public function ajax_purge_cache() {
		check_ajax_referer( 'ntv_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'اجازه دسترسی ندارید.', 'nativly' ) ) );
		}

		// Update cache version to timestamp.
		$new_version = time();
		update_option( 'ntv_cache_version', $new_version, false );

		wp_send_json_success( array(
			'message' => __( 'کش PWA با موفقیت پاک‌سازی شد. کلاینت‌ها در بارگذاری بعدی نسخه جدید را دریافت خواهند کرد.', 'nativly' ),
			'version' => $new_version,
		) );
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

		wp_send_json_success( array(
			'message' => __( 'تمامی تنظیمات با موفقیت به حالت پیش‌فرض بازگردانی شدند.', 'nativly' ),
		) );
	}

	/**
	 * Render Nativly Admin Settings Page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		$settings = NTV_Core::get_settings();
		$is_rtl   = is_rtl();
		$dir      = $is_rtl ? 'rtl' : 'ltr';
		?>
		<div class="wrap ntv-admin-wrap" dir="<?php echo esc_attr( $dir ); ?>">
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
						<?php esc_html_e( 'پاک‌سازی کش PWA', 'nativly' ); ?>
					</button>
					<button type="submit" form="ntv-settings-form" class="button button-primary ntv-btn-primary">
						<span class="dashicons dashicons-saved"></span>
						<?php esc_html_e( 'ذخیره تنظیمات', 'nativly' ); ?>
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
						<button type="button" class="ntv-tab-btn is-active" data-tab="menu"><?php esc_html_e( 'آیتم‌های نوبار', 'nativly' ); ?></button>
						<button type="button" class="ntv-tab-btn" data-tab="styling"><?php esc_html_e( 'طراحی شیشه‌ای و استایل', 'nativly' ); ?></button>
						<button type="button" class="ntv-tab-btn" data-tab="theme"><?php esc_html_e( 'قالب و حس اپلیکیشن', 'nativly' ); ?></button>
						<button type="button" class="ntv-tab-btn" data-tab="pwa"><?php esc_html_e( 'اپلیکیشن و PWA', 'nativly' ); ?></button>
						<button type="button" class="ntv-tab-btn" data-tab="tools"><?php esc_html_e( 'ابزارها و کش', 'nativly' ); ?></button>
						<button type="button" class="ntv-tab-btn" data-tab="diagnostics"><?php esc_html_e( 'وضعیت و عیب‌یابی', 'nativly' ); ?></button>
					</nav>

					<!-- Settings Form -->
					<form id="ntv-settings-form" method="post" action="">
						<?php wp_nonce_field( 'ntv_save_settings', 'ntv_save_settings_nonce' ); ?>

						<!-- Tab 1: Menu Items -->
						<div id="ntv-tab-menu" class="ntv-tab-content is-active">
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
											echo '<option value="' . esc_attr( $menu->term_id ) . '" ' . selected( $settings['wp_nav_menu_id'], $menu->term_id, false ) . '>' . esc_html( $menu->name ) . '</option>';
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
						</div>

						<!-- Tab 2: Glass Styling & Presets -->
						<div id="ntv-tab-styling" class="ntv-tab-content">
							<div class="ntv-card">
								<h3 class="ntv-card-title"><?php esc_html_e( 'پالت‌ها و پریست‌های آماده (یک‌کلیک)', 'nativly' ); ?></h3>
								<p class="ntv-card-subtitle"><?php esc_html_e( 'یکی از پریست‌های استاندارد را انتخاب کنید یا رنگ‌ها را مطابق سلیقه خود تغییر دهید:', 'nativly' ); ?></p>
								<div class="ntv-presets-grid">
									<button type="button" class="ntv-preset-card" data-preset="dark_liquid">
										<span class="ntv-preset-color" style="background:#0047FF;"></span>
										<strong>Dark Liquid Pill</strong>
										<small><?php esc_html_e( 'شیشه تیره + کپسول آبی نئون', 'nativly' ); ?></small>
									</button>
									<button type="button" class="ntv-preset-card" data-preset="frosted_light">
										<span class="ntv-preset-color" style="background:#0F172A;"></span>
										<strong>Frosted Light Glass</strong>
										<small><?php esc_html_e( 'شیشه شفاف روشن + متن تیره', 'nativly' ); ?></small>
									</button>
									<button type="button" class="ntv-preset-card" data-preset="handicraft">
										<span class="ntv-preset-color" style="background:#C88A32;"></span>
										<strong>Handicraft Warm</strong>
										<small><?php esc_html_e( 'شیشه شکلاتی گرم + کپسول طلایی', 'nativly' ); ?></small>
									</button>
									<button type="button" class="ntv-preset-card" data-preset="emerald">
										<span class="ntv-preset-color" style="background:#10B981;"></span>
										<strong>Emerald Minimal</strong>
										<small><?php esc_html_e( 'شیشه تیره + کپسول زمردی', 'nativly' ); ?></small>
									</button>
								</div>
								<input type="hidden" name="ntv_settings[active_preset]" id="ntv-active-preset-input" value="<?php echo esc_attr( $settings['active_preset'] ); ?>">
							</div>

							<div class="ntv-card ntv-mt-4">
								<h3 class="ntv-card-title"><?php esc_html_e( 'تنظیمات رنگ و شفافیت نوبار', 'nativly' ); ?></h3>
								<div class="ntv-grid-2">
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'رنگ کپسول فعال (Active Pill):', 'nativly' ); ?></label>
										<input type="text" name="ntv_settings[active_pill_bg]" id="ntv-color-active-pill" class="ntv-color-picker" value="<?php echo esc_attr( $settings['active_pill_bg'] ); ?>">
									</div>
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'رنگ متن و آیکون تب فعال:', 'nativly' ); ?></label>
										<input type="text" name="ntv_settings[active_text_color]" id="ntv-color-active-text" class="ntv-color-picker" value="<?php echo esc_attr( $settings['active_text_color'] ); ?>">
									</div>
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'رنگ آیکون‌های غیرفعال:', 'nativly' ); ?></label>
										<input type="text" name="ntv_settings[inactive_icon_color]" id="ntv-color-inactive-icon" class="regular-text" value="<?php echo esc_attr( $settings['inactive_icon_color'] ); ?>">
									</div>
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'رنگ پس‌زمینه شیشه (Glass Background):', 'nativly' ); ?></label>
										<input type="text" name="ntv_settings[glass_bg_color]" id="ntv-color-glass-bg" class="regular-text" value="<?php echo esc_attr( $settings['glass_bg_color'] ); ?>">
									</div>
								</div>

								<div class="ntv-grid-2 ntv-mt-3">
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'میزان تاری شیشه (Backdrop Blur):', 'nativly' ); ?> <span id="ntv-blur-val"><?php echo esc_html( $settings['glass_blur'] ); ?>px</span></label>
										<input type="range" name="ntv_settings[glass_blur]" id="ntv-blur-slider" min="0" max="40" step="1" value="<?php echo esc_attr( $settings['glass_blur'] ); ?>" class="ntv-slider">
									</div>
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'گردی لبه‌ها (Border Radius):', 'nativly' ); ?> <span id="ntv-radius-val"><?php echo esc_html( $settings['border_radius'] ); ?>px</span></label>
										<input type="range" name="ntv_settings[border_radius]" id="ntv-radius-slider" min="0" max="999" step="5" value="<?php echo esc_attr( $settings['border_radius'] ); ?>" class="ntv-slider">
									</div>
								</div>
							</div>

							<div class="ntv-card ntv-mt-4">
								<h3 class="ntv-card-title"><?php esc_html_e( 'موقعیت و فواصل نمایش', 'nativly' ); ?></h3>
								<div class="ntv-grid-2">
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'وضعیت نوبار در مانیتور دسکتاپ:', 'nativly' ); ?></label>
										<select name="ntv_settings[desktop_position]" id="ntv-desktop-pos-select" class="regular-text">
											<option value="hidden" <?php selected( $settings['desktop_position'], 'hidden' ); ?>><?php esc_html_e( 'مخفی در دسکتاپ (پیشنهادی جهت امنیت قالب)', 'nativly' ); ?></option>
											<option value="bottom" <?php selected( $settings['desktop_position'], 'bottom' ); ?>><?php esc_html_e( 'شناور در پایین صفحه دسکتاپ', 'nativly' ); ?></option>
											<option value="top" <?php selected( $settings['desktop_position'], 'top' ); ?>><?php esc_html_e( 'شناور در بالای صفحه دسکتاپ', 'nativly' ); ?></option>
										</select>
									</div>
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'فاصله شناوری از پایین در موبایل (px):', 'nativly' ); ?></label>
										<input type="number" name="ntv_settings[mobile_bottom_offset]" id="ntv-offset-input" value="<?php echo esc_attr( $settings['mobile_bottom_offset'] ); ?>" min="0" max="60" class="small-text">
									</div>
								</div>
							</div>
						</div>

						<!-- Tab 3: Theme & Native Feel -->
						<div id="ntv-tab-theme" class="ntv-tab-content">
							<div class="ntv-card">
								<h3 class="ntv-card-title"><?php esc_html_e( 'مخفی‌سازی هوشمند هدر قالب اصلی', 'nativly' ); ?></h3>
								<p class="ntv-card-subtitle"><?php esc_html_e( 'جلوگیری از نمایش همزمان دو نوار ناوبری با مخفی‌کردن هدر قالب فقط در ابعاد موبایل:', 'nativly' ); ?></p>
								
								<label class="ntv-switch-label">
									<input type="checkbox" name="ntv_settings[hide_theme_header]" value="1" <?php checked( $settings['hide_theme_header'], 1 ); ?>>
									<span><?php esc_html_e( 'فعال‌سازی مخفی‌سازی خودکار هدر قالب', 'nativly' ); ?></span>
								</label>

								<div class="ntv-grid-2 ntv-mt-3">
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'قالب یا صفحه‌ساز فعال:', 'nativly' ); ?></label>
										<select name="ntv_settings[header_hiding_preset]" id="ntv-header-preset" class="regular-text">
											<option value="kadence" <?php selected( $settings['header_hiding_preset'], 'kadence' ); ?>>Kadence Theme</option>
											<option value="hello_elementor" <?php selected( $settings['header_hiding_preset'], 'hello_elementor' ); ?>>Hello Elementor</option>
											<option value="elementor_pro" <?php selected( $settings['header_hiding_preset'], 'elementor_pro' ); ?>>Elementor Pro Theme Builder</option>
											<option value="astra_gp" <?php selected( $settings['header_hiding_preset'], 'astra_gp' ); ?>>Astra / GeneratePress</option>
											<option value="custom" <?php selected( $settings['header_hiding_preset'], 'custom' ); ?>><?php esc_html_e( 'سلکتور CSS دلخواه (Custom)', 'nativly' ); ?></option>
										</select>
									</div>
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'دستگاه‌های هدف مخفی‌سازی:', 'nativly' ); ?></label>
										<select name="ntv_settings[hide_header_device]" class="regular-text">
											<option value="mobile" <?php selected( $settings['hide_header_device'], 'mobile' ); ?>><?php esc_html_e( 'فقط موبایل (زیر 768 پیکسل - امن‌ترین حالت)', 'nativly' ); ?></option>
											<option value="mobile_tablet" <?php selected( $settings['hide_header_device'], 'mobile_tablet' ); ?>><?php esc_html_e( 'موبایل و تبلت (زیر 1024 پیکسل)', 'nativly' ); ?></option>
											<option value="all" <?php selected( $settings['hide_header_device'], 'all' ); ?>><?php esc_html_e( 'کلیه دستگاه‌ها', 'nativly' ); ?></option>
										</select>
									</div>
								</div>

								<div id="ntv-custom-header-wrap" class="ntv-field-group ntv-mt-3" style="<?php echo 'custom' === $settings['header_hiding_preset'] ? '' : 'display:none;'; ?>">
									<label><?php esc_html_e( 'سلکتور CSS هدر قالب:', 'nativly' ); ?></label>
									<input type="text" name="ntv_settings[custom_header_selector]" class="large-text" value="<?php echo esc_attr( $settings['custom_header_selector'] ); ?>" placeholder=".site-header, #masthead">
								</div>
							</div>

							<div class="ntv-card ntv-mt-4">
								<h3 class="ntv-card-title"><?php esc_html_e( 'حس اپلیکیشن بومی (Native App Feel)', 'nativly' ); ?></h3>
								<div class="ntv-toggles-list">
									<label class="ntv-switch-label">
										<input type="checkbox" name="ntv_settings[enable_safe_area]" value="1" <?php checked( $settings['enable_safe_area'], 1 ); ?>>
										<div>
											<strong><?php esc_html_e( 'پشتیبانی از Safe Area آیفون (env(safe-area-inset-bottom))', 'nativly' ); ?></strong>
											<span class="ntv-hint"><?php esc_html_e( 'جلوگیری از همپوشانی نوبار با خط هوم اسکرین در آیفون‌های دارای ناچ یا Dynamic Island.', 'nativly' ); ?></span>
										</div>
									</label>

									<label class="ntv-switch-label">
										<input type="checkbox" name="ntv_settings[enable_overscroll_none]" value="1" <?php checked( $settings['enable_overscroll_none'], 1 ); ?>>
										<div>
											<strong><?php esc_html_e( 'غیرفعال‌سازی افکت کشسانی مرورگر (overscroll-behavior-y: none)', 'nativly' ); ?></strong>
											<span class="ntv-hint"><?php esc_html_e( 'جلوگیری از Pull-to-refresh ناخواسته مرورگر برای ایجاد حس نرم‌افزار نصب‌شده.', 'nativly' ); ?></span>
										</div>
									</label>

									<label class="ntv-switch-label">
										<input type="checkbox" name="ntv_settings[disable_ui_select]" value="1" <?php checked( $settings['disable_ui_select'], 1 ); ?>>
										<div>
											<strong><?php esc_html_e( 'غیرفعال‌سازی هایلایت آبی لمس و انتخاب متن نوبار (user-select: none)', 'nativly' ); ?></strong>
											<span class="ntv-hint"><?php esc_html_e( 'متون مقالات، پست‌ها و اینپوت‌ها کماکان قابل کپی خواهند بود.', 'nativly' ); ?></span>
										</div>
									</label>

									<label class="ntv-switch-label">
										<input type="checkbox" name="ntv_settings[enable_view_transitions]" value="1" <?php checked( $settings['enable_view_transitions'], 1 ); ?>>
										<div>
											<strong><?php esc_html_e( 'انیمیشن‌های جابجایی بین صفحات (View Transitions API)', 'nativly' ); ?></strong>
											<span class="ntv-hint"><?php esc_html_e( 'انتقال نرم و سینمایی هنگام جابجایی بین صفحات در مرورگرهای سازگار.', 'nativly' ); ?></span>
										</div>
									</label>
								</div>
							</div>

							<div class="ntv-card ntv-mt-4">
								<h3 class="ntv-card-title"><?php esc_html_e( 'قوانین هدف‌گذاری و استثنائات صفحات', 'nativly' ); ?></h3>
								<div class="ntv-grid-2">
									<label class="ntv-switch-label">
										<input type="checkbox" name="ntv_settings[exclude_on_cart]" value="1" <?php checked( $settings['exclude_on_cart'], 1 ); ?>>
										<span><?php esc_html_e( 'مخفی‌سازی نوبار در صفحه سبد خرید ووکامرس', 'nativly' ); ?></span>
									</label>
									<label class="ntv-switch-label">
										<input type="checkbox" name="ntv_settings[exclude_on_checkout]" value="1" <?php checked( $settings['exclude_on_checkout'], 1 ); ?>>
										<span><?php esc_html_e( 'مخفی‌سازی نوبار در صفحه تسویه‌حساب (Checkout)', 'nativly' ); ?></span>
									</label>
								</div>
							</div>
						</div>

						<!-- Tab 4: PWA & Install Prompt -->
						<div id="ntv-tab-pwa" class="ntv-tab-content">
							<div class="ntv-card">
								<h3 class="ntv-card-title"><?php esc_html_e( 'هسته Progressive Web App (PWA)', 'nativly' ); ?></h3>
								<label class="ntv-switch-label">
									<input type="checkbox" name="ntv_settings[enable_pwa]" value="1" <?php checked( $settings['enable_pwa'], 1 ); ?>>
									<strong><?php esc_html_e( 'فعال‌سازی سرویس‌ورکر و مانیفست وب‌اپلیکیشن (PWA)', 'nativly' ); ?></strong>
								</label>

								<div class="ntv-grid-2 ntv-mt-3">
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'نام کامل اپلیکیشن:', 'nativly' ); ?></label>
										<input type="text" name="ntv_settings[app_name]" class="regular-text" value="<?php echo esc_attr( $settings['app_name'] ); ?>">
									</div>
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'نام کوتاه هوم‌اسکرین (iOS & Android):', 'nativly' ); ?></label>
										<input type="text" name="ntv_settings[app_short_name]" class="regular-text" value="<?php echo esc_attr( $settings['app_short_name'] ); ?>" maxlength="15">
									</div>
								</div>

								<div class="ntv-field-group ntv-mt-3">
									<label><?php esc_html_e( 'توضیحات کوتاه اپلیکیشن:', 'nativly' ); ?></label>
									<textarea name="ntv_settings[app_description]" class="large-text" rows="2"><?php echo esc_textarea( $settings['app_description'] ); ?></textarea>
								</div>

								<div class="ntv-grid-2 ntv-mt-3">
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'رنگ تم نوار وضعیت (Theme Color):', 'nativly' ); ?></label>
										<input type="text" name="ntv_settings[theme_color]" class="ntv-color-picker" value="<?php echo esc_attr( $settings['theme_color'] ); ?>">
									</div>
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'رنگ پس‌زمینه اسپلش اسکرین:', 'nativly' ); ?></label>
										<input type="text" name="ntv_settings[background_color]" class="ntv-color-picker" value="<?php echo esc_attr( $settings['background_color'] ); ?>">
									</div>
								</div>

								<!-- 512x512 Icon Upload with Auto-generator -->
								<div class="ntv-field-group ntv-mt-3">
									<label><?php esc_html_e( 'آیکون با کیفیت اپلیکیشن (512x512 PNG):', 'nativly' ); ?></label>
									<div class="ntv-media-uploader">
										<input type="hidden" name="ntv_settings[pwa_icon_id]" id="ntv-pwa-icon-id" value="<?php echo esc_attr( $settings['pwa_icon_id'] ); ?>">
										<input type="hidden" name="ntv_settings[pwa_icon_512]" id="ntv-pwa-icon-512" value="<?php echo esc_attr( $settings['pwa_icon_512'] ); ?>">
										<input type="hidden" name="ntv_settings[pwa_icon_192]" id="ntv-pwa-icon-192" value="<?php echo esc_attr( $settings['pwa_icon_192'] ); ?>">
										<input type="hidden" name="ntv_settings[pwa_icon_180]" id="ntv-pwa-icon-180" value="<?php echo esc_attr( $settings['pwa_icon_180'] ); ?>">

										<div id="ntv-icon-preview-box" class="ntv-icon-preview-box">
											<?php if ( ! empty( $settings['pwa_icon_512'] ) ) : ?>
												<img src="<?php echo esc_url( $settings['pwa_icon_512'] ); ?>" alt="App Icon" width="64" height="64">
											<?php else : ?>
												<span class="dashicons dashicons-format-image"></span>
											<?php endif; ?>
										</div>
										<button type="button" id="ntv-upload-icon-btn" class="button button-secondary"><?php esc_html_e( 'انتخاب از کتابخانه رسانه', 'nativly' ); ?></button>
										<span class="ntv-hint"><?php esc_html_e( 'هنگام ذخیره، نسخه‌های 192x192 و 180x180 (مخصوص iOS Touch Icon) به طور خودکار ساخته می‌شوند.', 'nativly' ); ?></span>
									</div>
								</div>
							</div>

							<!-- Install Prompt Settings -->
							<div class="ntv-card ntv-mt-4">
								<h3 class="ntv-card-title"><?php esc_html_e( 'بنر هوشمند نصب اپلیکیشن (Smart Install Prompt)', 'nativly' ); ?></h3>
								<label class="ntv-switch-label">
									<input type="checkbox" name="ntv_settings[enable_install_prompt]" value="1" <?php checked( $settings['enable_install_prompt'], 1 ); ?>>
									<strong><?php esc_html_e( 'نمایش پیام هوشمند دعوت به نصب در مرورگرها و راهنمای تصویری iOS Safari', 'nativly' ); ?></strong>
								</label>

								<div class="ntv-grid-3 ntv-mt-3">
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'تاخیر در نمایش (ثانیه):', 'nativly' ); ?></label>
										<input type="number" name="ntv_settings[install_prompt_delay]" value="<?php echo esc_attr( $settings['install_prompt_delay'] ); ?>" min="0" max="60" class="small-text">
									</div>
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'حداقل بازدید از صفحات:', 'nativly' ); ?></label>
										<input type="number" name="ntv_settings[install_prompt_min_views]" value="<?php echo esc_attr( $settings['install_prompt_min_views'] ); ?>" min="1" max="20" class="small-text">
									</div>
									<div class="ntv-field-group">
										<label><?php esc_html_e( 'توقف نمایش پس از رد کردن (روز):', 'nativly' ); ?></label>
										<input type="number" name="ntv_settings[install_prompt_cooldown_days]" value="<?php echo esc_attr( $settings['install_prompt_cooldown_days'] ); ?>" min="1" max="90" class="small-text">
									</div>
								</div>
							</div>
						</div>

						<!-- Tab 5: Tools & Cache -->
						<div id="ntv-tab-tools" class="ntv-tab-content">
							<div class="ntv-card">
								<h3 class="ntv-card-title"><?php esc_html_e( 'مدیریت و پاک‌سازی کش PWA', 'nativly' ); ?></h3>
								<p class="ntv-card-subtitle"><?php esc_html_e( 'در صورتی که تغییری در صفحات یا کدهای سایت ایجاد کرده‌اید، با این دکمه کش سرویس‌ورکر کلاینت‌ها را فورس‌آپدیت کنید:', 'nativly' ); ?></p>
								<button type="button" id="ntv-tools-purge-btn" class="button button-secondary ntv-btn-action">
									<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'پاک‌سازی فوری کش PWA', 'nativly' ); ?>
								</button>
								<p class="ntv-hint ntv-mt-2"><?php esc_html_e( 'نسخه کش فعلی:', 'nativly' ); ?> <code>ntv-v<?php echo esc_html( get_option( 'ntv_cache_version', 1 ) ); ?></code></p>
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
										<form method="post" enctype="multipart/form-data" action="" style="display:inline;">
											<?php wp_nonce_field( 'ntv_import_action', 'ntv_import_nonce' ); ?>
											<input type="file" name="ntv_import_file" accept=".json" required>
											<button type="submit" name="ntv_import_settings_submit" class="button button-secondary ntv-mt-2">
												<span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'درون‌ریزی فایل', 'nativly' ); ?>
											</button>
										</form>
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
						<div id="ntv-tab-diagnostics" class="ntv-tab-content">
							<?php
							if ( class_exists( 'NTV_Diagnostics' ) ) {
								NTV_Diagnostics::render_tab_content();
							}
							?>
						</div>
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

								<!-- Live Expanding Navbar Inside Phone Mockup -->
								<div id="ntv-preview-nav-root" class="ntv-mockup-nav-container">
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

			<!-- Lucide Visual Icon Picker Modal -->
			<div id="ntv-icon-picker-modal" class="ntv-modal-backdrop" style="display:none;">
				<div class="ntv-modal-box">
					<div class="ntv-modal-header">
						<h3><?php esc_html_e( 'انتخاب آیکون وکتور Lucide', 'nativly' ); ?></h3>
						<button type="button" class="ntv-modal-close-btn">&times;</button>
					</div>
					<div class="ntv-modal-search">
						<input type="text" id="ntv-icon-search-input" placeholder="<?php esc_attr_e( 'جستجوی آیکون...', 'nativly' ); ?>">
					</div>
					<div class="ntv-modal-icons-grid" id="ntv-modal-icons-grid">
						<!-- Populated via JS -->
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Helper: Render a single Menu Item repeater row.
	 *
	 * @param int   $idx Index.
	 * @param array $item Item data.
	 * @return void
	 */
	public function render_menu_item_row( $idx, $item ) {
		$icon_key    = ! empty( $item['icon'] ) ? $item['icon'] : 'home';
		$icon_type   = ! empty( $item['icon_type'] ) ? $item['icon_type'] : 'lucide';
		$badge_type  = ! empty( $item['badge_type'] ) ? $item['badge_type'] : 'none';
		$badge_text  = ! empty( $item['badge_text'] ) ? $item['badge_text'] : '';
		$visibility  = ! empty( $item['visibility'] ) ? $item['visibility'] : 'all';
		$custom_svg  = ! empty( $item['custom_svg'] ) ? $item['custom_svg'] : '';
		?>
		<div class="ntv-menu-item-row" data-index="<?php echo esc_attr( $idx ); ?>">
			<div class="ntv-row-drag-handle">
				<span class="dashicons dashicons-menu"></span>
			</div>

			<div class="ntv-row-preview-icon">
				<button type="button" class="ntv-row-icon-trigger" title="<?php esc_attr_e( 'تغییر آیکون', 'nativly' ); ?>">
					<span class="ntv-row-current-icon">
						<?php
						if ( 'custom' === $icon_type && ! empty( $custom_svg ) && class_exists( 'NTV_Icons' ) ) {
							echo NTV_Icons::get_icon_svg( $custom_svg );
						} elseif ( class_exists( 'NTV_Icons' ) ) {
							echo NTV_Icons::get_icon_svg( $icon_key );
						}
						?>
					</span>
				</button>
				<input type="hidden" name="ntv_settings[menu_items][<?php echo esc_attr( $idx ); ?>][icon]" class="ntv-item-icon-input" value="<?php echo esc_attr( $icon_key ); ?>">
				<input type="hidden" name="ntv_settings[menu_items][<?php echo esc_attr( $idx ); ?>][icon_type]" class="ntv-item-icon-type-input" value="<?php echo esc_attr( $icon_type ); ?>">
				<input type="hidden" name="ntv_settings[menu_items][<?php echo esc_attr( $idx ); ?>][id]" value="<?php echo esc_attr( $item['id'] ?? uniqid( 'item_' ) ); ?>">
			</div>

			<div class="ntv-row-fields">
				<div class="ntv-row-main-inputs">
					<input type="text" name="ntv_settings[menu_items][<?php echo esc_attr( $idx ); ?>][title]" class="ntv-item-title-input regular-text" value="<?php echo esc_attr( $item['title'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'عنوان تب', 'nativly' ); ?>" required>
					<input type="url" name="ntv_settings[menu_items][<?php echo esc_attr( $idx ); ?>][url]" class="ntv-item-url-input regular-text" value="<?php echo esc_attr( $item['url'] ?? '' ); ?>" placeholder="https://..." required>
				</div>

				<div class="ntv-row-options-toggle">
					<button type="button" class="ntv-toggle-options-btn"><?php esc_html_e( 'تنظیمات بیشتر (نشان و دسترسی)', 'nativly' ); ?> &darr;</button>
				</div>

				<div class="ntv-row-extra-options" style="display:none;">
					<div class="ntv-grid-3">
						<div class="ntv-field-group">
							<label><?php esc_html_e( 'نوع نشان (Badge):', 'nativly' ); ?></label>
							<select name="ntv_settings[menu_items][<?php echo esc_attr( $idx ); ?>][badge_type]" class="ntv-item-badge-type">
								<option value="none" <?php selected( $badge_type, 'none' ); ?>><?php esc_html_e( 'بدون نشان', 'nativly' ); ?></option>
								<option value="wc_cart_count" <?php selected( $badge_type, 'wc_cart_count' ); ?>><?php esc_html_e( 'شمارنده سبد خرید ووکامرس', 'nativly' ); ?></option>
								<option value="custom" <?php selected( $badge_type, 'custom' ); ?>><?php esc_html_e( 'متن دلخواه', 'nativly' ); ?></option>
							</select>
						</div>
						<div class="ntv-field-group">
							<label><?php esc_html_e( 'متن نشان دلخواه:', 'nativly' ); ?></label>
							<input type="text" name="ntv_settings[menu_items][<?php echo esc_attr( $idx ); ?>][badge_text]" class="ntv-item-badge-text" value="<?php echo esc_attr( $badge_text ); ?>" placeholder="NEW">
						</div>
						<div class="ntv-field-group">
							<label><?php esc_html_e( 'سطح دسترسی:', 'nativly' ); ?></label>
							<select name="ntv_settings[menu_items][<?php echo esc_attr( $idx ); ?>][visibility]">
								<option value="all" <?php selected( $visibility, 'all' ); ?>><?php esc_html_e( 'همه کاربران', 'nativly' ); ?></option>
								<option value="logged_in" <?php selected( $visibility, 'logged_in' ); ?>><?php esc_html_e( 'فقط کاربران وارد شده', 'nativly' ); ?></option>
								<option value="guests" <?php selected( $visibility, 'guests' ); ?>><?php esc_html_e( 'فقط کاربران مهمان', 'nativly' ); ?></option>
							</select>
						</div>
					</div>

					<div class="ntv-field-group ntv-mt-2">
						<label><?php esc_html_e( 'کد SVG اختصاصی (اختیاری):', 'nativly' ); ?></label>
						<textarea name="ntv_settings[menu_items][<?php echo esc_attr( $idx ); ?>][custom_svg]" class="large-text ntv-item-svg-textarea" rows="2" placeholder="<svg>...</svg>"><?php echo esc_textarea( $custom_svg ); ?></textarea>
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
