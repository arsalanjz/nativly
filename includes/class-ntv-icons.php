<?php
/**
 * Icon Library & Strict SVG Sanitizer
 *
 * @package Nativly
 * @author  Arsalan Jabarzare
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class NTV_Icons
 *
 * Provides a curated static catalog of 25 Lucide SVG icons and
 * a hardened DOMDocument-based SVG sanitizer.
 */
class NTV_Icons {

	/**
	 * Static cache for icon catalog.
	 *
	 * @var array|null
	 */
	private static $catalog = null;

	/**
	 * Retrieve full catalog of 25 clean, MIT-licensed Lucide SVG icons.
	 *
	 * @return array<string, array{name: string, path: string}>
	 */
	public static function get_catalog() {
		if ( null !== self::$catalog ) {
			return self::$catalog;
		}

		self::$catalog = array(
			'home'           => array(
				'name' => __( 'Home', 'nativly' ),
				'path' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline>',
			),
			'book-open'      => array(
				'name' => __( 'Courses / Book', 'nativly' ),
				'path' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>',
			),
			'grid'           => array(
				'name' => __( 'Grid / Categories', 'nativly' ),
				'path' => '<rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect>',
			),
			'shopping-bag'   => array(
				'name' => __( 'Shopping Bag', 'nativly' ),
				'path' => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path><path d="M3 6h18"></path><path d="M16 10a4 4 0 0 1-8 0"></path>',
			),
			'shopping-cart'  => array(
				'name' => __( 'Shopping Cart', 'nativly' ),
				'path' => '<circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path>',
			),
			'user'           => array(
				'name' => __( 'User / Profile', 'nativly' ),
				'path' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
			),
			'search'         => array(
				'name' => __( 'Search', 'nativly' ),
				'path' => '<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path>',
			),
			'heart'          => array(
				'name' => __( 'Wishlist / Heart', 'nativly' ),
				'path' => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path>',
			),
			'bell'           => array(
				'name' => __( 'Notifications / Bell', 'nativly' ),
				'path' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>',
			),
			'phone'          => array(
				'name' => __( 'Phone / Call', 'nativly' ),
				'path' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>',
			),
			'message-circle' => array(
				'name' => __( 'Chat / Messages', 'nativly' ),
				'path' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path>',
			),
			'compass'        => array(
				'name' => __( 'Explore / Compass', 'nativly' ),
				'path' => '<circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon>',
			),
			'layers'         => array(
				'name' => __( 'Layers / Services', 'nativly' ),
				'path' => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"></path><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"></path><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"></path>',
			),
			'package'        => array(
				'name' => __( 'Orders / Package', 'nativly' ),
				'path' => '<path d="m7.5 4.27 9 5.15"></path><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path>',
			),
			'download'       => array(
				'name' => __( 'Downloads', 'nativly' ),
				'path' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" x2="12" y1="15" y2="3"></line>',
			),
			'settings'       => array(
				'name' => __( 'Settings', 'nativly' ),
				'path' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle>',
			),
			'star'           => array(
				'name' => __( 'Favorites / Star', 'nativly' ),
				'path' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>',
			),
			'award'          => array(
				'name' => __( 'Achievements / Award', 'nativly' ),
				'path' => '<circle cx="12" cy="8" r="6"></circle><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"></path>',
			),
			'tool'           => array(
				'name' => __( 'Support / Tool', 'nativly' ),
				'path' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>',
			),
			'sparkles'       => array(
				'name' => __( 'Special / Sparkles', 'nativly' ),
				'path' => '<path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"></path><path d="M5 3v4"></path><path d="M19 17v4"></path><path d="M3 5h4"></path><path d="M17 19h4"></path>',
			),
			'credit-card'    => array(
				'name' => __( 'Billing / Credit Card', 'nativly' ),
				'path' => '<rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" x2="22" y1="10" y2="10"></line>',
			),
			'receipt'        => array(
				'name' => __( 'Invoices / Receipt', 'nativly' ),
				'path' => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"></path><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><path d="M12 17.5v-11"></path>',
			),
			'qr-code'        => array(
				'name' => __( 'QR Code / Scan', 'nativly' ),
				'path' => '<rect width="5" height="5" x="3" y="3" rx="1"></rect><rect width="5" height="5" x="16" y="3" rx="1"></rect><rect width="5" height="5" x="3" y="16" rx="1"></rect><path d="M21 16h-3a2 2 0 0 0-2 2v3"></path><path d="M21 21v.01"></path><path d="M12 7v3a2 2 0 0 1-2 2H7"></path><path d="M3 12h.01"></path><path d="M12 3h.01"></path><path d="M12 16v.01"></path><path d="M16 12h1"></path><path d="M21 12v.01"></path><path d="M12 21v-1"></path>',
			),
			'help-circle'    => array(
				'name' => __( 'Help / FAQ', 'nativly' ),
				'path' => '<circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><path d="M12 17h.01"></path>',
			),
			'log-in'         => array(
				'name' => __( 'Login / Sign In', 'nativly' ),
				'path' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" x2="3" y1="12" y2="12"></line>',
			),
		);

		return self::$catalog;
	}

