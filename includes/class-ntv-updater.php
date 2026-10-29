<?php
/**
 * Nativly Remote GitHub & Self-Hosted Release Updater
 *
 * @package Nativly
 * @author  Arsalan Jabarzare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class NTV_Updater
 *
 * Checks for updates from GitHub Releases (or custom JSON endpoints), injects update
 * payloads into the WordPress plugin update transient, and handles plugin information modal.
 */
class NTV_Updater {

	/**
	 * Singleton instance.
	 *
	 * @var NTV_Updater|null
	 */
	private static $instance = null;

	/**
	 * GitHub Repository Path.
	 *
	 * @var string
	 */
	private $repository = 'arsalanjz/nativly';

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	private $current_version = '';

	/**
	 * Plugin basename.
	 *
	 * @var string
	 */
	private $plugin_basename = '';

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private $plugin_slug = 'nativly';

	/**
	 * Transient cache key.
	 *
	 * @var string
	 */
	private $cache_key = 'ntv_update_check';

	/**
	 * Cache expiration in seconds (12 hours).
	 *
	 * @var int
	 */
	private $cache_ttl = 43200;

	/**
	 * Retrieve singleton instance.
	 *
	 * @return NTV_Updater
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
		$this->current_version = defined( 'NTV_VERSION' ) ? NTV_VERSION : '1.0.0';
		$this->plugin_basename = defined( 'NTV_BASENAME' ) ? NTV_BASENAME : 'nativly/nativly.php';

		$this->init_hooks();
	}

	/**
	 * Register WordPress update hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_plugin_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info_popup' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_update_cache' ), 10, 2 );
	}

	/**
	 * Get updater configuration with filter support.
	 *
	 * Allows overriding the repository or specifying a custom JSON endpoint.
	 *
	 * @return array{repository: string, json_url: string, access_token: string}
	 */
	public function get_config() {
		$default_config = array(
			'repository'   => $this->repository,
			'json_url'     => '',
			'access_token' => '',
		);

		return (array) apply_filters( 'ntv_updater_config', $default_config );
	}

	/**
	 * Check remote source for plugin updates and inject into update transient.
	 *
	 * @param object|false $transient WordPress update plugins transient.
	 * @return object
	 */
	public function check_for_plugin_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote_data = $this->get_remote_release();
		if ( empty( $remote_data ) || empty( $remote_data['version'] ) ) {
			return $transient;
		}

		// Compare versions.
		if ( version_compare( $this->current_version, $remote_data['version'], '<' ) ) {
			$update_payload = (object) array(
				'slug'        => $this->plugin_slug,
				'plugin'      => $this->plugin_basename,
				'new_version' => $remote_data['version'],
				'url'         => $remote_data['details_url'] ?? 'https://github.com/' . $this->repository,
				'package'     => $remote_data['download_url'] ?? '',
				'icons'       => array(
					'1x' => NTV_URL . 'assets/images/icon-128x128.png',
					'2x' => NTV_URL . 'assets/images/icon-256x256.png',
				),
				'tested'      => $remote_data['tested'] ?? '',
				'requires'    => $remote_data['requires'] ?? '6.0',
				'requires_php'=> $remote_data['requires_php'] ?? '7.4',
			);

			$transient->response[ $this->plugin_basename ] = $update_payload;
		} else {
			$transient->no_update[ $this->plugin_basename ] = (object) array(
				'slug'        => $this->plugin_slug,
				'plugin'      => $this->plugin_basename,
				'new_version' => $this->current_version,
				'url'         => 'https://github.com/' . $this->repository,
				'package'     => '',
			);
		}

