<?php
/**
 * Plugin Name:       Nativly
 * Plugin URI:        https://github.com/arsalanjz/nativly
 * Description:       Transform WordPress into a native-like Progressive Web App (PWA) with an expanding glassmorphic navigation bar.
 * Version:           1.3.0
 * Author:            Arsalan Jabarzare
 * Author URI:        https://github.com/arsalanjz
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nativly
 * Domain Path:       /languages
 * Requires at least: 6.2
 * Requires PHP:      7.4
 *
 * @package Nativly
 */

defined( 'ABSPATH' ) || exit;

// Plugin Constants.
define( 'NTV_VERSION', '1.3.0' );
define( 'NTV_PATH', plugin_dir_path( __FILE__ ) );
define( 'NTV_URL', plugin_dir_url( __FILE__ ) );
define( 'NTV_BASENAME', plugin_basename( __FILE__ ) );
define( 'NTV_OPTION_KEY', 'ntv_settings' );

// Require Foundational Classes.
require_once NTV_PATH . 'includes/class-ntv-activator.php';
require_once NTV_PATH . 'includes/class-ntv-icons.php';
require_once NTV_PATH . 'includes/class-ntv-core.php';

// Activation & Deactivation Hooks.
register_activation_hook( __FILE__, array( 'NTV_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NTV_Activator', 'deactivate' ) );

/**
 * Boot the plugin orchestrator.
 *
 * @return NTV_Core
 */
function nativly_init() {
	return NTV_Core::get_instance();
}

add_action( 'plugins_loaded', 'nativly_init' );