	/**
	 * Check if an icon key exists in the Lucide catalog.
	 *
	 * @param string $key Icon slug.
	 * @return bool
	 */
	public static function has_icon( $key ) {
		$catalog = self::get_catalog();
		return isset( $catalog[ $key ] );
	}

	/**
	 * Get complete SVG markup for an icon key or custom SVG string.
	 *
	 * @param string               $key_or_custom_svg Icon key from catalog or raw SVG string.
	 * @param array<string,string> $custom_attrs      Optional attributes to add/override on root <svg>.
	 * @return string Clean, safe SVG markup.
	 */
	public static function get_icon_svg( $key_or_custom_svg, $custom_attrs = array() ) {
		$key_or_custom_svg = trim( (string) $key_or_custom_svg );
		if ( empty( $key_or_custom_svg ) ) {
			return '';
		}

		$catalog = self::get_catalog();

		// Check if it is a catalog icon.
		if ( isset( $catalog[ $key_or_custom_svg ] ) ) {
			$base_attrs = array(
				'xmlns'       => 'http://www.w3.org/2000/svg',
				'viewBox'     => '0 0 24 24',
				'width'       => '24',
				'height'      => '24',
				'fill'        => 'none',
				'stroke'      => 'currentColor',
				'stroke-width' => '2',
				'stroke-linecap' => 'round',
				'stroke-linejoin' => 'round',
				'class'       => 'ntv-icon-svg ntv-icon-' . sanitize_html_class( $key_or_custom_svg ),
				'aria-hidden' => 'true',
			);

			if ( ! empty( $custom_attrs ) && is_array( $custom_attrs ) ) {
				if ( isset( $custom_attrs['class'] ) && isset( $base_attrs['class'] ) ) {
					$custom_attrs['class'] = $base_attrs['class'] . ' ' . $custom_attrs['class'];
				}
				$base_attrs = array_merge( $base_attrs, $custom_attrs );
			}

			$attr_string = '';
			foreach ( $base_attrs as $attr_key => $attr_val ) {
				$attr_string .= sprintf( ' %s="%s"', esc_attr( $attr_key ), esc_attr( (string) $attr_val ) );
			}

			return '<svg' . $attr_string . '>' . $catalog[ $key_or_custom_svg ]['path'] . '</svg>';
		}

		// Otherwise, treat as custom SVG and sanitize strictly.
		if ( false !== stripos( $key_or_custom_svg, '<svg' ) ) {
			return self::sanitize_svg( $key_or_custom_svg );
		}

		return '';
	}

