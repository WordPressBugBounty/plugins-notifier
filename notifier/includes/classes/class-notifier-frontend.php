<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 *
 * @package    Wa_Notifier
 */
class Notifier_Frontend {
	/**
	 * Init
	 */
	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'display_whatsapp_chat_button' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'add_chat_button_inline_styles' ), 20 );
	}

	/**
	 * Add position and device visibility CSS for the chat button.
	 *
	 * Device visibility is handled with media queries (not wp_is_mobile()) so it
	 * works with page caching and can distinguish tablets.
	 */
	public static function add_chat_button_inline_styles() {
		$handle = NOTIFIER_NAME . '-frontend-css';
		if ( ! wp_style_is( $handle, 'enqueued' ) ) {
			return;
		}

		$css      = '';
		$position = get_option( 'notifier_ctc_position', 'bottom-right' );
		$offset_x = absint( get_option( 'notifier_ctc_offset_x', 20 ) );
		$offset_y = absint( get_option( 'notifier_ctc_offset_y', 20 ) );

		// The default matches frontend.css; skip it so existing theme CSS overrides of the button position keep working.
		if ( 'bottom-right' !== $position || 20 !== $offset_x || 20 !== $offset_y ) {
			$css .= self::get_position_css( $position, $offset_x, $offset_y );
		}

		$mobile_query = '(max-width: 767px)';

		if ( 'yes' === get_option( 'notifier_ctc_mobile_position_enable', 'no' ) ) {
			$css .= '@media ' . $mobile_query . '{' . self::get_position_css(
				get_option( 'notifier_ctc_mobile_position', 'bottom-right' ),
				get_option( 'notifier_ctc_mobile_offset_x', 20 ),
				get_option( 'notifier_ctc_mobile_offset_y', 20 )
			) . '}';
		}

		$devices       = (array) get_option( 'notifier_ctc_devices', array( 'desktop', 'tablet', 'mobile' ) );
		$media_queries = array(
			'mobile'  => $mobile_query,
			'tablet'  => '(min-width: 768px) and (max-width: 1024px)',
			'desktop' => '(min-width: 1025px)',
		);
		foreach ( $media_queries as $device => $query ) {
			if ( ! in_array( $device, $devices, true ) ) {
				$css .= '@media ' . $query . '{.notifier-click-to-chat-btn{display:none !important;}}';
			}
		}

		if ( '' !== $css ) {
			wp_add_inline_style( $handle, $css );
		}
	}

	/**
	 * Build the fixed-position CSS rule for a corner + offsets.
	 *
	 * @param string     $position Corner key, e.g. 'bottom-right'.
	 * @param int|string $offset_x Horizontal offset in px.
	 * @param int|string $offset_y Vertical offset in px.
	 * @return string
	 */
	private static function get_position_css( $position, $offset_x, $offset_y ) {
		$vertical   = 0 === strpos( (string) $position, 'top' ) ? 'top' : 'bottom';
		$horizontal = false !== strpos( (string) $position, 'left' ) ? 'left' : 'right';

		return sprintf(
			'.notifier-click-to-chat-btn{top:auto;bottom:auto;left:auto;right:auto;%1$s:%2$dpx;%3$s:%4$dpx;}',
			$vertical,
			absint( $offset_y ),
			$horizontal,
			absint( $offset_x )
		);
	}

	/**
	 * Whether the chat button should be displayed on the current request.
	 *
	 * A page qualifies when it matches any "display" rule and no "exclude" rule.
	 *
	 * @return bool
	 */
	public static function should_display_on_current_page() {
		$rules = Notifier_Backend::get_ctc_display_rules();

		if ( self::matches_any_rule( $rules['exclude'] ) ) {
			return false;
		}

		return self::matches_any_rule( $rules['display'] );
	}

	/**
	 * @param array $rows Rule rows: array( 'rule' => key, 'specifics' => array ).
	 * @return bool
	 */
	private static function matches_any_rule( $rows ) {
		foreach ( (array) $rows as $row ) {
			if ( ! empty( $row['rule'] ) && self::rule_matches( $row['rule'], (array) ( $row['specifics'] ?? array() ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check one rule key against the current request. Keys are documented in
	 * Notifier_Backend::get_ctc_location_options().
	 *
	 * @param string $rule
	 * @param array  $specifics
	 * @return bool
	 */
	private static function rule_matches( $rule, $specifics = array() ) {
		switch ( $rule ) {
			case 'basic-global':
				return true;
			case 'basic-singulars':
				return is_singular();
			case 'basic-archives':
				return is_archive();
			case 'special-front':
				return is_front_page();
			case 'special-blog':
				return is_home();
			case 'special-404':
				return is_404();
			case 'special-search':
				return is_search();
			case 'special-date':
				return is_date();
			case 'special-author':
				return is_author();
			case 'wc-shop':
				return function_exists( 'is_shop' ) && is_shop();
			case 'wc-cart':
				return function_exists( 'is_cart' ) && is_cart();
			case 'wc-checkout':
				return function_exists( 'is_checkout' ) && is_checkout();
			case 'wc-account':
				return function_exists( 'is_account_page' ) && is_account_page();
			case 'wc-order-received':
				return function_exists( 'is_order_received_page' ) && is_order_received_page();
			case 'specifics':
				foreach ( $specifics as $item ) {
					if ( self::specific_matches( $item ) ) {
						return true;
					}
				}
				return false;
		}

		// {post_type}|all, {post_type}|archive, {post_type}|tax|{taxonomy}
		$parts = explode( '|', $rule );
		if ( count( $parts ) < 2 || ! post_type_exists( $parts[0] ) ) {
			return false;
		}
		$post_type = $parts[0];

		if ( 'all' === $parts[1] ) {
			return is_singular( $post_type );
		}
		if ( 'archive' === $parts[1] ) {
			return is_post_type_archive( $post_type );
		}
		if ( 'tax' === $parts[1] && ! empty( $parts[2] ) ) {
			$taxonomy = $parts[2];
			if ( 'category' === $taxonomy ) {
				return is_category();
			}
			if ( 'post_tag' === $taxonomy ) {
				return is_tag();
			}
			return is_tax( $taxonomy );
		}

		return false;
	}

	/**
	 * Match a specific item: post-{id} (that single post/page/product), tax-{id} (that
	 * term's archive) or tax-{id}-posts (any single item that has that term).
	 *
	 * @param string $item
	 * @return bool
	 */
	private static function specific_matches( $item ) {
		if ( ! preg_match( '/^(post|tax)-(\d+)(-posts)?$/', (string) $item, $m ) ) {
			return false;
		}
		$id = (int) $m[2];

		if ( 'post' === $m[1] ) {
			if ( is_singular() && (int) get_queried_object_id() === $id ) {
				return true;
			}
			// Pages WordPress / WooCommerce render as archives rather than singulars.
			if ( is_home() && ! is_front_page() && (int) get_option( 'page_for_posts' ) === $id ) {
				return true;
			}
			if ( function_exists( 'is_shop' ) && function_exists( 'wc_get_page_id' ) && is_shop() && (int) wc_get_page_id( 'shop' ) === $id ) {
				return true;
			}
			return false;
		}

		if ( ! empty( $m[3] ) ) {
			$term = get_term( $id );
			return $term && ! is_wp_error( $term ) && is_singular() && has_term( $id, $term->taxonomy, get_queried_object_id() );
		}

		$queried = get_queried_object();
		return ( is_category() || is_tag() || is_tax() ) && $queried instanceof WP_Term && (int) $queried->term_id === $id;
	}

	/**
	 * Display Whatsapp chat button.
	 */
	public static function display_whatsapp_chat_button() {
		$enable_click_chat = get_option( 'notifier_ctc_enable' );
		$btn_style         = get_option( 'notifier_ctc_button_style' );
		$click_chat_number = get_option( 'notifier_ctc_whatsapp_number' );

		if ( ! $btn_style ) {
			return;
		}

		if ( 'yes' === $enable_click_chat && ! empty( $click_chat_number ) && self::should_display_on_current_page() ) {
			$valid_styles = array_keys( Notifier_Backend::get_button_styles() );
			if ( 'default' !== $btn_style && in_array( $btn_style, $valid_styles, true ) ) {
				include_once NOTIFIER_PATH . 'templates/buttons/' . $btn_style . '.php';
			}
		}
	}
}
