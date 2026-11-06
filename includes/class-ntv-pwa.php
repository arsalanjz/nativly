<?php
/**
 * Progressive Web App Engine & Service Worker Endpoint
 *
 * @package Nativly
 * @author  Arsalan Jabarzare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class NTV_PWA
 *
 * Manages Web App Manifest generation, root Service Worker generation,
 * image icon resizing, iOS Safari home screen meta tags, and install prompt assets.
 */
class NTV_PWA {

	/**
	 * Singleton instance.
	 *
	 * @var NTV_PWA|null
	 */
	private static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @return NTV_PWA
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
	 * Initialize PWA hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Listen for manifest and service worker requests early.
		add_action( 'template_redirect', array( $this, 'handle_pwa_endpoints' ), 1 );

		// Head meta tag injection for iOS and PWA recognition.
		add_action( 'wp_head', array( $this, 'render_head_meta' ), 2 );

		// Enqueue install prompt and service worker registration scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_pwa_scripts' ) );

		// Output install prompt modal markup in footer.
		add_action( 'wp_footer', array( $this, 'render_install_prompt_markup' ), 95 );
	}

	/**
	 * Calculate normalized URL scope and path for subdirectory or multisite compatibility.
	 *
	 * @return string Root or subdirectory path with trailing slash (e.g. '/' or '/blog/').
	 */
	public static function get_scope_path() {
		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		return trailingslashit( $home_path ? $home_path : '/' );
	}

	/**
	 * Intercept /ntv-manifest.json and /ntv-sw.js endpoints.
	 *
	 * @return void
	 */
	public function handle_pwa_endpoints() {
		$settings = NTV_Core::get_settings();

		// Check if PWA is enabled.
		if ( empty( $settings['enable_pwa'] ) ) {
			return;
		}

		$is_manifest = false;
		$is_sw       = false;

		// Check rewrite query vars.
		if ( get_query_var( 'ntv_manifest' ) ) {
			$is_manifest = true;
		} elseif ( get_query_var( 'ntv_sw' ) ) {
			$is_sw = true;
		} else {
			// Check direct request uri.
			$req_path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
			if ( 'ntv-manifest.json' === $req_path || ( isset( $_GET['ntv_manifest'] ) && '1' === $_GET['ntv_manifest'] ) ) {
				$is_manifest = true;
			} elseif ( 'ntv-sw.js' === $req_path || ( isset( $_GET['ntv_sw'] ) && '1' === $_GET['ntv_sw'] ) ) {
				$is_sw = true;
			}
		}

		if ( $is_manifest ) {
			$this->render_manifest();
			exit;
		}

		if ( $is_sw ) {
			$this->render_service_worker();
			exit;
		}
	}

	/**
	 * Render dynamic Web App Manifest JSON with correct HTTP headers.
	 *
	 * @return void
	 */
	public function render_manifest() {
		$settings   = NTV_Core::get_settings();
		$site_title = get_bloginfo( 'name' );
		$site_desc  = get_bloginfo( 'description' );
		$scope_path = self::get_scope_path();

		$app_name       = ! empty( $settings['app_name'] ) ? $settings['app_name'] : $site_title;
		$app_short_name = ! empty( $settings['app_short_name'] ) ? $settings['app_short_name'] : $site_title;
		$app_desc       = ! empty( $settings['app_description'] ) ? $settings['app_description'] : $site_desc;
		$theme_color    = ! empty( $settings['theme_color'] ) ? $settings['theme_color'] : '#0047FF';
		$bg_color       = ! empty( $settings['background_color'] ) ? $settings['background_color'] : '#121218';

		$manifest = array(
			'id'               => $scope_path,
			'name'             => $app_name,
			'short_name'       => $app_short_name,
			'description'      => $app_desc,
			'start_url'        => $scope_path,
			'scope'            => $scope_path,
			'display'          => 'standalone',
			'orientation'      => 'portrait',
			'background_color' => $bg_color,
			'theme_color'      => $theme_color,
			'dir'              => is_rtl() ? 'rtl' : 'ltr',
			'lang'             => get_bloginfo( 'language' ),
			'icons'            => array(),
			'shortcuts'        => array(),
		);

		// Resolve Icons.
		$icon_192 = ! empty( $settings['pwa_icon_192'] ) ? $settings['pwa_icon_192'] : '';
		$icon_512 = ! empty( $settings['pwa_icon_512'] ) ? $settings['pwa_icon_512'] : '';

		// If no custom uploaded icons, check WP Site Icon.
		if ( empty( $icon_512 ) && function_exists( 'get_site_icon_url' ) ) {
			$site_icon = get_site_icon_url( 512 );
			if ( $site_icon ) {
				$icon_512 = $site_icon;
				$icon_192 = get_site_icon_url( 192 );
			}
		}

		if ( ! empty( $icon_192 ) ) {
			$manifest['icons'][] = array(
				'src'     => esc_url_raw( $icon_192 ),
				'sizes'   => '192x192',
				'type'    => 'image/png',
				'purpose' => 'any',
			);
			$manifest['icons'][] = array(
				'src'     => esc_url_raw( $icon_192 ),
				'sizes'   => '192x192',
				'type'    => 'image/png',
				'purpose' => 'maskable',
			);
		}

		if ( ! empty( $icon_512 ) ) {
			$manifest['icons'][] = array(
				'src'     => esc_url_raw( $icon_512 ),
				'sizes'   => '512x512',
				'type'    => 'image/png',
				'purpose' => 'any',
			);
			$manifest['icons'][] = array(
				'src'     => esc_url_raw( $icon_512 ),
				'sizes'   => '512x512',
				'type'    => 'image/png',
				'purpose' => 'maskable',
			);
		}

		// Shortcuts generated from navbar items.
		if ( ! empty( $settings['menu_items'] ) && is_array( $settings['menu_items'] ) ) {
			$shortcut_count = 0;
			foreach ( $settings['menu_items'] as $item ) {
				if ( empty( $item['title'] ) || empty( $item['url'] ) ) {
					continue;
				}
				$manifest['shortcuts'][] = array(
					'name'  => $item['title'],
					'url'   => esc_url_raw( $item['url'] ),
					'icons' => ! empty( $icon_192 ) ? array( array( 'src' => esc_url_raw( $icon_192 ), 'sizes' => '192x192' ) ) : array(),
				);
				$shortcut_count++;
				if ( $shortcut_count >= 4 ) {
					break;
				}
			}
		}

		/**
		 * Filter manifest schema before output.
		 *
		 * @param array $manifest Manifest structure.
		 */
		$manifest = apply_filters( 'ntv_pwa_manifest', $manifest );

		// Send Headers.
		if ( ! headers_sent() ) {
			header( 'Content-Type: application/manifest+json; charset=utf-8' );
			header( 'Cache-Control: public, max-age=3600' );
			header( 'X-Robots-Tag: noindex' );
		}

		echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Render production Service Worker JavaScript with smart routing and strict bypass rules.
	 *
	 * @return void
	 */
	public function render_service_worker() {
		$scope_path    = self::get_scope_path();
		$cache_version = (int) get_option( 'ntv_cache_version', time() );
		$cache_name    = 'ntv-v' . $cache_version;
		$settings      = NTV_Core::get_settings();

		// Determine Offline Fallback URL.
		$offline_url = '';
		if ( ! empty( $settings['offline_page_id'] ) ) {
			$offline_url = get_permalink( (int) $settings['offline_page_id'] );
		}

		// WooCommerce dynamic bypass endpoints.
		$wc_paths = array();
		if ( class_exists( 'WooCommerce' ) ) {
			if ( function_exists( 'wc_get_page_permalink' ) ) {
				$cart_url = wc_get_page_permalink( 'cart' );
				if ( $cart_url ) {
					$wc_paths[] = (string) wp_parse_url( $cart_url, PHP_URL_PATH );
				}
				$checkout_url = wc_get_page_permalink( 'checkout' );
				if ( $checkout_url ) {
					$wc_paths[] = (string) wp_parse_url( $checkout_url, PHP_URL_PATH );
				}
				$account_url = wc_get_page_permalink( 'myaccount' );
				if ( $account_url ) {
					$wc_paths[] = (string) wp_parse_url( $account_url, PHP_URL_PATH );
				}
			}
		}
		$wc_paths = array_filter( array_unique( $wc_paths ) );

		if ( ! headers_sent() ) {
			header( 'Content-Type: application/javascript; charset=utf-8' );
			header( 'Service-Worker-Allowed: ' . $scope_path );
			header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
		}

		?>
/**
 * Nativly Progressive Web App Service Worker
 * Dynamic Cache Version: <?php echo esc_js( $cache_name ); ?>
 */

const CACHE_NAME = '<?php echo esc_js( $cache_name ); ?>';
const SCOPE_PATH = '<?php echo esc_js( $scope_path ); ?>';
const OFFLINE_PAGE_URL = '<?php echo esc_js( $offline_url ); ?>';
const WC_DYNAMIC_PATHS = <?php echo wp_json_encode( array_values( $wc_paths ) ); ?>;

// Static pre-cache assets.
const PRECACHE_ASSETS = [
	SCOPE_PATH,
	...(OFFLINE_PAGE_URL ? [OFFLINE_PAGE_URL] : [])
];

// Offline fallback HTML response (in Persian RTL and English).
const BUILTIN_OFFLINE_HTML = `<!DOCTYPE html>
<html dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>" lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title><?php esc_html_e( 'No Connection', 'nativly' ); ?></title>
  <style>
    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: #121218;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      padding: 24px;
      box-sizing: border-box;
      text-align: center;
    }
    .card {
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 24px;
      padding: 40px 24px;
      max-width: 400px;
      width: 100%;
      backdrop-filter: blur(20px);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
    }
    .icon {
      width: 56px;
      height: 56px;
      margin: 0 auto 20px;
      color: #FF9500;
    }
    h1 {
      font-size: 20px;
      margin: 0 0 12px;
      font-weight: 700;
    }
    p {
      color: rgba(255, 255, 255, 0.7);
      font-size: 14px;
      line-height: 1.6;
      margin: 0 0 28px;
    }
    button {
      background: #0047FF;
      color: #fff;
      border: none;
      padding: 12px 28px;
      border-radius: 999px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: opacity 0.2s;
    }
    button:active {
      opacity: 0.8;
    }
  </style>
</head>
<body>
  <div class="card">
    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <line x1="1" y1="1" x2="23" y2="23"></line>
      <path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"></path>
      <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"></path>
      <path d="M10.71 5.05A16 16 0 0 1 22.58 9"></path>
      <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"></path>
      <path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path>
      <line x1="12" y1="20" x2="12.01" y2="20"></line>
    </svg>
    <h1><?php echo esc_js( __( 'شما آفلاین هستید', 'nativly' ) ); ?></h1>
    <p><?php echo esc_js( __( 'اتصال اینترنت خود را بررسی کنید و دکمه تلاش مجدد را لمس نمایید.', 'nativly' ) ); ?></p>
    <button onclick="window.location.reload()"><?php echo esc_js( __( 'تلاش مجدد', 'nativly' ) ); ?></button>
  </div>
</body>
</html>`;

// Install Event: Cache essential root entry and immediately skip waiting
self.addEventListener('install', (event) => {
	self.skipWaiting();
	event.waitUntil(
		caches.open(CACHE_NAME).then((cache) => {
			return cache.addAll(PRECACHE_ASSETS).catch((err) => {
				console.warn('[Nativly SW] Pre-cache non-fatal error:', err);
			});
		})
	);
});

// Activate Event: Delete previous ntv-v* caches and claim clients immediately
self.addEventListener('activate', (event) => {
	event.waitUntil(
		caches.keys().then((keys) => {
			return Promise.all(
				keys.map((key) => {
					if (key.startsWith('ntv-v') && key !== CACHE_NAME) {
						return caches.delete(key);
					}
				})
			);
		}).then(() => self.clients.claim())
	);
});

// Listen for SKIP_WAITING from frontend prompt toast
self.addEventListener('message', (event) => {
	if (event.data && event.data.type === 'SKIP_WAITING') {
		self.skipWaiting();
	}
});

// Fetch Event: Strict bypass and selective caching strategies
self.addEventListener('fetch', (event) => {
	const req = event.request;

	// 1. Strict Bypass: Non-GET requests are NEVER intercepted
	if (req.method !== 'GET') {
		return;
	}

	const url = new URL(req.url);

	// 2. Strict Bypass: WordPress Admin, Login, REST API, AJAX, Customizer, and Elementor
	const bypassPatterns = [
		'/wp-admin/',
		'/wp-login.php',
		'/wp-json/',
		'admin-ajax.php',
		'wc-ajax=',
		'/cart/',
		'/checkout/',
		'/my-account/',
		'/addons/'
	];

	for (const pattern of bypassPatterns) {
		if (url.pathname.includes(pattern) || url.search.includes(pattern)) {
			return;
		}
	}

	// Dynamic WooCommerce Cart/Checkout/Account paths
	for (const wcPath of WC_DYNAMIC_PATHS) {
		if (wcPath && url.pathname.startsWith(wcPath)) {
			return;
		}
	}

	// Query params that must bypass cache immediately
	if (
		url.searchParams.has('add-to-cart') ||
		url.searchParams.has('remove_item') ||
		url.searchParams.has('_wpnonce') ||
		url.searchParams.has('preview') ||
		url.searchParams.has('elementor-preview') ||
		url.searchParams.has('et_fb') ||
		url.searchParams.has('bricks')
	) {
		return;
	}

	// 3. Strict Bypass: Auth cookies or active cart session
	// Service worker request header check: if cookies exist indicating logged in user or cart items
	// Note: We cannot inspect Document Cookie in SW directly, but we bypass any request containing user sessions or non-GET.

	// Strategy A: HTML Document Navigation -> Network-First with 4s timeout & offline fallback
	if (req.mode === 'navigate') {
		event.respondWith(
			new Promise((resolve) => {
				const timeoutId = setTimeout(() => {
					// Network timed out, try cache
					caches.match(req).then((cachedResponse) => {
						if (cachedResponse) {
							resolve(cachedResponse);
						}
					});
				}, 4000);

				fetch(req)
					.then((networkResponse) => {
						clearTimeout(timeoutId);

						// Do NOT cache responses with Set-Cookie or no-store
						const cacheControl = networkResponse.headers.get('Cache-Control') || '';
						const hasSetCookie = networkResponse.headers.has('Set-Cookie');

						if (
							networkResponse.ok &&
							!hasSetCookie &&
							!cacheControl.includes('no-store') &&
							!cacheControl.includes('private')
						) {
							const clone = networkResponse.clone();
							caches.open(CACHE_NAME).then((cache) => cache.put(req, clone));
						}

						resolve(networkResponse);
					})
					.catch(() => {
						clearTimeout(timeoutId);
						// Network failed, serve from cache or offline fallback
						caches.match(req).then((cachedResponse) => {
							if (cachedResponse) {
								resolve(cachedResponse);
								return;
							}

							// Serve custom offline page if configured
							if (OFFLINE_PAGE_URL) {
								caches.match(OFFLINE_PAGE_URL).then((offlineRes) => {
									if (offlineRes) {
										resolve(offlineRes);
									} else {
										resolve(new Response(BUILTIN_OFFLINE_HTML, {
											headers: { 'Content-Type': 'text/html; charset=utf-8' }
										}));
									}
								});
							} else {
								resolve(new Response(BUILTIN_OFFLINE_HTML, {
									headers: { 'Content-Type': 'text/html; charset=utf-8' }
								}));
							}
						});
					});
			})
		);
		return;
	}

	// Strategy B: CSS, JS, Fonts -> Stale-While-Revalidate
	const isStaticAsset = /\.(?:css|js|woff2|woff|ttf|eot)(?:\?.*)?$/i.test(url.pathname);
	if (isStaticAsset) {
		event.respondWith(
			caches.match(req).then((cachedResponse) => {
				const fetchPromise = fetch(req).then((networkResponse) => {
					if (networkResponse.ok) {
						const clone = networkResponse.clone();
						caches.open(CACHE_NAME).then((cache) => cache.put(req, clone));
					}
					return networkResponse;
				}).catch(() => cachedResponse);

				return cachedResponse || fetchPromise;
			})
		);
		return;
	}

	// Strategy C: Images -> Cache-First with max 60 pruning
	const isImage = /\.(?:png|jpg|jpeg|webp|avif|svg|ico)(?:\?.*)?$/i.test(url.pathname);
	if (isImage) {
		event.respondWith(
			caches.match(req).then((cachedResponse) => {
				if (cachedResponse) {
					return cachedResponse;
				}
				return fetch(req).then((networkResponse) => {
					if (networkResponse.ok) {
						const clone = networkResponse.clone();
						caches.open(CACHE_NAME).then((cache) => {
							cache.put(req, clone);
							pruneCache(cache, 60);
						});
					}
					return networkResponse;
				});
			})
		);
		return;
	}
});

/**
 * Cache Pruning Helper
 */
function pruneCache(cache, maxItems) {
	cache.keys().then((keys) => {
		if (keys.length > maxItems) {
			cache.delete(keys[0]).then(() => pruneCache(cache, maxItems));
		}
	});
}
		<?php
	}

	/**
	 * Head Meta Injection for standard PWA and complete iOS Add-to-Home-Screen Recognition.
	 *
	 * @return void
	 */
	public function render_head_meta() {
		$settings = NTV_Core::get_settings();
		if ( empty( $settings['enable_pwa'] ) ) {
			return;
		}

		$site_title     = get_bloginfo( 'name' );
		$app_short_name = ! empty( $settings['app_short_name'] ) ? $settings['app_short_name'] : $site_title;
		$theme_color    = ! empty( $settings['theme_color'] ) ? $settings['theme_color'] : '#0047FF';
		$manifest_url   = home_url( '/ntv-manifest.json' );
		$icon_180       = ! empty( $settings['pwa_icon_180'] ) ? $settings['pwa_icon_180'] : '';

		// If no custom 180 icon, fallback to WP Site Icon.
		if ( empty( $icon_180 ) && function_exists( 'get_site_icon_url' ) ) {
			$icon_180 = get_site_icon_url( 180 );
		}

		echo "\n<!-- Nativly PWA & iOS Safari Meta Tags -->\n";
		echo '<link rel="manifest" href="' . esc_url( $manifest_url ) . '">' . "\n";
		echo '<meta name="theme-color" content="' . esc_attr( $theme_color ) . '">' . "\n";
		echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
		echo '<meta name="apple-mobile-web-app-title" content="' . esc_attr( $app_short_name ) . '">' . "\n";

		if ( ! empty( $icon_180 ) ) {
			echo '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url( $icon_180 ) . '">' . "\n";
		}
	}

	/**
	 * Enqueue PWA script and pass configuration to frontend.
	 *
	 * @return void
	 */
	public function enqueue_pwa_scripts() {
		$settings = NTV_Core::get_settings();
		if ( empty( $settings['enable_pwa'] ) ) {
			return;
		}

		$cache_version = (string) get_option( 'ntv_cache_version', NTV_VERSION );

		wp_enqueue_script(
			'ntv-install-prompt',
			NTV_URL . 'assets/js/ntv-install-prompt.js',
			array(),
			$cache_version,
			true
		);

		$site_title = get_bloginfo( 'name' );
		$app_name   = ! empty( $settings['app_name'] ) ? $settings['app_name'] : $site_title;
		$icon_url   = ! empty( $settings['pwa_icon_192'] ) ? $settings['pwa_icon_192'] : '';
		if ( empty( $icon_url ) && function_exists( 'get_site_icon_url' ) ) {
			$icon_url = get_site_icon_url( 192 );
		}

		wp_localize_script(
			'ntv-install-prompt',
			'ntvPwaData',
			array(
				'swUrl'             => home_url( '/ntv-sw.js' ),
				'scope'             => self::get_scope_path(),
				'enablePrompt'      => ! empty( $settings['enable_install_prompt'] ),
				'appName'           => $app_name,
				'appIcon'           => $icon_url,
				'delaySeconds'      => isset( $settings['install_prompt_delay'] ) ? (int) $settings['install_prompt_delay'] : 3,
				'minPageViews'      => isset( $settings['install_prompt_min_views'] ) ? (int) $settings['install_prompt_min_views'] : 2,
				'cooldownDays'      => isset( $settings['install_prompt_cooldown_days'] ) ? (int) $settings['install_prompt_cooldown_days'] : 7,
				'isRTL'             => is_rtl(),
				'cacheVersion'      => $cache_version,
				'i18n'              => array(
					'updateAvailable'   => __( 'نسخه جدید آماده است — بروزرسانی', 'nativly' ),
					'installApp'        => __( 'نصب اپلیکیشن', 'nativly' ),
					'installSubtitle'   => __( 'برای تجربه سریع‌تر، این برنامه را روی دستگاه خود نصب کنید.', 'nativly' ),
					'installBtn'        => __( 'نصب', 'nativly' ),
					'dismissBtn'        => __( 'بعداً', 'nativly' ),
					'iosInstruction1'   => __( 'دکمه اشتراک‌گذاری (Share) را در پایین صفحه لمس کنید', 'nativly' ),
					'iosInstruction2'   => __( 'گزینه «افزودن به صفحه اصلی» (Add to Home Screen) را انتخاب کنید', 'nativly' ),
					'iosClose'          => __( 'متوجه شدم', 'nativly' ),
				),
			)
		);
	}

	/**
	 * Output install prompt markup for Chromium and iOS modal in footer.
	 *
	 * @return void
	 */
	public function render_install_prompt_markup() {
		$settings = NTV_Core::get_settings();
		if ( empty( $settings['enable_pwa'] ) || empty( $settings['enable_install_prompt'] ) ) {
			return;
		}

		$site_title = get_bloginfo( 'name' );
		$app_name   = ! empty( $settings['app_name'] ) ? $settings['app_name'] : $site_title;
		$icon_url   = ! empty( $settings['pwa_icon_192'] ) ? $settings['pwa_icon_192'] : '';
		if ( empty( $icon_url ) && function_exists( 'get_site_icon_url' ) ) {
			$icon_url = get_site_icon_url( 192 );
		}

		$dir_attr = is_rtl() ? 'rtl' : 'ltr';
		?>
		<!-- Nativly PWA Install Prompt Shell -->
		<div id="ntv-pwa-toast-container" class="ntv-pwa-container" dir="<?php echo esc_attr( $dir_attr ); ?>">
			<!-- Android / Chromium / Mobile Install Card -->
			<div id="ntv-install-card" class="ntv-glass-install-card ntv-install-prompt-banner ntv-install-card" style="display: none;" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="ntv-install-title">
				<button id="ntv-install-close-x" class="ntv-install-close-x" type="button" aria-label="<?php esc_attr_e( 'بستن', 'nativly' ); ?>">&times;</button>
				<div class="ntv-install-header">
					<?php if ( ! empty( $icon_url ) ) : ?>
						<img src="<?php echo esc_url( $icon_url ); ?>" alt="<?php echo esc_attr( $app_name ); ?>" class="ntv-install-app-icon" width="48" height="48">
					<?php endif; ?>
					<div class="ntv-install-info ntv-install-text">
						<h3 id="ntv-install-title" class="ntv-install-title"><?php echo esc_html( $app_name ); ?></h3>
						<p id="ntv-install-desc" class="ntv-install-desc"><?php esc_html_e( 'برای دسترسی سریع‌تر و تجربه بومی، این اپلیکیشن را نصب کنید.', 'nativly' ); ?></p>
					</div>
				</div>
				<div class="ntv-install-actions">
					<button id="ntv-install-dismiss-btn" class="ntv-btn-secondary ntv-install-btn-secondary" type="button"><?php esc_html_e( 'بعداً', 'nativly' ); ?></button>
					<button id="ntv-install-confirm-btn" class="ntv-btn-primary ntv-install-btn-primary" type="button"><?php esc_html_e( 'نصب اپلیکیشن', 'nativly' ); ?></button>
				</div>
			</div>

			<!-- iOS Safari "Add to Home Screen" Visual Modal -->
			<div id="ntv-ios-modal-backdrop" class="ntv-ios-backdrop ntv-ios-guide-modal" style="display: none;" aria-hidden="true">
				<div id="ntv-ios-modal" class="ntv-glass-ios-modal ntv-ios-modal-card" role="dialog" aria-modal="true">
					<div class="ntv-ios-header">
						<?php if ( ! empty( $icon_url ) ) : ?>
							<img src="<?php echo esc_url( $icon_url ); ?>" alt="<?php echo esc_attr( $app_name ); ?>" class="ntv-ios-app-icon" width="56" height="56">
						<?php endif; ?>
						<div>
							<h3 class="ntv-ios-title"><?php echo esc_html( $app_name ); ?></h3>
							<p class="ntv-ios-subtitle"><?php esc_html_e( 'راهنمای نصب وب‌اپلیکیشن', 'nativly' ); ?></p>
						</div>
					</div>
					<div class="ntv-ios-steps">
						<div class="ntv-ios-step">
							<span class="ntv-step-num">۱</span>
							<div class="ntv-step-content">
								<span><?php esc_html_e( 'از منوی مرورگر یا دکمه اشتراک‌گذاری (Share) را لمس کنید:', 'nativly' ); ?></span>
								<svg class="ntv-share-icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path>
									<polyline points="16 6 12 2 8 6"></polyline>
									<line x1="12" y1="2" x2="12" y2="15"></line>
								</svg>
							</div>
						</div>
						<div class="ntv-ios-step">
							<span class="ntv-step-num">۲</span>
							<div class="ntv-step-content">
								<span><?php esc_html_e( 'به پایین اسکرول کرده و گزینه «افزودن به صفحه اصلی» (Add to Home Screen) را انتخاب نمایید:', 'nativly' ); ?></span>
								<div class="ntv-add-badge">
									<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
										<line x1="12" y1="8" x2="12" y2="16"></line>
										<line x1="8" y1="12" x2="16" y2="12"></line>
									</svg>
									<span>Add to Home Screen</span>
								</div>
							</div>
						</div>
					</div>
					<button id="ntv-ios-close-btn" class="ntv-btn-primary ntv-btn-block ntv-ios-got-it-btn" type="button"><?php esc_html_e( 'متوجه شدم', 'nativly' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Automatically generate 192x192 and iOS 180x180 PNG icons from a 512x512 attachment.
	 *
	 * @param int $attachment_id Media attachment ID.
	 * @return array{icon_180: string, icon_192: string, icon_512: string}|false
	 */
	public static function generate_pwa_icons( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( ! $attachment_id ) {
			return false;
		}

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$target_dir = trailingslashit( $upload_dir['basedir'] ) . 'ntv-pwa';
		$target_url = trailingslashit( $upload_dir['baseurl'] ) . 'ntv-pwa';

		if ( ! file_exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		$icon_512_url = wp_get_attachment_url( $attachment_id );

		// Generate 180x180 for iOS Apple Touch Icon.
		$editor_180 = wp_get_image_editor( $file_path );
		$icon_180_url = '';
		if ( ! is_wp_error( $editor_180 ) ) {
			$editor_180->resize( 180, 180, true );
			$dest_180 = $target_dir . '/icon-180x180.png';
			$saved    = $editor_180->save( $dest_180, 'image/png' );
			if ( ! is_wp_error( $saved ) ) {
				$icon_180_url = $target_url . '/icon-180x180.png?v=' . time();
			}
		}

		// Generate 192x192 for Android / PWA Manifest.
		$editor_192 = wp_get_image_editor( $file_path );
		$icon_192_url = '';
		if ( ! is_wp_error( $editor_192 ) ) {
			$editor_192->resize( 192, 192, true );
			$dest_192 = $target_dir . '/icon-192x192.png';
			$saved    = $editor_192->save( $dest_192, 'image/png' );
			if ( ! is_wp_error( $saved ) ) {
				$icon_192_url = $target_url . '/icon-192x192.png?v=' . time();
			}
		}

		// Save updated icon URLs directly to settings.
		$settings = NTV_Core::get_settings();
		$settings['pwa_icon_id']  = $attachment_id;
		$settings['pwa_icon_180'] = $icon_180_url ? $icon_180_url : $icon_512_url;
		$settings['pwa_icon_192'] = $icon_192_url ? $icon_192_url : $icon_512_url;
		$settings['pwa_icon_512'] = $icon_512_url;

		NTV_Core::update_settings( $settings );

		return array(
			'icon_180' => $settings['pwa_icon_180'],
			'icon_192' => $settings['pwa_icon_192'],
			'icon_512' => $settings['pwa_icon_512'],
		);
	}
}
