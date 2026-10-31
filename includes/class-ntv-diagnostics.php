<?php
/**
 * Nativly Diagnostics & Cache Plugin Compatibility Engine
 *
 * @package Nativly
 * @author  Arsalan Jabarzare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class NTV_Diagnostics
 *
 * Handles PWA health checks, server environment checks, conflicting plugin detection,
 * beginner-friendly Persian explanations, and automatic cache exclusion filters
 * for WP Rocket, LiteSpeed, Autoptimize, and W3TC.
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
		$this->register_cache_exclusions();
	}

	/**
	 * Hook automatic exclusions into top WordPress caching and optimization plugins.
	 *
	 * Ensures /ntv-sw.js, /ntv-manifest.json, and ntv-frontend assets are never cached, minified, or deferred.
	 *
	 * @return void
	 */
	public function register_cache_exclusions() {
		// 1. WP Rocket exclusions.
		add_filter( 'rocket_exclude_js', array( $this, 'exclude_from_js_minify' ) );
		add_filter( 'rocket_cache_reject_uri', array( $this, 'exclude_from_page_cache' ) );
		add_filter( 'rocket_delay_js_exclusions', array( $this, 'exclude_from_delay_js' ) );

		// 2. LiteSpeed Cache exclusions.
		add_filter( 'litespeed_optimize_js_excludes', array( $this, 'exclude_from_js_minify' ) );
		add_filter( 'litespeed_ucss_exc', array( $this, 'exclude_from_ucss' ) );

		// 3. Autoptimize exclusions.
		add_filter( 'autoptimize_filter_js_exclude', array( $this, 'exclude_from_autoptimize' ) );

		// 4. W3 Total Cache exclusions.
		add_filter( 'w3tc_minify_js_enable', array( $this, 'w3tc_maybe_disable_minify' ), 10, 2 );
	}

	/**
	 * Exclude scripts from minification / deferral.
	 *
	 * @param array|string $excludes List of exclusions.
	 * @return array|string Filtered exclusions.
	 */
	public function exclude_from_js_minify( $excludes ) {
		$items = array( 'ntv-sw.js', 'ntv-manifest.json', 'ntv-frontend.js', 'ntv-install-prompt.js' );
		if ( is_array( $excludes ) ) {
			return array_unique( array_merge( $excludes, $items ) );
		}
		return $excludes;
	}

	/**
	 * Exclude Nativly scripts from WP Rocket Delay JavaScript execution.
	 *
	 * Ensures the bottom navbar and interactive gestures are initialized instantly without
	 * waiting for user scroll/interaction.
	 *
	 * @param array $exclusions Array of delay JS exclusions.
	 * @return array Filtered exclusions.
	 */
	public function exclude_from_delay_js( $exclusions ) {
		if ( ! is_array( $exclusions ) ) {
			$exclusions = array();
		}
		$items = array( 'ntv-frontend', 'ntv-install-prompt', 'ntvFrontendData' );
		return array_unique( array_merge( $exclusions, $items ) );
	}

	/**
	 * Exclude URIs from full page caching.
	 *
	 * @param array $urls Excluded URLs.
	 * @return array Filtered URLs.
	 */
	public function exclude_from_page_cache( $urls ) {
		if ( ! is_array( $urls ) ) {
			$urls = array();
		}
		$urls[] = '(.*)/ntv-sw.js';
		$urls[] = '(.*)/ntv-manifest.json';
		return array_unique( $urls );
	}

	/**
	 * Exclude CSS from LiteSpeed UCSS.
	 *
	 * @param array $excludes List.
	 * @return array Filtered list.
	 */
	public function exclude_from_ucss( $excludes ) {
		if ( ! is_array( $excludes ) ) {
			$excludes = array();
		}
		$excludes[] = 'ntv-frontend.css';
		$excludes[] = '#ntv-navbar-root';
		return array_unique( $excludes );
	}

	/**
	 * Exclude scripts from Autoptimize.
	 *
	 * @param string $exclude_string Comma separated exclusions.
	 * @return string Filtered exclusions.
	 */
	public function exclude_from_autoptimize( $exclude_string ) {
		$items = 'ntv-sw.js, ntv-manifest.json, ntv-frontend.js, ntv-install-prompt.js';
		return $exclude_string ? $exclude_string . ', ' . $items : $items;
	}

	/**
	 * Check if W3TC should minify.
	 *
	 * @param bool   $enable Minify status.
	 * @param string $file   File name.
	 * @return bool
	 */
	public function w3tc_maybe_disable_minify( $enable, $file = '' ) {
		if ( false !== strpos( $file, 'ntv-' ) ) {
			return false;
		}
		return $enable;
	}

	/**
	 * Run all diagnostic checks and return structured test reports with Persian explanations.
	 *
	 * @return array<string,array{status: string, title: string, message: string, detail: string, help: string}>
	 */
	public static function run_diagnostics() {
		$checks = array();

		// Check 1: HTTPS / SSL.
		$is_ssl        = is_ssl();
		$checks['ssl'] = array(
			'status'  => $is_ssl ? 'success' : 'error',
			'title'   => __( 'وضعیت گواهی امنیتی HTTPS', 'nativly' ),
			'message' => $is_ssl ? __( 'گواهی امنیتی فعال است.', 'nativly' ) : __( 'گواهی SSL فعال نیست! Service Worker برای فعال‌سازی PWA الزاماً به HTTPS نیاز دارد.', 'nativly' ),
			'detail'  => $is_ssl ? 'HTTPS Enabled' : 'HTTP Only (Service Worker will fail on client devices)',
			'help'    => __( 'گواهی SSL اتصال وب‌سایت شما را با پروتکل امن HTTPS رمزنگاری می‌کند. استانداردهای مرورگرهای مدرن (گوگل کروم و سافاری) ثبت Service Worker و عملکرد وب‌اپلیکیشن (PWA) را در بستر غیرامن HTTP مسدود می‌کنند. اگر علامت قرمز دیدید، گواهی امنیتی رایگان Let\'s Encrypt را از کنترل‌پنل هاست خود فعال نمایید.', 'nativly' ),
		);

		// Check 2: GD or Imagick library support for PNG.
		$has_png_support        = wp_image_editor_supports( array( 'mime_type' => 'image/png' ) );
		$checks['image_editor'] = array(
			'status'  => $has_png_support ? 'success' : 'warning',
			'title'   => __( 'پشتیبانی از پردازش تصویر (GD / Imagick)', 'nativly' ),
			'message' => $has_png_support ? __( 'محیط هاست قادر به تولید خودکار آیکون‌های PWA است.', 'nativly' ) : __( 'کتابخانه GD یا Imagick روی سرور یافت نشد. تولید خودکار اندازه آیکون‌ها ممکن است با خطا مواجه شود.', 'nativly' ),
			'detail'  => $has_png_support ? 'PHP Image Editor Ready' : 'GD / Imagick Missing for image/png',
			'help'    => __( 'کتابخانه‌های گرافیکی PHP مانند GD یا ImageMagick وظیفه فشرده‌سازی و تغییر سایز آیکون اپلیکیشن به ابعاد ۵۱۲×۵۱۲ پیکسل را بر عهده دارند. در صورتی که هاست شما فاقد این اکستنشن باشد، کافیست از پشتیبانی هاست خود بخواهید اکستنشن php-gd یا php-imagick را برای نسخه PHP شما فعال کند.', 'nativly' ),
		);

		// Check 3: Manifest & Service Worker Endpoints.
		$manifest_url        = home_url( '/ntv-manifest.json' );
		$sw_url              = home_url( '/ntv-sw.js' );
		$checks['endpoints'] = array(
			'status'  => 'info',
			'title'   => __( 'نقاط انتهایی PWA (Endpoints)', 'nativly' ),
			'message' => __( 'آدرس مانیفست و سرویس‌ورکر به درستی تعریف شده‌اند.', 'nativly' ),
			'detail'  => sprintf( '<a href="%s" target="_blank" rel="noopener">Manifest.json</a> | <a href="%s" target="_blank" rel="noopener">Service-Worker.js</a>', esc_url( $manifest_url ), esc_url( $sw_url ) ),
			'help'    => __( 'فایل‌های manifest.json و ntv-sw.js هسته اصلی اپلیکیشن تحت وب شما هستند. فایل مانیفست مشخصات ظاهری، رنگ و نام اپلیکیشن را به سیستم‌عامل موبایل معرفی می‌کند و سرویس‌ورکر فایل‌ها را در حافظه گوشی ذخیره کرده و کارکرد آفلاین را ممکن می‌سازد. با کلیک روی هر لینک، مطمئن شوید محتوای فایل‌ها بدون خطای ۴۰۴ باز می‌شود.', 'nativly' ),
		);

		// Check 4: Conflicting PWA Plugins.
		$conflicting_plugins = array(
			'super-progressive-web-apps/superpwa.php' => 'Super Progressive Web Apps',
			'pwa-for-wp/pwa-for-wp.php'               => 'PWA for WP & AMP',
			'pwa/pwa.php'                             => 'WordPress Core PWA Feature Plugin',
			'onesignal-free-web-push-notifications/onesignal.php' => 'OneSignal Web Push',
		);

		$active_conflicts = array();
		if ( ! function_exists( 'is_plugin_active' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( function_exists( 'is_plugin_active' ) ) {
			foreach ( $conflicting_plugins as $plugin_file => $plugin_name ) {
				if ( is_plugin_active( $plugin_file ) ) {
					$active_conflicts[] = $plugin_name;
				}
			}
		}

		if ( ! empty( $active_conflicts ) ) {
			$checks['conflicts'] = array(
				'status'  => 'warning',
				'title'   => __( 'تداخل افزونه‌های PWA موازی', 'nativly' ),
				'message' => sprintf(
					/* translators: %s: Comma-separated list of conflicting plugins */
					__( 'افزونه‌های زیر ممکن است Service Worker موازی ثبت کرده و با Nativly تداخل ایجاد کنند: %s', 'nativly' ),
					implode( '، ', array_map( 'esc_html', $active_conflicts ) )
				),
				'detail'  => __( 'پیشنهاد می‌شود برای جلوگیری از اختلال در کش، سایر افزونه‌های PWA را غیرفعال کنید.', 'nativly' ),
				'help'    => __( 'فعال بودن چند افزونه PWA به طور همزمان باعث ثبت چند Service Worker موازی در مرورگر مخاطب می‌شود. این مسئله به رفتارهای غیرمنتظره، کش شدن فایل‌های اشتباه و عدم نمایش نوبار منجر خواهد شد. پیشنهاد می‌کنیم فقط از قابلیت‌های نیتیولی استفاده کنید.', 'nativly' ),
			);
		} else {
			$checks['conflicts'] = array(
				'status'  => 'success',
				'title'   => __( 'بررسی تداخل افزونه‌ها', 'nativly' ),
				'message' => __( 'هیچ افزونه PWA موازی فعالی شناسایی نشد.', 'nativly' ),
				'detail'  => 'No Conflicting PWA Plugins Detected',
				'help'    => __( 'هیچ افزونه متداخلی در سایت شما فعال نیست و نیتیولی به صورت انحصاری مدیریت تجربه بومی موبایل و PWA را در دست دارد.', 'nativly' ),
			);
		}

		// Check 5: Active WooCommerce.
		$has_wc                = class_exists( 'WooCommerce' );
		$checks['woocommerce'] = array(
			'status'  => 'info',
			'title'   => __( 'یکپارچگی ووکامرس', 'nativly' ),
			'message' => $has_wc ? __( 'ووکامرس فعال است و شمارنده سبد خرید و قوانین انحصاری فعال هستند.', 'nativly' ) : __( 'ووکامرس غیرفعال است (اختیاری).', 'nativly' ),
			'detail'  => $has_wc ? 'WooCommerce Detected & Integrated' : 'WooCommerce Not Installed',
			'help'    => __( 'نیتیولی به طور هوشمند سبد خرید، صفحه حساب کاربری و صفحات تسویه‌حساب ووکامرس را تشخیص داده و نشان شمارنده سبد خرید را در لحظه و بدون نیاز به بارگذاری مجدد صفحه به‌روزرسانی می‌کند.', 'nativly' ),
		);

		// Check 6: Cache Engine Compatibility.
		$checks['cache_exclusions'] = array(
			'status'  => 'success',
			'title'   => __( 'سازگاری با افزونه‌های کش و بهینه‌سازی', 'nativly' ),
			'message' => __( 'فیلترهای استثنای خودکار برای WP Rocket، LiteSpeed Cache، Autoptimize و W3TC فعال هستند.', 'nativly' ),
			'detail'  => 'Zero-Delay Optimization Bypass Active',
			'help'    => __( 'اسکریپت‌ها و فایل‌های ضروری نیتیولی به صورت خودکار از قابلیت‌های تاخیر اجرای جاوااسکریپت (Delay JS) و استخراج استایل بحرانی (Critical CSS) مستثنی شده‌اند تا نوبار موبایل با سرعت و بدون تاخیر چند ثانیه‌ای بلافاصله در دسترس کاربر قرار گیرد.', 'nativly' ),
		);

		return $checks;
	}

	/**
	 * Render Diagnostics Tab HTML.
	 *
	 * @return void
	 */
	public static function render_tab_content() {
		$checks = self::run_diagnostics();
		?>
		<div class="ntv-diagnostics-wrap">
			<div class="ntv-card">
				<h3 class="ntv-card-title"><?php esc_html_e( 'وضعیت سلامت سیستم و نیازمندی‌های PWA', 'nativly' ); ?></h3>
				<p class="ntv-card-subtitle"><?php esc_html_e( 'بررسی شرایط سرور، گواهی امنیتی، نقاط دسترسی و سازگاری با کش‌کننده‌ها.', 'nativly' ); ?></p>

				<div class="ntv-checks-grid">
					<?php foreach ( $checks as $key => $check ) : ?>
						<div class="ntv-check-item ntv-status-<?php echo esc_attr( $check['status'] ); ?>">
							<div class="ntv-check-icon">
								<?php if ( 'success' === $check['status'] ) : ?>
									<span class="dashicons dashicons-yes-alt"></span>
								<?php elseif ( 'warning' === $check['status'] ) : ?>
									<span class="dashicons dashicons-warning"></span>
								<?php elseif ( 'error' === $check['status'] ) : ?>
									<span class="dashicons dashicons-dismiss"></span>
								<?php else : ?>
									<span class="dashicons dashicons-info"></span>
								<?php endif; ?>
							</div>
							<div class="ntv-check-content">
								<h4 class="ntv-check-title">
									<?php echo esc_html( $check['title'] ); ?>
									<?php if ( ! empty( $check['help'] ) ) : ?>
										<button type="button" class="ntv-help-trigger" aria-label="<?php esc_attr_e( 'راهنمای تشریحی', 'nativly' ); ?>" title="<?php esc_attr_e( 'مشاهده راهنما و راهکار', 'nativly' ); ?>">?</button>
									<?php endif; ?>
								</h4>
								<p class="ntv-check-msg"><?php echo esc_html( $check['message'] ); ?></p>
								<div class="ntv-check-detail"><?php echo wp_kses_post( $check['detail'] ); ?></div>
								<?php if ( ! empty( $check['help'] ) ) : ?>
									<div class="ntv-help-box" style="display:none;">
										<strong><?php esc_html_e( 'راهنمایی و راهکار تکمیلی:', 'nativly' ); ?></strong>
										<p><?php echo esc_html( $check['help'] ); ?></p>
									</div>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="ntv-card ntv-mt-4">
				<h3 class="ntv-card-title"><?php esc_html_e( 'تنظیمات پیشنهادی Cloudflare و CDN', 'nativly' ); ?></h3>
				<p class="ntv-card-subtitle"><?php esc_html_e( 'برای جلوگیری از کش شدن سرویس‌ورکر و مانیفست توسط شبکه توزیع محتوا (CDN)، قانون زیر را در Cloudflare Page Rules یا Cache Rules اضافه کنید:', 'nativly' ); ?></p>

				<div class="ntv-code-block">
					<code>
						URL: *<?php echo esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>/ntv-sw.js<br>
						Cache Level: Bypass<br>
						Edge Cache TTL: Bypass
					</code>
				</div>
			</div>
		</div>
		<?php
	}
}
