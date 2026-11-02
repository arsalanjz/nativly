<?php
/**
 * WooCommerce Integration Component
 *
 * @package Nativly
 * @author  Arsalan Jabarzare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class NTV_WooCommerce
 *
 * Handles WooCommerce mini-cart fragments and cart count badges.
 */
class NTV_WooCommerce {

	/**
	 * Singleton instance.
	 *
	 * @var NTV_WooCommerce|null
	 */
	private static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @return NTV_WooCommerce
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
	 * Initialize WooCommerce hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'add_cart_count_fragment' ) );
	}

	/**
	 * Get current cart item count safely.
	 *
	 * @return int Total number of items in cart.
	 */
	public static function get_cart_count() {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
			return 0;
		}

		$wc = WC();
		if ( ! $wc || ! isset( $wc->cart ) || ! is_object( $wc->cart ) ) {
			return 0;
		}

		return (int) $wc->cart->get_cart_contents_count();
	}

	/**
	 * Render cart badge HTML.
	 *
	 * @param int|null $count Optional count.
	 * @return string HTML span markup.
	 */
	public static function render_cart_badge_html( $count = null ) {
		if ( null === $count ) {
			$count = self::get_cart_count();
		}

		$count         = (int) $count;
		$is_visible    = $count > 0;
		$empty_class   = $is_visible ? '' : ' is-empty';
		$display_style = $is_visible ? '' : ' style="display:none;"';
		$display_count = $count > 99 ? '99+' : (string) $count;

		return sprintf(
			'<span class="ntv-badge ntv-cart-badge%s"%s data-count="%d" aria-label="%s">%s</span>',
			$empty_class,
			$display_style,
			$count,
			/* translators: %d: number of items in shopping cart */
			esc_attr( sprintf( __( '%d items in cart', 'nativly' ), $count ) ),
			esc_html( $display_count )
		);
	}

	/**
	 * Hook into woocommerce_add_to_cart_fragments to update cart badge in real-time.
	 *
	 * @param array $fragments Associative array of fragments.
	 * @return array Updated fragments.
	 */
	public function add_cart_count_fragment( $fragments ) {
		$fragments['span.ntv-cart-badge'] = self::render_cart_badge_html();
		return $fragments;
	}
}