	/**
	 * Strict DOMDocument SVG sanitizer.
	 *
	 * Allows only clean vector elements and safe presentation attributes.
	 * Strips scripts, foreignObjects, event handlers, and javascript: protocols.
	 *
	 * @param string $svg Raw SVG input.
	 * @return string Sanitized SVG string or empty string on failure.
	 */
	public static function sanitize_svg( $svg ) {
		if ( empty( $svg ) || ! is_string( $svg ) ) {
			return '';
		}

		$svg = trim( $svg );

		// Quick pre-check for SVG element.
		if ( false === stripos( $svg, '<svg' ) ) {
			return '';
		}

		// Disable internal errors during parsing to prevent warning leakage.
		$libxml_prev_state = libxml_use_internal_errors( true );
		$disable_entities  = null;
		if ( function_exists( 'libxml_disable_entity_loader' ) && PHP_VERSION_ID < 80000 ) {
			// Deprecated in PHP 8.0+, only used in PHP 7.4.
			// phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated
			$disable_entities = libxml_disable_entity_loader( true );
		}

		$dom = new DOMDocument();
		$dom->resolveExternals   = false;
		$dom->substituteEntities = false;

		// Prepend XML declaration for UTF-8 preservation.
		$xml_prefix = '<?xml version="1.0" encoding="UTF-8"?>';
		$loaded     = $dom->loadXML( $xml_prefix . $svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );

		if ( ! $loaded ) {
			// Fallback with HTML encapsulation for resilient parsing.
			$html_wrapper = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>' . $svg . '</body></html>';
			$loaded       = $dom->loadHTML( $html_wrapper, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		}

		if ( ! $loaded ) {
			libxml_clear_errors();
			libxml_use_internal_errors( $libxml_prev_state );
			if ( null !== $disable_entities && function_exists( 'libxml_disable_entity_loader' ) ) {
				// phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated
				libxml_disable_entity_loader( $disable_entities );
			}
			return '';
		}

		// Strictly allowed SVG tags.
		$allowed_tags = array(
			'svg',
			'path',
			'circle',
			'rect',
			'line',
			'polyline',
			'polygon',
			'g',
			'title',
			'desc',
		);

		// Strictly allowed SVG attributes.
		$allowed_attrs = array(
			'xmlns',
			'viewbox',
			'width',
			'height',
			'fill',
			'stroke',
			'stroke-width',
			'stroke-linecap',
			'stroke-linejoin',
			'class',
			'id',
			'd',
			'cx',
			'cy',
			'r',
			'rx',
			'ry',
			'x',
			'y',
			'x1',
			'y1',
			'x2',
			'y2',
			'points',
			'transform',
			'opacity',
			'fill-opacity',
			'stroke-opacity',
			'stroke-dasharray',
			'stroke-dashoffset',
			'stroke-miterlimit',
			'clip-rule',
			'fill-rule',
			'aria-hidden',
			'role',
		);

		$nodes_to_remove = array();
		$all_elements    = $dom->getElementsByTagName( '*' );

		foreach ( $all_elements as $element ) {
			$tag_name = strtolower( $element->tagName );

			// Ignore HTML container tags when parsed via loadHTML.
			if ( in_array( $tag_name, array( 'html', 'head', 'body', 'meta' ), true ) ) {
				continue;
			}

			if ( ! in_array( $tag_name, $allowed_tags, true ) ) {
				$nodes_to_remove[] = $element;
				continue;
			}

			if ( $element->hasAttributes() ) {
				$attrs_to_remove = array();

				foreach ( $element->attributes as $attr ) {
					$attr_name  = strtolower( $attr->nodeName );
					$attr_value = trim( $attr->nodeValue );

					// Disallow any event handler (onload, onclick, onerror, etc.).
					if ( 0 === strpos( $attr_name, 'on' ) ) {
						$attrs_to_remove[] = $attr->nodeName;
						continue;
					}

					// Disallow dangerous URI schemes.
					if ( preg_match( '/\s*(javascript|vbscript|data):/i', $attr_value ) ) {
						$attrs_to_remove[] = $attr->nodeName;
						continue;
					}

					// Disallow CSS expressions or behaviors.
					if ( false !== stripos( $attr_value, 'expression' ) || false !== stripos( $attr_value, 'behavior' ) ) {
						$attrs_to_remove[] = $attr->nodeName;
						continue;
					}

					// Allowlist check.
					if ( ! in_array( $attr_name, $allowed_attrs, true ) ) {
						$attrs_to_remove[] = $attr->nodeName;
					}
				}

				foreach ( $attrs_to_remove as $attr_to_remove ) {
					$element->removeAttribute( $attr_to_remove );
				}
			}
		}

		// Remove disallowed elements from DOM tree.
		foreach ( $nodes_to_remove as $node ) {
			if ( $node->parentNode ) {
				$node->parentNode->removeChild( $node );
			}
		}

		// Extract root <svg> element.
		$svg_elements = $dom->getElementsByTagName( 'svg' );
		if ( 0 === $svg_elements->length ) {
			libxml_clear_errors();
			libxml_use_internal_errors( $libxml_prev_state );
			if ( null !== $disable_entities && function_exists( 'libxml_disable_entity_loader' ) ) {
				// phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated
				libxml_disable_entity_loader( $disable_entities );
			}
			return '';
		}

		$root_svg  = $svg_elements->item( 0 );
		$clean_svg = $dom->saveXML( $root_svg );

		libxml_clear_errors();
		libxml_use_internal_errors( $libxml_prev_state );
		if ( null !== $disable_entities && function_exists( 'libxml_disable_entity_loader' ) ) {
			// phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated
			libxml_disable_entity_loader( $disable_entities );
		}

		return is_string( $clean_svg ) ? trim( $clean_svg ) : '';
	}
}
