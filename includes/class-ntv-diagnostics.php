<?php
/**
 * System Diagnostics, Cache Compatibility & Environment Health Checks
 *
 * @package Nativly
 * @author  Arsalan Jabarzare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class NTV_Diagnostics
 *
 * Provides real-time compatibility checks, automated LiteSpeed/WP Rocket cache exclusions,
 * and detailed system health reporting for PWA and glassmorphic navbar operations.
 */
class NTV_Diagnostics {

	/**
	 * Singleton instance.
	 *
	 * @var NTV_Diagnostics|null
	 */
	private static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @return NTV_Diagnostics
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
		$this->init_cache_exclusions();
	}

	/**
	 * Register programmatic exclusions for third-party caching and optimization plugins.
	 *
	 * Prevents LiteSpeed, WP Rocket, and Autoptimize from delaying, minifying, or breaking
	 * the instant rendering of Nativly navbar scripts, inline CSS, and PWA Service Workers.
	 *
	 * @return void
	 */
	private function init_cache_exclusions() {
		// LiteSpeed Cache: JS Delay, Defer, and Optimize Exclusions.
		add_filter( 'litespeed_optm_js_delay_exc', array( $this, 'exclude_litespeed_js' ) );
		add_filter( 'litespeed_optm_js_defer_exc', array( $this, 'exclude_litespeed_js' ) );
		add_filter( 'litespeed_optimize_js_excludes', array( $this, 'exclude_litespeed_js' ) );

		// LiteSpeed Cache: CSS and Unique CSS (UCSS) Exclusions.
		add_filter( 'litespeed_optm_css_exc', array( $this, 'exclude_litespeed_css' ) );
		add_filter( 'litespeed_optm_ucss_exc', array( $this, 'exclude_litespeed_ucss' ) );

		// LiteSpeed Cache: Guest Mode JS Optimization Exclusion.
		add_filter( 'litespeed_optm_gm_js_exc', array( $this, 'exclude_litespeed_js' ) );

		// WP Rocket: JS Defer & Delay Exclusions.
		add_filter( 'rocket_exclude_js', array( $this, 'exclude_rocket_js' ) );
		add_filter( 'rocket_cache_reject_uri', array( $this, 'exclude_rocket_uris' ) );

		// Autoptimize: JS Exclusions.
		add_filter( 'autoptimize_filter_js_exclude', array( $this, 'exclude_autoptimize_js' ) );

		// Perfmatters: RUCSS & JS Delay/Defer Exclusions.
		add_filter( 'perfmatters_rucss_excluded_stylesheets', array( $this, 'exclude_perfmatters_css' ) );
		add_filter( 'perfmatters_rucss_excluded_selectors', array( $this, 'exclude_perfmatters_selectors' ) );
		add_filter( 'perfmatters_delay_js_exclusions', array( $this, 'exclude_perfmatters_js' ) );
		add_filter( 'perfmatters_defer_js_exclusions', array( $this, 'exclude_perfmatters_js' ) );
	}

	/**
	 * Exclude Nativly scripts and handlers from LiteSpeed optimization.
	 *
	 * @param array<int,string>|mixed $excludes Existing exclusions.
	 * @return array<int,string> Modified exclusions.
	 */
	public function exclude_litespeed_js( $excludes ) {
		$excludes = is_array( $excludes ) ? $excludes : array();
		$nativly_items = array(
			'ntv-frontend',
			'ntv-install-prompt',
			'ntvFrontendData',
			'ntv_sw',
			'ntv-sw.js',
			'ntv-manifest.json',
			'ntv-critical-inline-css',
		);
		return array_values( array_unique( array_merge( $excludes, $nativly_items ) ) );
	}

	/**
	 * Exclude Nativly stylesheets and critical inline CSS from LiteSpeed CSS combine/delay.
	 *
	 * @param array<int,string>|mixed $excludes Existing exclusions.
	 * @return array<int,string> Modified exclusions.
	 */
	public function exclude_litespeed_css( $excludes ) {
		$excludes = is_array( $excludes ) ? $excludes : array();
		$nativly_items = array(
			'ntv-frontend',
			'ntv-critical-inline-css',
			'assets/css/ntv-frontend.css',
		);
		return array_values( array_unique( array_merge( $excludes, $nativly_items ) ) );
	}

	/**
	 * Exclude Nativly selectors from LiteSpeed UCSS (Unique CSS generation).
	 *
	 * @param array<int,string>|mixed $excludes Existing exclusions.
	 * @return array<int,string> Modified exclusions.
	 */
	public function exclude_litespeed_ucss( $excludes ) {
		$excludes = is_array( $excludes ) ? $excludes : array();
		$nativly_selectors = array(
			'.ntv-glass-nav-container',
			'.ntv-glass-nav',
			'.ntv-nav-item',
			'.ntv-bubble-popover',
			'.ntv-bubble-grid',
			'.ntv-bubble-item',
			'#ntv-navbar-root',
		);
		return array_values( array_unique( array_merge( $excludes, $nativly_selectors ) ) );
	}

	/**
	 * Exclude Nativly JS files from WP Rocket delay / defer.
	 *
	 * @param array<int,string>|mixed $excludes Existing exclusions.
	 * @return array<int,string> Modified exclusions.
	 */
	public function exclude_rocket_js( $excludes ) {
		$excludes = is_array( $excludes ) ? $excludes : array();
		$excludes[] = 'assets/js/ntv-frontend.js';
		$excludes[] = 'assets/js/ntv-install-prompt.js';
		return array_values( array_unique( $excludes ) );
	}

	/**
	 * Exclude Nativly PWA virtual endpoints from WP Rocket caching.
	 *
	 * @param array<int,string>|mixed $uris Excluded URIs.
	 * @return array<int,string> Modified URIs.
	 */
	public function exclude_rocket_uris( $uris ) {
		$uris = is_array( $uris ) ? $uris : array();
		$uris[] = '/ntv-sw.js';
		$uris[] = '/ntv-manifest.json';
		return array_values( array_unique( $uris ) );
	}

	/**
	 * Exclude Nativly JS from Autoptimize aggregation.
	 *
	 * @param string $exclude_string Comma-separated list.
	 * @return string Modified list.
	 */
	public function exclude_autoptimize_js( $exclude_string ) {
		return $exclude_string . ', ntv-frontend.js, ntv-install-prompt.js';
	}

	/**
	 * Exclude Nativly stylesheets from Perfmatters Remove Unused CSS (RUCSS).
	 *
	 * @param array<int,string>|mixed $excludes Existing exclusions.
	 * @return array<int,string> Modified exclusions.
	 */
	public function exclude_perfmatters_css( $excludes ) {
		$excludes   = is_array( $excludes ) ? $excludes : array();
		$excludes[] = 'ntv-frontend.css';
		$excludes[] = 'assets/css/ntv-frontend.css';
		return array_values( array_unique( $excludes ) );
	}

	/**
	 * Exclude Nativly selectors from Perfmatters RUCSS removal.
	 *
	 * @param array<int,string>|mixed $selectors Existing selectors.
	 * @return array<int,string> Modified selectors.
	 */
	public function exclude_perfmatters_selectors( $selectors ) {
		$selectors = is_array( $selectors ) ? $selectors : array();
		$nativly_selectors = array(
			'.ntv-glass-nav-container',
			'#ntv-glass-navbar',
			'.ntv-nav-list',
			'.ntv-nav-item',
			'.ntv-glass-bubble-popover',
			'.ntv-bubble-popover',
			'.ntv-cart-badge',
			'#ntv-navbar-root',
		);
		return array_values( array_unique( array_merge( $selectors, $nativly_selectors ) ) );
	}

	/**
	 * Exclude Nativly scripts from Perfmatters Delay & Defer JS.
	 *
	 * @param array<int,string>|mixed $excludes Existing exclusions.
	 * @return array<int,string> Modified exclusions.
	 */
	public function exclude_perfmatters_js( $excludes ) {
		$excludes = is_array( $excludes ) ? $excludes : array();
		$items    = array(
			'ntv-frontend.js',
			'ntv-install-prompt.js',
			'ntvFrontendData',
		);
		return array_values( array_unique( array_merge( $excludes, $items ) ) );
	}

	/**
	 * Run comprehensive environment health checks.
	 *
	 * @return array<string,array<string,string>> Diagnostic results keyed by slug.
	 */
	public static function get_diagnostics_data() {
		$results = array();

		// 1. SSL / HTTPS Security.
		$is_ssl = is_ssl();
		$results['ssl'] = array(
			'status'  => $is_ssl ? 'ok' : 'error',
			'title'   => __( 'پروتکل امنیتی SSL / HTTPS', 'nativly' ),
			'message' => $is_ssl
				? __( 'گواهی امنیتی SSL در سایت شما فعال و معتبر است.', 'nativly' )
				: __( 'سایت از پروتکل ناامن HTTP استفاده می‌کند. قابلیت‌های PWA و Service Worker طبق استانداردهای وب به HTTPS نیاز دارند.', 'nativly' ),
			'detail'  => $is_ssl ? __( 'فعال و ایمن', 'nativly' ) : __( 'خطای بحرانی: فاقد SSL', 'nativly' ),
			'help'    => __( 'سرویس‌ورکرها به دلایل امنیتی فقط روی دامنه دارای HTTPS (یا localhost) رجیستر می‌شوند. لطفاً از فعال بودن SSL روی دامنه خود اطمینان حاصل کنید.', 'nativly' ),
		);

		// 2. PWA Rewrite Endpoints.
		$rules        = get_option( 'rewrite_rules', array() );
		$has_manifest = is_array( $rules ) && ( isset( $rules['^ntv-manifest\.json$'] ) || isset( $rules['ntv-manifest\.json$'] ) );
		$has_sw       = is_array( $rules ) && ( isset( $rules['^ntv-sw\.js$'] ) || isset( $rules['ntv-sw\.js$'] ) );
		$endpoints_ok = ( $has_manifest && $has_sw );

		$results['endpoints'] = array(
			'status'  => $endpoints_ok ? 'ok' : 'warning',
			'title'   => __( 'مسیرهای بازنویسی PWA (Manifest & Service Worker)', 'nativly' ),
			'message' => $endpoints_ok
				? __( 'مسیرهای بازنویسی وردپرس برای مانیفست و سرویس‌ورکر به درستی ثبت شده‌اند.', 'nativly' )
				: __( 'مسیرهای بازنویسی شناسایی نشدند. ممکن است نیاز باشد یک‌بار پیوندهای یکتا را ذخیره کنید.', 'nativly' ),
			'detail'  => $endpoints_ok ? __( 'مسیرها آماده', 'nativly' ) : __( 'نیاز به بازنشانی پیوند یکتا', 'nativly' ),
			'help'    => __( 'فایل‌های مانیفست و سرویس‌ورکر به صورت مجازی در آدرس‌های /ntv-manifest.json و /ntv-sw.js در دسترس هستند.', 'nativly' ),
		);

		// 3. Image Library (GD / Imagick).
		$has_gd_or_imagick = wp_image_editor_supports( array( 'mime_type' => 'image/png' ) );
		$results['image_lib'] = array(
			'status'  => $has_gd_or_imagick ? 'ok' : 'warning',
			'title'   => __( 'کتابخانه پردازش تصویر سرور (GD / Imagick)', 'nativly' ),
			'message' => $has_gd_or_imagick
				? __( 'موتور ویرایشگر تصویر فعال است و تولید خودکار آیکون‌های اپلیکیشن پشتیبانی می‌شود.', 'nativly' )
				: __( 'هیچ‌یک از ماژول‌های GD یا Imagick روی سرور PHP فعال نیست. آیکون‌های سفارشی ممکن است ریسایز نشوند.', 'nativly' ),
			'detail'  => $has_gd_or_imagick ? __( 'پشتیبانی کامل', 'nativly' ) : __( 'هشدار: ماژول ناموجود', 'nativly' ),
			'help'    => __( 'برای تغییر خودکار سایز آیکون ۵۱۲×۵۱۲ به ۱۹۲×۱۹۲ و ۱۸۰×۱۸۰، ماژول PHP GD یا Imagick در هاست پیشنهاد می‌شود.', 'nativly' ),
		);

		// 4. PWA Conflicts Detector.
		$active_plugins    = (array) get_option( 'active_plugins', array() );
		$potential_conflicts = array(
			'super-progressive-web-apps/superpwa.php' => 'Super Progressive Web Apps',
			'pwa-for-wp/pwa-for-wp.php'               => 'PWA for WP & AMP',
			'pwa/pwa.php'                             => 'WordPress PWA',
			'onesignal-free-web-push-notifications/onesignal.php' => 'OneSignal Push Notifications',
		);
		$detected_conflicts = array();
		foreach ( $potential_conflicts as $plugin_file => $plugin_name ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				$detected_conflicts[] = $plugin_name;
			}
		}

		$has_conflicts = ! empty( $detected_conflicts );
		$results['conflicts'] = array(
			'status'  => $has_conflicts ? 'warning' : 'ok',
			'title'   => __( 'تداخل با سایر افزونه‌های PWA', 'nativly' ),
			'message' => $has_conflicts
				? sprintf(
					/* translators: %s: Comma-separated list of conflicting plugins */
					__( 'افزونه‌های زیر ممکن است Service Worker موازی اجرا کنند: %s', 'nativly' ),
					implode( ', ', $detected_conflicts )
				)
				: __( 'هیچ تداخلی با سایر افزونه‌های Service Worker یا PWA شناسایی نشد.', 'nativly' ),
			'detail'  => $has_conflicts ? __( 'تداخل بالقوه', 'nativly' ) : __( 'بدون تداخل', 'nativly' ),
			'help'    => __( 'اجرای هم‌زمان دو Service Worker متفاوت ممکن است باعث اختلال در کش یا نصب اپلیکیشن شود.', 'nativly' ),
		);

		// 5. WooCommerce Detection.
		$has_wc = class_exists( 'WooCommerce' );
		$results['woocommerce'] = array(
			'status'  => 'ok',
			'title'   => __( 'فروشگاه‌ساز ووکامرس (WooCommerce)', 'nativly' ),
			'message' => $has_wc
				? __( 'ووکامرس فعال است. ویژگی‌های هماهنگ با سبد خرید و نشانگر تعداد اقلام به صورت زنده کار می‌کنند.', 'nativly' )
				: __( 'ووکامرس فعال نیست. قابلیت‌های عمومی نوبار و PWA برای سایت‌های وبلاگی و شرکتی فعال هستند.', 'nativly' ),
			'detail'  => $has_wc ? __( 'فعال و هماهنگ', 'nativly' ) : __( 'اختیاری - غیرفعال', 'nativly' ),
			'help'    => __( 'ناتیولی کاملاً با ووکامرس هماهنگ است و نشانگر تعداد آیتم‌های سبد خرید را روی آیکون نوبار بدون رفرش صفحه نشان می‌دهد.', 'nativly' ),
		);

		// 6. LiteSpeed & Cache Compatibility.
		$has_litespeed = defined( 'LSCWP_V' );
		$has_rocket    = defined( 'WP_ROCKET_VERSION' );
		$cache_name    = $has_litespeed ? 'LiteSpeed Cache' : ( $has_rocket ? 'WP Rocket' : __( 'کش سرور / افزونه متفرقه', 'nativly' ) );

		$results['cache'] = array(
			'status'  => 'ok',
			'title'   => sprintf(
				/* translators: %s: Cache system name */
				__( 'سازگاری کش (%s)', 'nativly' ),
				$cache_name
			),
			'message' => __( 'قوانین خودکار استثنای اسکریپت‌ها، استایل‌های بحرانی درون‌خطی و آدرس‌های Service Worker اعمال شده‌اند.', 'nativly' ),
			'detail'  => __( 'استثنائات خودکار فعال', 'nativly' ),
			'help'    => __( 'ناتیولی به طور خودکار هوک‌های پاک‌سازی کش و فیلترهای تاخیر اسکریپت را به افزونه‌های کش معروف تزریق می‌کند تا رندر نوبار آنی و بدون تاخیر باشد.', 'nativly' ),
		);

		return $results;
	}

	/**
	 * Build plain text system diagnostic report for support copy/paste.
	 *
	 * @return string Formatted plain text report.
	 */
	public static function generate_system_report() {
		global $wp_version;

		$theme = wp_get_theme();
		$report  = "### Nativly System & Diagnostics Report ###\n";
		$report .= "Generated: " . gmdate( 'Y-m-d H:i:s' ) . " UTC\n";
		$report .= "Plugin Version: " . NTV_VERSION . "\n";
		$report .= "WordPress Version: " . $wp_version . "\n";
		$report .= "PHP Version: " . PHP_VERSION . "\n";
		$report .= "Web Server: " . ( isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown' ) . "\n";
		$report .= "Site URL: " . home_url( '/' ) . "\n";
		$report .= "SSL Active: " . ( is_ssl() ? 'Yes' : 'No' ) . "\n";
		$report .= "Active Theme: " . $theme->get( 'Name' ) . " v" . $theme->get( 'Version' ) . "\n";
		$report .= "WooCommerce Active: " . ( class_exists( 'WooCommerce' ) ? 'Yes' : 'No' ) . "\n";
		$report .= "LiteSpeed Active: " . ( defined( 'LSCWP_V' ) ? 'Yes (v' . LSCWP_V . ')' : 'No' ) . "\n";
		$report .= "WP Rocket Active: " . ( defined( 'WP_ROCKET_VERSION' ) ? 'Yes (v' . WP_ROCKET_VERSION . ')' : 'No' ) . "\n";
		$report .= "Cache Version: ntv-v" . get_option( 'ntv_cache_version', 1 ) . "\n";

		$settings = NTV_Core::get_settings();
		$report .= "\n--- Configuration Summary ---\n";
		$report .= "Menu Source: " . ( $settings['menu_source'] ?? 'custom' ) . "\n";
		$report .= "Navbar Width Mode: " . ( $settings['navbar_width_mode'] ?? 'auto' ) . "\n";
		$report .= "Desktop Position: " . ( $settings['desktop_position'] ?? 'hidden' ) . "\n";
		$report .= "Enable PWA: " . ( ! empty( $settings['enable_pwa'] ) ? 'Yes' : 'No' ) . "\n";
		$report .= "Enable Install Prompt: " . ( ! empty( $settings['enable_install_prompt'] ) ? 'Yes' : 'No' ) . "\n";
		$report .= "Hide Theme Header: " . ( ! empty( $settings['hide_theme_header'] ) ? 'Yes' : 'No' ) . "\n";
		$report .= "Hide Cart for Guests: " . ( ! empty( $settings['hide_cart_for_guests'] ) ? 'Yes' : 'No' ) . "\n";

		return $report;
	}

	/**
	 * Render Tab 6 content in the admin settings screen.
	 *
	 * Uses polished .ntv-diag-card markup with green/blue/amber badges, Dashicons,
	 * collapsible help boxes, and one-click debug report copying.
	 *
	 * @return void
	 */
	public static function render_tab_content() {
		$checks = self::get_diagnostics_data();
		$report = self::generate_system_report();
		?>
		<div class="ntv-card">
			<h3 class="ntv-card-title"><?php esc_html_e( 'وضعیت سلامت و سازگاری سیستم (Health Checks)', 'nativly' ); ?></h3>
			<p class="ntv-card-subtitle"><?php esc_html_e( 'بررسی خودکار وضعیت امنیت، کش، سازگاری افزونه‌ها و پیش‌نیازهای PWA در هاست و سایت شما:', 'nativly' ); ?></p>

			<div class="ntv-diag-grid">
				<?php foreach ( $checks as $slug => $data ) :
					$status_class = 'ntv-status-' . sanitize_html_class( $data['status'] );
					$icon_name    = 'yes-alt';
					if ( 'warning' === $data['status'] ) {
						$icon_name = 'warning';
					} elseif ( 'error' === $data['status'] ) {
						$icon_name = 'dismiss';
					}
					?>
					<div class="ntv-diag-card <?php echo esc_attr( $status_class ); ?>">
						<div class="ntv-diag-icon">
							<span class="dashicons dashicons-<?php echo esc_attr( $icon_name ); ?>"></span>
						</div>
						<div class="ntv-diag-content">
							<div class="ntv-diag-header">
								<h4 class="ntv-diag-title"><?php echo esc_html( $data['title'] ); ?></h4>
								<span class="ntv-diag-badge"><?php echo esc_html( $data['detail'] ); ?></span>
							</div>
							<p class="ntv-diag-message"><?php echo esc_html( $data['message'] ); ?></p>
							<?php if ( ! empty( $data['help'] ) ) : ?>
								<div class="ntv-diag-help-box">
									<span class="dashicons dashicons-info"></span>
									<span><?php echo wp_kses_post( $data['help'] ); ?></span>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Direct Endpoints Test Box -->
		<div class="ntv-card ntv-mt-4">
			<h3 class="ntv-card-title"><?php esc_html_e( 'آدرس‌های مجازی Service Worker و Web Manifest', 'nativly' ); ?></h3>
			<p class="ntv-card-subtitle"><?php esc_html_e( 'می‌توانید برای اطمینان از دسترسی مستقیم مرورگر و عدم تداخل کش سرور، پیوندهای زیر را باز کنید:', 'nativly' ); ?></p>
			<div class="ntv-grid-2">
				<div class="ntv-endpoint-item">
					<strong>Manifest JSON:</strong>
					<a href="<?php echo esc_url( home_url( '/ntv-manifest.json' ) ); ?>" target="_blank" rel="noopener noreferrer" class="ntv-endpoint-link">
						<code>/ntv-manifest.json</code> <span class="dashicons dashicons-external"></span>
					</a>
				</div>
				<div class="ntv-endpoint-item">
					<strong>Service Worker JS:</strong>
					<a href="<?php echo esc_url( home_url( '/ntv-sw.js' ) ); ?>" target="_blank" rel="noopener noreferrer" class="ntv-endpoint-link">
						<code>/ntv-sw.js</code> <span class="dashicons dashicons-external"></span>
					</a>
				</div>
			</div>
		</div>

		<!-- System Diagnostics Report Box -->
		<div class="ntv-card ntv-mt-4">
			<h3 class="ntv-card-title"><?php esc_html_e( 'گزارش سیستم و پشتیبانی (System Report)', 'nativly' ); ?></h3>
			<p class="ntv-card-subtitle"><?php esc_html_e( 'در صورت برخورد با هرگونه مشکل، گزارش زیر را کپی کرده و برای تیم پشتیبانی ارسال فرمایید:', 'nativly' ); ?></p>
			<div class="ntv-report-wrap">
				<pre id="ntv-diagnostics-report" class="ntv-system-report-box"><?php echo esc_html( $report ); ?></pre>
				<button type="button" id="ntv-copy-diagnostics-btn" class="button button-secondary ntv-mt-2">
					<span class="dashicons dashicons-clipboard"></span>
					<?php esc_html_e( 'کپی گزارش در کلیپ‌بورد', 'nativly' ); ?>
				</button>
			</div>
		</div>
		<?php
	}
}