		return $transient;
	}

	/**
	 * Handle Plugin Information modal view in WP Admin (plugins_api filter).
	 *
	 * @param false|object|array $result Result.
	 * @param string             $action Action name.
	 * @param object             $args Arguments.
	 * @return false|object
	 */
	public function plugin_info_popup( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $this->plugin_slug !== $args->slug ) {
			return $result;
		}

		$remote_data = $this->get_remote_release();
		if ( empty( $remote_data ) ) {
			return $result;
		}

		$info = (object) array(
			'name'          => 'Nativly',
			'slug'          => $this->plugin_slug,
			'version'       => $remote_data['version'] ?? $this->current_version,
			'author'        => '<a href="https://github.com/arsalanjz">Arsalan Jabarzare</a>',
			'homepage'      => 'https://github.com/' . $this->repository,
			'requires'      => $remote_data['requires'] ?? '6.0',
			'tested'        => $remote_data['tested'] ?? '6.7',
			'requires_php'  => $remote_data['requires_php'] ?? '7.4',
			'download_link' => $remote_data['download_url'] ?? '',
			'sections'      => array(
				'description' => __( 'تبدیل حرفه‌ای وب‌سایت وردپرسی و ووکامرسی به اپلیکیشن بومی با نوبار شناور شیشه‌ای، کش هوشمند PWA و پشتیبانی کامل از iOS و Android.', 'nativly' ),
				'changelog'   => ! empty( $remote_data['changelog'] ) ? $remote_data['changelog'] : __( 'بروزرسانی‌ها و بهبودهای عملکردی.', 'nativly' ),
			),
			'banners'       => array(
				'low'  => NTV_URL . 'assets/images/banner-772x250.png',
				'high' => NTV_URL . 'assets/images/banner-1544x500.png',
			),
		);

		return $info;
	}

	/**
	 * Fetch release data from cache or remote GitHub API / Custom JSON endpoint.
	 *
	 * @return array<string,string>|false
	 */
	private function get_remote_release() {
		// 1. Check transient cache.
		$cached = get_transient( $this->cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$config = $this->get_config();
		$data   = false;

		// 2. Custom JSON Endpoint check if provided.
		if ( ! empty( $config['json_url'] ) ) {
			$response = wp_remote_get(
				esc_url_raw( $config['json_url'] ),
				array(
					'timeout' => 10,
					'headers' => array( 'Accept' => 'application/json' ),
				)
			);

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( is_array( $body ) && ! empty( $body['version'] ) ) {
					$data = array(
						'version'      => sanitize_text_field( $body['version'] ),
						'download_url' => esc_url_raw( $body['download_url'] ?? '' ),
						'details_url'  => esc_url_raw( $body['details_url'] ?? '' ),
						'changelog'    => wp_kses_post( $body['changelog'] ?? '' ),
						'tested'       => sanitize_text_field( $body['tested'] ?? '' ),
						'requires'     => sanitize_text_field( $body['requires'] ?? '6.0' ),
						'requires_php' => sanitize_text_field( $body['requires_php'] ?? '7.4' ),
					);
				}
			}
		}

		// 3. GitHub Releases API check if no JSON data.
		if ( false === $data && ! empty( $config['repository'] ) ) {
			$api_url = sprintf( 'https://api.github.com/repos/%s/releases/latest', trim( $config['repository'], '/' ) );
			$headers = array(
				'Accept'     => 'application/vnd.github.v3+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
			);

			if ( ! empty( $config['access_token'] ) ) {
				$headers['Authorization'] = 'token ' . $config['access_token'];
			}

			$response = wp_remote_get(
				$api_url,
				array(
					'timeout' => 10,
					'headers' => $headers,
				)
			);

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$release = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( is_array( $release ) && ! empty( $release['tag_name'] ) ) {
					$version = ltrim( sanitize_text_field( $release['tag_name'] ), 'v' );

					// Look for downloadable zip asset or fallback to zipball_url.
					$download_url = '';
					if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
						foreach ( $release['assets'] as $asset ) {
							if ( ! empty( $asset['browser_download_url'] ) && false !== strpos( $asset['name'], '.zip' ) ) {
								$download_url = $asset['browser_download_url'];
								break;
							}
						}
					}

					if ( empty( $download_url ) && ! empty( $release['zipball_url'] ) ) {
						$download_url = $release['zipball_url'];
					}

					$data = array(
						'version'      => $version,
						'download_url' => esc_url_raw( $download_url ),
						'details_url'  => esc_url_raw( $release['html_url'] ?? '' ),
						'changelog'    => wp_kses_post( $release['body'] ?? '' ),
						'tested'       => '6.7',
						'requires'     => '6.0',
						'requires_php' => '7.4',
					);
				}
			}
		}

		// 4. Cache result for 12 hours.
		if ( is_array( $data ) ) {
			set_transient( $this->cache_key, $data, $this->cache_ttl );
		} else {
			// Cache empty state for 1 hour to prevent flooding remote API on errors.
			set_transient( $this->cache_key, array(), 3600 );
		}

		return $data;
	}

	/**
	 * Clear update cache when upgrader completes.
	 *
	 * @param WP_Upgrader $upgrader Upgrader object.
	 * @param array       $hook_extra Hook arguments.
	 * @return void
	 */
	public function clear_update_cache( $upgrader, $hook_extra ) {
		if ( isset( $hook_extra['action'], $hook_extra['type'] ) && 'update' === $hook_extra['action'] && 'plugin' === $hook_extra['type'] ) {
			delete_transient( $this->cache_key );
		}
	}
}
