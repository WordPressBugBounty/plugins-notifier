<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Woocommerce notifications
 *
 * @package    Wa_Notifier
 */
class Notifier_Woocommerce {

	/**
	 * Init.
	 */
	public static function init() {
		add_filter( 'notifier_notification_triggers', array( __CLASS__, 'add_notification_triggers'), 10 );
		add_filter( 'notifier_notification_merge_tags', array( __CLASS__, 'woocommerce_merge_tags') );
		add_filter( 'notifier_notification_recipient_fields', array( __CLASS__, 'woocommerce_recipient_fields') );
		add_action( 'woocommerce_review_order_before_submit', array( __CLASS__, 'add_checkout_optin_fields') );
		add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'notifier_save_checkout_field'));
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'render_order_optin_field' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'save_admin_order_meta' ), 10, 2 );
		
		add_action( 'wp_ajax_notifier_save_cart_abandonment_data', array(__CLASS__, 'notifier_save_cart_abandonment_data'));
		add_action( 'wp_ajax_nopriv_notifier_save_cart_abandonment_data', array(__CLASS__, 'notifier_save_cart_abandonment_data'));
		add_action( 'woocommerce_cart_updated', array( __CLASS__, 'notifier_update_cart_data_on_update'), 999);
		add_action( 'notifier_check_cart_abandonment', array( __CLASS__ , 'notifier_process_cart_abandonment' ), 10, 1);

		add_action( 'woocommerce_new_order', array( __CLASS__ , 'notifier_save_session_on_new_order' ), 10, 1);

		add_action( 'wp', array(__CLASS__, 'retrive_cart_data_from_url'));

		// v3: Action Scheduler handler for async schema push when new meta keys are discovered.
		add_action( 'notifier_push_updated_schema', array( __CLASS__, 'handle_push_updated_schema' ), 10, 1 );

		// v3: contact sync hooks — priority 100 so WooCommerce and other plugins have
		// already written all billing/user meta before we read it.
		add_action( 'user_register', array( __CLASS__, 'on_user_register' ), 100 );
		add_action( 'profile_update', array( __CLASS__, 'on_user_update' ), 100, 2 );
		add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'on_checkout_contact_sync' ), 100 );

		// v3: Action Scheduler handler for batch contact sync.
		add_action( 'notifier_wc_batch_contact_sync', array( __CLASS__, 'process_batch_contact_sync_page' ), 10, 3 );
	}

	/**
	 * Merge tag group types relevant to a WooCommerce order context.
	 * Used by both get_woo_notification_triggers() and build_wc_order_payload().
	 */
	public static function get_order_merge_tag_types() {
		return array( 'WordPress', 'WooCommerce', 'WooCommerce Order', 'WooCommerce Customer', 'WooCommerce Order Custom Meta', 'WooCommerce Customer User Meta' );
	}

	/**
	 * Recipient field group types relevant to a WooCommerce order context.
	 * Used by both get_woo_notification_triggers() and build_wc_order_payload().
	 */
	public static function get_order_recipient_types() {
		return array( 'WooCommerce', 'WooCommerce Order Custom Meta', 'WooCommerce Customer User Meta' );
	}

	/**
	 * Add Woocommerce notification triggers
	 */
	public static function get_woo_notification_triggers() {
		$merge_tag_types = self::get_order_merge_tag_types();
		$recipient_types = self::get_order_recipient_types();
		$triggers = array (
			array(
				'id'			=> 'woo_order_new',
				'label' 		=> 'New order is placed',
				'description'	=> 'Trigger notification when a new order is placed.',
				'merge_tags' 	=> Notifier_Notification_Merge_Tags::get_merge_tags($merge_tag_types),
				'recipient_fields'	=> Notifier_Notification_Merge_Tags::get_recipient_fields($recipient_types),
				'action'		=> array(
					'hook'		=> 'woocommerce_new_order',
					'callback' 	=> function ( $order_id ){
						if (!self::maybe_send_notification($order_id)) {
							return;
						}

						$args = array (
							'object_type' 	=> 'order',
							'object_id'		=> $order_id
						);
						Notifier_Notification_Triggers::send_trigger_request('woo_order_new', $args);
					}
				)
			),
			array(
				'id'			=> 'woo_order_new_cod',
				'label' 		=> 'New order is placed with COD payment method',
				'description'	=> 'Trigger notification when a new order is placed with Cash on Delivery payment method selected.',
				'merge_tags' 	=> Notifier_Notification_Merge_Tags::get_merge_tags($merge_tag_types),
				'recipient_fields'	=> Notifier_Notification_Merge_Tags::get_recipient_fields($recipient_types),
				'action'		=> array(
					'hook'		=> 'woocommerce_new_order',
					'callback' 	=> function ( $order_id ){
						if (!self::maybe_send_notification($order_id)) {
							return;
						}

						$args = array (
							'object_type' 	=> 'order',
							'object_id'		=> $order_id
						);
						$order = new WC_Order( $order_id );
						$method = $order->get_payment_method();
						if('cod' != $method){
							return;
						}
						Notifier_Notification_Triggers::send_trigger_request('woo_order_new_cod', $args);
					}
				)
			),
			array(
				'id'            => 'woocommerce_cart_abandonment',
				'label'         => 'Cart is abandoned',
				'description'   => 'Trigger notification when cart is abandoned as per your setup in the Settings > WooCommerce tab.',
				'merge_tags'    => Notifier_Notification_Merge_Tags::get_merge_tags(array('WooCommerce', 'WooCommerce Cart Abandonment')),
				'recipient_fields' => Notifier_Notification_Merge_Tags::get_recipient_fields(array('WooCommerce Cart Abandonment')),
				'action'        => array(
					'hook'      => 'notifier_process_abandoned_cart',
					'callback'  => function ( $checkout_details ) {
						$checkout_details = (array) $checkout_details;
						Notifier_Notification_Triggers::send_trigger_request( 'woocommerce_cart_abandonment', $checkout_details );
					}
				)
			),
		);

		$statuses = wc_get_order_statuses();
		foreach($statuses as $key => $status){
			$status_slug = str_replace('wc-','', $key);
			if(in_array($status_slug, array('checkout-draft'))){
				continue;
			}
			$trigger_id = 'woo_order_' . $status_slug;
			$triggers[] = array(
				'id'			=> $trigger_id,
				'label' 		=> 'Order status changes to ' . $status,
				'description'	=> 'Trigger notification when status of an order changes to ' . $status . '.',
				'merge_tags' 	=> Notifier_Notification_Merge_Tags::get_merge_tags($merge_tag_types),
				'recipient_fields'	=> Notifier_Notification_Merge_Tags::get_recipient_fields($recipient_types),
				'action'		=> array(
					'hook'		=> 'woocommerce_order_status_' . $status_slug,
					'callback' 	=> function ( $order_id ) use ($trigger_id) {
						if (!self::maybe_send_notification($order_id)) {
							return;
						}
						
						$args = array (
							'object_type' 	=> 'order',
							'object_id'		=> $order_id
						);
						Notifier_Notification_Triggers::send_trigger_request($trigger_id, $args);
					}
				)
			);
		}

		return $triggers;
	}

	/**
	 * Add notification trigger Woocommerce
	 */
	public static function add_notification_triggers ($triggers) {
		$triggers['WooCommerce'] = self::get_woo_notification_triggers();
		return $triggers;
	}

	/**
	 * Get Woocommerce merge tags
	 */
	public static function woocommerce_merge_tags($merge_tags) {
		$merge_tags['WooCommerce'] = array(
			array(
				'id' 			=> 'woo_store_url',
				'label' 		=> 'Shop page url',
				'preview_value' => 'https://example.com/shop/',
				'return_type'	=> 'text',
				'source'		=> 'static',
				'value'			=> function ( $bulk_data ) {
					return (string) get_permalink( wc_get_page_id( 'shop' ) );
				}
			),
			array(
				'id' 			=> 'woo_my_account_url',
				'label' 		=> 'My Account page url',
				'preview_value' => 'https://example.com/my-account/',
				'return_type'	=> 'text',
				'source'		=> 'static',
				'value'			=> function ( $bulk_data ) {
					return (string) get_permalink( wc_get_page_id( 'myaccount' ) );
				}
			),
		);

		// WooCommerce Order fields — source/path maps directly to get_base_data() keys.
		// Fields with custom formatting use a 'value' closure receiving $bulk_data.
		$woo_fields = array();
		$woo_fields['WooCommerce Order'] = array(
			'id' => array(
				'preview' => '123',
				'label'   => 'Order ID',
				'source'  => 'base',
				'path'    => 'id',
			),
			'subtotal_to_display' => array(
				'preview' => '$50.00',
				'label'   => 'Order subtotal',
				'source'  => 'computed',
				'value'   => function ( $bulk_data ) {
					return html_entity_decode( $bulk_data['order']->get_subtotal_to_display() );
				},
			),
			'coupon_codes' => array(
				'preview' => 'OFF50',
				'label'   => 'Order coupon codes',
				'source'  => 'coupons',
				'value'   => function ( $bulk_data ) {
					$codes = array();
					foreach ( $bulk_data['coupons'] as $item ) {
						$codes[] = $item->get_code();
					}
					return implode( ', ', $codes );
				},
			),
			'discount_to_display' => array(
				'preview' => '$5.00',
				'label'   => 'Order discount amount',
				'source'  => 'computed',
				'value'   => function ( $bulk_data ) {
					return html_entity_decode( $bulk_data['order']->get_discount_to_display() );
				},
			),
			'shipping_method' => array(
				'preview' => 'Flat rate',
				'label'   => 'Order shipping method',
				'source'  => 'shipping',
				'value'   => function ( $bulk_data ) {
					$methods = array();
					foreach ( $bulk_data['shipping'] as $item ) {
						$methods[] = $item->get_method_title();
					}
					return implode( ', ', $methods );
				},
			),
			'shipping_total' => array(
				'preview' => '$2.50',
				'label'   => 'Order shipping amount',
				'source'  => 'base',
				'path'    => 'shipping_total',
				'value'   => function ( $bulk_data ) {
					$currency = $bulk_data['base']['currency'];
					return html_entity_decode( wc_price( $bulk_data['base']['shipping_total'], array( 'currency' => $currency ) ) );
				},
			),
			'total_tax' => array(
				'preview' => '$5.00',
				'label'   => 'Order tax amount',
				'source'  => 'base',
				'path'    => 'total_tax',
				'value'   => function ( $bulk_data ) {
					$currency = $bulk_data['base']['currency'];
					return html_entity_decode( wc_price( $bulk_data['base']['total_tax'], array( 'currency' => $currency ) ) );
				},
			),
			'formatted_order_total' => array(
				'preview' => '$50.00',
				'label'   => 'Order total amount',
				'source'  => 'computed',
				'value'   => function ( $bulk_data ) {
					return html_entity_decode( $bulk_data['order']->get_formatted_order_total() );
				},
			),
			'payment_method_title' => array(
				'preview' => 'Cash on delivery',
				'label'   => 'Order payment method',
				'source'  => 'base',
				'path'    => 'payment_method_title',
			),
			'checkout_payment_url' => array(
				'preview' => 'https://example.com/checkout/order-received/123/?key=wc_order_abcdef',
				'label'   => 'Order checkout payment URL',
				'source'  => 'computed',
				'value'   => function ( $bulk_data ) {
					return $bulk_data['order']->get_checkout_payment_url();
				},
			),
			'checkout_order_received_url' => array(
				'preview' => 'https://example.com/checkout/order-received/123/?key=wc_order_abcdef',
				'label'   => 'Order thank you page URL',
				'source'  => 'computed',
				'value'   => function ( $bulk_data ) {
					return $bulk_data['order']->get_checkout_order_received_url();
				},
			),
			'view_order_url' => array(
				'preview' => 'https://example.com/my-account/view-order/123/',
				'label'   => 'Order view URL',
				'source'  => 'computed',
				'value'   => function ( $bulk_data ) {
					return $bulk_data['order']->get_view_order_url();
				},
			),
			'status' => array(
				'preview' => 'Processing',
				'label'   => 'Order status',
				'source'  => 'base',
				'path'    => 'status',
			),
			'first_product_image' => array(
				'preview'     => '',
				'label'       => 'Order first item featured image',
				'return_type' => 'image',
				'source'      => 'items',
				'value'       => function ( $bulk_data ) {
					foreach ( $bulk_data['items'] as $item ) {
						$product = $item->get_product();
						if ( $product ) {
							$image_id = $product->get_image_id();
							return $image_id ? wp_get_attachment_url( $image_id ) : wc_placeholder_img_src();
						}
						break;
					}
					return wc_placeholder_img_src();
				},
			),
			'products_list' => array(
				'preview'     => '',
				'label'       => 'Order products list',
				'return_type' => 'text',
				'source'      => 'items',
				'value'       => function ( $bulk_data ) {
					$parts = array();
					$currency = $bulk_data['base']['currency'];
					foreach ( $bulk_data['items'] as $item ) {
						$parts[] = sanitize_textarea_field(
							$item->get_name() . ' x ' . $item->get_quantity() . ' (' . wc_price( $item->get_total(), array( 'currency' => $currency ) ) . ')'
						);
					}
					return implode( ', ', $parts );
				},
			),
			'order_first_item_name' => array(
				'preview'     => '',
				'label'       => 'Order first item name',
				'return_type' => 'text',
				'source'      => 'items',
				'value'       => function ( $bulk_data ) {
					foreach ( $bulk_data['items'] as $item ) {
						return $item->get_name();
					}
					return '';
				},
			),
			'order_first_item_price' => array(
				'preview'     => '',
				'label'       => 'Order first item price',
				'return_type' => 'text',
				'source'      => 'items',
				'value'       => function ( $bulk_data ) {
					$currency = $bulk_data['base']['currency'];
					foreach ( $bulk_data['items'] as $item ) {
						$product = $item->get_product();
						if ( $product ) {
							return html_entity_decode( wc_price( $product->get_price(), array( 'currency' => $currency ) ) );
						}
						break;
					}
					return '';
				},
			),
			'order_first_item_total' => array(
				'preview'     => '',
				'label'       => 'Order first item total',
				'return_type' => 'text',
				'source'      => 'items',
				'value'       => function ( $bulk_data ) {
					$currency = $bulk_data['base']['currency'];
					foreach ( $bulk_data['items'] as $item ) {
						return html_entity_decode( wc_price( $item->get_total(), array( 'currency' => $currency ) ) );
					}
					return '';
				},
			),
		);

		$woo_fields['WooCommerce Customer'] = array(
			'billing_first_name' => array(
				'preview' => 'John',
				'source'  => 'base',
				'path'    => 'billing.first_name',
			),
			'billing_last_name' => array(
				'preview' => 'Doe',
				'source'  => 'base',
				'path'    => 'billing.last_name',
			),
			'billing_company' => array(
				'preview' => 'ABC Inc',
				'source'  => 'base',
				'path'    => 'billing.company',
			),
			'billing_address_1' => array(
				'preview' => '123, XYZ Street',
				'source'  => 'base',
				'path'    => 'billing.address_1',
			),
			'billing_address_2' => array(
				'preview' => 'XYZ Avenue',
				'source'  => 'base',
				'path'    => 'billing.address_2',
			),
			'billing_city' => array(
				'preview' => 'New York',
				'source'  => 'base',
				'path'    => 'billing.city',
			),
			'billing_postcode' => array(
				'preview' => '12345',
				'source'  => 'base',
				'path'    => 'billing.postcode',
			),
			'billing_state' => array(
				'preview' => 'NY',
				'source'  => 'base',
				'path'    => 'billing.state',
				'value'   => function ( $bulk_data ) {
					$country = $bulk_data['base']['billing']['country'];
					$state   = $bulk_data['base']['billing']['state'];
					return WC()->countries->states[ $country ][ $state ] ?? $state;
				},
			),
			'billing_country' => array(
				'preview' => 'US',
				'source'  => 'base',
				'path'    => 'billing.country',
				'value'   => function ( $bulk_data ) {
					$code = $bulk_data['base']['billing']['country'];
					return WC()->countries->countries[ $code ] ?? $code;
				},
			),
			'billing_email' => array(
				'preview' => 'john@example.com',
				'source'  => 'base',
				'path'    => 'billing.email',
			),
			'billing_phone' => array(
				'preview' => '987 654 321',
				'source'  => 'base',
				'path'    => 'billing.phone',
				'value'   => function ( $bulk_data ) {
					$phone   = $bulk_data['base']['billing']['phone'] ?? '';
					$country = $bulk_data['base']['billing']['country'] ?? '';
					return html_entity_decode( sanitize_text_field( self::get_formatted_phone_number( $phone, $country ) ) );
				},
			),
			'formatted_billing_address' => array(
				'preview' => '',
				'label'   => 'Complete billing address',
				'source'  => 'computed',
				'value'   => function ( $bulk_data ) {
					return str_replace( '<br/>', ', ', $bulk_data['order']->get_formatted_billing_address() );
				},
			),
			'shipping_first_name' => array(
				'preview' => 'John',
				'source'  => 'base',
				'path'    => 'shipping.first_name',
			),
			'shipping_last_name' => array(
				'preview' => 'Doe',
				'source'  => 'base',
				'path'    => 'shipping.last_name',
			),
			'shipping_company' => array(
				'preview' => 'ABC Inc',
				'source'  => 'base',
				'path'    => 'shipping.company',
			),
			'shipping_address_1' => array(
				'preview' => '123, XYZ Street',
				'source'  => 'base',
				'path'    => 'shipping.address_1',
			),
			'shipping_address_2' => array(
				'preview' => 'XYZ Avenue',
				'source'  => 'base',
				'path'    => 'shipping.address_2',
			),
			'shipping_city' => array(
				'preview' => 'New York',
				'source'  => 'base',
				'path'    => 'shipping.city',
			),
			'shipping_postcode' => array(
				'preview' => '12345',
				'source'  => 'base',
				'path'    => 'shipping.postcode',
			),
			'shipping_state' => array(
				'preview' => 'NY',
				'source'  => 'base',
				'path'    => 'shipping.state',
				'value'   => function ( $bulk_data ) {
					$country = $bulk_data['base']['shipping']['country'];
					$state   = $bulk_data['base']['shipping']['state'];
					return WC()->countries->states[ $country ][ $state ] ?? $state;
				},
			),
			'shipping_country' => array(
				'preview' => 'US',
				'source'  => 'base',
				'path'    => 'shipping.country',
				'value'   => function ( $bulk_data ) {
					$code = $bulk_data['base']['shipping']['country'];
					return WC()->countries->countries[ $code ] ?? $code;
				},
			),
			'shipping_phone' => array(
				'preview' => '987 654 321',
				'source'  => 'base',
				'path'    => 'shipping.phone',
				'value'   => function ( $bulk_data ) {
					$phone   = $bulk_data['base']['shipping']['phone'] ?? '';
					$country = $bulk_data['base']['shipping']['country'] ?? '';
					return html_entity_decode( sanitize_text_field( self::get_formatted_phone_number( $phone, $country ) ) );
				},
			),
			'formatted_shipping_address' => array(
				'preview' => '',
				'label'   => 'Complete shipping address',
				'source'  => 'computed',
				'value'   => function ( $bulk_data ) {
					return str_replace( '<br/>', ', ', $bulk_data['order']->get_formatted_shipping_address() );
				},
			),
		);

		foreach ( $woo_fields as $woo_field_key => $woo_field ) {
			foreach ( $woo_field as $field => $field_data ) {
				$label = isset( $field_data['label'] ) ? $field_data['label'] : ucfirst( str_replace( '_', ' ', $field ) );
				$merge_tags[ $woo_field_key ][] = array(
					'id'            => 'woo_order_' . $field,
					'label'         => $label,
					'preview_value' => isset( $field_data['preview'] ) ? $field_data['preview'] : '',
					'return_type'   => isset( $field_data['return_type'] ) ? $field_data['return_type'] : 'text',
					'source'        => isset( $field_data['source'] ) ? $field_data['source'] : 'base',
					'path'          => isset( $field_data['path'] ) ? $field_data['path'] : null,
					'value'         => isset( $field_data['value'] ) ? $field_data['value'] : null,
				);
			}
		}

		// Order meta keys — read from persistent option (populated on connect/sync).
		$order_meta_keys = Notifier_Notification_Merge_Tags::get_custom_meta_keys( 'shop_order' );
		if ( ! empty( $order_meta_keys ) ) {
			foreach ( $order_meta_keys as $order_meta_key ) {
				$merge_tags['WooCommerce Order Custom Meta'][] = array(
					'id'            => 'woo_order_meta_' . $order_meta_key,
					'label'         => $order_meta_key,
					'preview_value' => '123',
					'return_type'   => 'all',
					'source'        => 'meta',
					'path'          => $order_meta_key,
				);
			}
		}

		// WooCommerce customer user meta — read from persistent option.
		$user_meta_keys = Notifier_Notification_Merge_Tags::get_user_meta_keys();
		if ( ! empty( $user_meta_keys ) ) {
			foreach ( $user_meta_keys as $user_meta_key ) {
				$merge_tags['WooCommerce Customer User Meta'][] = array(
					'id'            => 'woo_customer_meta_' . $user_meta_key,
					'label'         => $user_meta_key,
					'preview_value' => '123',
					'return_type'   => 'all',
					'source'        => 'user_meta',
					'path'          => $user_meta_key,
				);
			}
		}

		$merge_tags['WooCommerce Cart Abandonment'] = array(
			array(
				'id'            => 'wca_cart_products_list',
				'label'         => 'Abandoned cart products list',
				'preview_value' => '',
				'return_type'   => 'text',
				'value'         => function ($cart_details) {
                    return $cart_details ? self::get_comma_separated_products(maybe_unserialize($cart_details['cart_data'])) : '';
				}
			),
		array(
			'id'            => 'wca_cart_total',
			'label'         => 'Abandoned cart total',
			'preview_value' => '',
			'return_type'   => 'text',
			'value'         => function ($cart_details) {
				return $cart_details['cart_total'] ?? '';
			}
		),
			array(
				'id'            => 'wca_cart_datetime',
				'label'         => 'Abandoned cart datetime',
				'preview_value' => '',
				'return_type'   => 'text',
				'value'         => function ($cart_details) {
                    return $cart_details ? $cart_details['created_time'] : '';
				}
			),
			array(
				'id'            => 'wca_checkout_recovery_url',
				'label'         => 'Abandoned cart checkout recovery URL',
				'preview_value' => '',
				'return_type'   => 'text',
				'value'         => function ($cart_details) {
					if ($cart_details && isset($cart_details['session_key'])) {
						$session_key = sanitize_key($cart_details['session_key']);
						$checkout_url = wc_get_checkout_url();
						$recovery_url = add_query_arg(['wano_session' => $session_key], $checkout_url);
						return esc_url($recovery_url);
					}
					return wc_get_checkout_url();
				}
			),
			array(
				'id'            => 'wca_cart_recovery_url',
				'label'         => 'Abandoned cart recovery URL',
				'preview_value' => '',
				'return_type'   => 'text',
				'value'         => function ($cart_details) {
					if ($cart_details && isset($cart_details['session_key'])) {
						$session_key = sanitize_key($cart_details['session_key']);
						$cart_url = wc_get_cart_url();
						$recovery_url = add_query_arg(['wano_session' => $session_key], $cart_url);
						return esc_url($recovery_url);
					}
					return wc_get_cart_url();
				}
			),
			array(
				'id'            => 'wca_cart_session_key',
				'label'         => 'Abandoned cart recovery key',
				'preview_value' => '',
				'return_type'   => 'text',
				'value'         => function ($cart_details) {
					if ($cart_details && isset($cart_details['session_key'])) {
						$session_key = sanitize_key($cart_details['session_key']);
						return $session_key;
					}
					return '';
				}
			),
			array(
				'id'            => 'wca_cart_customer_email',
				'label'         => 'Billing email',
				'preview_value' => '',
				'return_type'   => 'text',
				'value'         => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_content']) : [];
					return $customer_data['billing_email'] ?? '';
				}
			),
			array(
				'id'            => 'wca_cart_applied_coupon',
				'label'         => 'Coupon details',
				'preview_value' => '',
				'return_type'   => 'text',
				'value'         => function ($cart_details) {
				$coupon_data = $cart_details ? maybe_unserialize($cart_details['coupon_content'] ?? '') : [];
				return $coupon_data ? implode(', ', $coupon_data) : '';
			}
			),			
		);

		$wca_other_fields = array(
			'first_name' => array(
				'preview' => 'John',
				'label' => 'Billing first name',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					return $customer_data['billing_first_name'] ?? '';
				}
			),
			'last_name' => array(
				'preview' => 'Doe',
				'label' => 'Billing last name',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					return $customer_data['billing_last_name'] ?? '';
				}
			),
			'billing_company' => array(
				'preview' => 'ABC Inc',
				'label' => 'Billing company',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					return $customer_data['billing_company'] ?? '';
				}
			),
			'billing_address_1' => array(
				'preview' => '123, XYZ Street',
				'label' => 'Billing address line 1',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					return $customer_data['billing_address_1'] ?? '';
				}
			),
			'billing_address_2' => array(
				'preview' => 'XYZ Avenue',
				'label' => 'Billing address line 2',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					return $customer_data['billing_address_2'] ?? '';
				}
			),
			'billing_city' => array(
				'preview' => 'New York',
				'label' => 'Billing city',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					return $customer_data['billing_city'] ?? '';
				}
			),
			'billing_postcode' => array(
				'preview' => '12345',
				'label' => 'Billing postcode',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					return $customer_data['billing_postcode'] ?? '';
				}
			),
			'billing_state' => array(
				'preview' => 'NY',
				'label' => 'Billing state',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					$state_code = $customer_data['billing_state'] ?? '';
					$country_code = $customer_data['billing_country'] ?? '';
					return $state_code && $country_code
						? WC()->countries->states[$country_code][$state_code] ?? ''
						: '';
				}
			),
			'billing_country' => array(
				'preview' => 'US',
				'label' => 'Billing country',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					$country_code = $customer_data['billing_country'] ?? '';
					return $country_code
						? WC()->countries->countries[$country_code] ?? ''
						: '';
				}
			),
			'billing_phone' => array(
				'preview' => '987 654 321',
				'label' => 'Billing phone number',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					return $customer_data['billing_phone'] ?? '';
				}
			),
			'billing_email' => array(
				'preview' => 'john@example.com',
				'label' => 'Billing email',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					return $customer_data['billing_email'] ?? '';
				}
			),
			'shipping_first_name' => array(
				'preview' => 'John',
				'label' => 'Shipping first name',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					return $customer_data['shipping_first_name'] ?? '';
				}
			),
			'shipping_last_name' => array(
				'preview' => 'Doe',
				'label' => 'Shipping last name',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					return $customer_data['shipping_last_name'] ?? '';
				}
			),
			'shipping_company' => array(
				'preview' => 'ABC Inc',
				'label' => 'Shipping company',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					return $customer_data['shipping_company'] ?? '';
				}
			),
			'shipping_address_1' => array(
				'preview' => '123, XYZ Street',
				'label' => 'Shipping address line 1',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					return $customer_data['shipping_address_1'] ?? '';
				}
			),
			'shipping_address_2' => array(
				'preview' => 'XYZ Avenue',
				'label' => 'Shipping address line 2',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					return $customer_data['shipping_address_2'] ?? '';
				}
			),
			'shipping_city' => array(
				'preview' => 'New York',
				'label' => 'Shipping city',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					return $customer_data['shipping_city'] ?? '';
				}
			),
			'shipping_postcode' => array(
				'preview' => '12345',
				'label' => 'Shipping postcode',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					return $customer_data['shipping_postcode'] ?? '';
				}
			),
			'shipping_state' => array(
				'preview' => 'NY',
				'label' => 'Shipping state',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					$state_code = $customer_data['shipping_state'] ?? '';
					$country_code = $customer_data['shipping_country'] ?? '';
					return $state_code && $country_code
						? WC()->countries->states[$country_code][$state_code] ?? ''
						: '';
				}
			),
			'shipping_country' => array(
				'preview' => 'US',
				'label' => 'Shipping country',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					$country_code = $customer_data['shipping_country'] ?? '';
					return $country_code
						? WC()->countries->countries[$country_code] ?? ''
						: '';
				}
			),
			'shipping_phone' => array(
				'preview' => '987 654 321',
				'label' => 'Shipping phone number',
				'value' => function ($cart_details) {
					$customer_data = $cart_details ? maybe_unserialize($cart_details['customer_data']) : [];
					return $customer_data['shipping_phone'] ?? '';
				}
			),			
		);

        foreach ($wca_other_fields as $field => $field_data) {
            $merge_tags['WooCommerce Cart Abandonment'][] = array(
                'id'            => 'wca_cart_customer_' . $field,
                'label'         => $field_data['label'],
                'preview_value' => $field_data['preview'],
                'return_type'   => 'text',
                'value'         => $field_data['value']
            );
        }

		return $merge_tags;
	}

	/*
	 * Add recipient fields for WooCommerce
	 */
	public static function woocommerce_recipient_fields( $recipient_fields ) {
		$recipient_fields['WooCommerce'] = array(
			array(
				'id'     => 'woo_order_billing_phone',
				'label'  => 'Billing phone number',
				'source' => 'base',
				'path'   => 'billing.phone',
				'value'  => function ( $bulk_data ) {
					$phone   = $bulk_data['base']['billing']['phone'];
					$country = $bulk_data['base']['billing']['country'];
					return html_entity_decode( sanitize_text_field( self::get_formatted_phone_number( $phone, $country ) ) );
				},
			),
			array(
				'id'     => 'woo_order_shipping_phone',
				'label'  => 'Shipping phone number',
				'source' => 'base',
				'path'   => 'shipping.phone',
				'value'  => function ( $bulk_data ) {
					$phone   = $bulk_data['base']['shipping']['phone'];
					$country = $bulk_data['base']['shipping']['country'];
					return html_entity_decode( sanitize_text_field( self::get_formatted_phone_number( $phone, $country ) ) );
				},
			),
		);

		// Order meta keys — read from persistent option.
		$order_meta_keys = Notifier_Notification_Merge_Tags::get_custom_meta_keys( 'shop_order' );
		if ( ! empty( $order_meta_keys ) ) {
			foreach ( $order_meta_keys as $order_meta_key ) {
				$recipient_fields['WooCommerce Order Custom Meta'][] = array(
					'id'            => 'woo_order_meta_' . $order_meta_key,
					'label'         => $order_meta_key,
					'preview_value' => '123',
					'return_type'   => 'text',
					'source'        => 'meta',
					'path'          => $order_meta_key,
				);
			}
		}

		// WooCommerce customer user meta — read from persistent option.
		$user_meta_keys = Notifier_Notification_Merge_Tags::get_user_meta_keys();
		if ( ! empty( $user_meta_keys ) ) {
			foreach ( $user_meta_keys as $user_meta_key ) {
				$recipient_fields['WooCommerce Customer User Meta'][] = array(
					'id'            => 'woo_customer_meta_' . $user_meta_key,
					'label'         => $user_meta_key,
					'preview_value' => '123',
					'return_type'   => 'text',
					'source'        => 'user_meta',
					'path'          => $user_meta_key,
				);
			}
		}

		$recipient_fields['WooCommerce Cart Abandonment'] = array(
			array(
				'id'			=> 'wca_cart_customer_billing_phone',
				'label'			=> 'Customer billing phone number',
				'value'			=> function ($cart_details) {					
					if(empty($cart_details['phone_number'])){
						return '';
					}
					$customer_data = !empty($cart_details['customer_data']) ? maybe_unserialize($cart_details['customer_data']) : [];
					$country_code = $customer_data['billing_country'] ?? '';
					$phone_number = self::get_formatted_phone_number($cart_details['phone_number'], $country_code);
					return html_entity_decode(sanitize_text_field($phone_number));
				}
			)			
		);

		return $recipient_fields;
	}

	/**
	 * Get phone number with country extension code
	 */
	public static function get_formatted_phone_number( $phone_number, $country_code ) {
		// If already starts with + and matches international format, return as-is
		if ( strpos($phone_number, '+') === 0 && notifier_validate_phone_number($phone_number) ) {
			return $phone_number;
		}
		$phone_number = notifier_sanitize_phone_number( $phone_number );
		$phone_number = ltrim($phone_number, '0');
		if ( in_array( $country_code, array( 'US', 'CA' ), true ) ) {
			$calling_code = '+1';
			$phone_number = ltrim( $phone_number, '+1' );
		} else {
			$calling_code = WC()->countries->get_country_calling_code( $country_code );
			$calling_code = is_array( $calling_code ) ? $calling_code[0] : $calling_code;
			if ( $calling_code ) {
				$phone_number = str_replace( $calling_code, '', preg_replace( '/^0/', '', $phone_number ) );
			}
		}
		$phone_number = ltrim($phone_number, '0');
		return $calling_code . $phone_number;
	}

	/**
	 * Add checkbox field on checkout page
	 */	
	public static function add_checkout_optin_fields() {
		if ('yes' === get_option( NOTIFIER_PREFIX . 'enable_opt_in_checkbox_checkout')) {
			$checkbox_text = get_option( NOTIFIER_PREFIX . 'checkout_opt_in_checkbox_text');
			if (empty($checkbox_text)) {
				$checkbox_text = 'Receive updates on WhatsApp';
			}
		
			woocommerce_form_field( NOTIFIER_PREFIX . 'whatsapp_opt_in', array(
				'type'          => 'checkbox',
				'class'         => array('form-row-wide'),
				'label'         => $checkbox_text,
			),'');
		}
	}

	/**
	 * Hook to save the custom field data
	 */
	public static function notifier_save_checkout_field($order_id) {
		$opt_in = isset( $_POST[ NOTIFIER_PREFIX . 'whatsapp_opt_in' ] ) ? sanitize_text_field( wp_unslash( $_POST[ NOTIFIER_PREFIX . 'whatsapp_opt_in' ] ) ) : '';
		if ( !empty($opt_in) ) {
			$order = wc_get_order( $order_id );
			$order->update_meta_data( NOTIFIER_PREFIX . 'whatsapp_opt_in', $opt_in );
    		$order->save();
		}
	}

	/**
	 * Render opt-in checkbox after billing address on order edit page.
	 * Hook passes a WC_Order object (works with both HPOS and legacy).
	 */
	public static function render_order_optin_field( $order ) {
		if ( 'yes' !== get_option( NOTIFIER_PREFIX . 'enable_opt_in_checkbox_checkout' ) ) {
			return;
		}

		if ( ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order->ID );
		}
		if ( ! $order ) {
			return;
		}

		$checkbox_text = get_option( NOTIFIER_PREFIX . 'checkout_opt_in_checkbox_text' );
		if ( empty( $checkbox_text ) ) {
			$checkbox_text = 'Receive updates on WhatsApp';
		}

		$opt_in  = $order->get_meta( NOTIFIER_PREFIX . 'whatsapp_opt_in' );

		woocommerce_wp_checkbox( array(
			'id'            => NOTIFIER_PREFIX . 'whatsapp_opt_in',
			'label'         => '<strong>' . __( 'WANotifier', 'notifier' ) . '</strong>',
			'description'   => esc_html( $checkbox_text ),
			'value'         => $opt_in,
			'cbvalue'       => '1',
			'wrapper_class' => 'form-field-wide',
		) );
	}

	/**
	 * Hook to save the custom field data on order page
	 */
	public static function save_admin_order_meta( $post_id, $post ) {
		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		$order = wc_get_order( $post_id );
		if ( ! $order ) {
			return;
		}

		$opt_in = isset( $_POST[ NOTIFIER_PREFIX . 'whatsapp_opt_in' ] ) ? '1' : '';
		$order->update_meta_data( NOTIFIER_PREFIX . 'whatsapp_opt_in', $opt_in );
		$order->save();
	}

    /**
     * Check if the notification should be sent based on global and user opt-in settings.
     */
    public static function maybe_send_notification( $order_id ) {
        if ('yes' !== get_option( NOTIFIER_PREFIX . 'enable_opt_in_checkbox_checkout' )) {
            return true;
        }

		$order = wc_get_order( $order_id );
		$opt_in = $order->get_meta( NOTIFIER_PREFIX . 'whatsapp_opt_in' );
        return '1' === $opt_in || true === $opt_in;
    }

	/**
	 * Handle checkout data updates.
	 */
	public static function notifier_save_cart_abandonment_data() {
		check_ajax_referer('notifier_save_cart_abandonment_data', 'security');

		// Check if cart abandonment tracking is enabled
		if ('yes' !== get_option('notifier_enable_abandonment_cart_tracking', 'no')) {
			wp_send_json(array('error' => true, 'message' => 'Tracking not enabled.'));
		}

		global $wpdb;
		$table_name = $wpdb->prefix . NOTIFIER_CART_ABANDONMENT_TABLE;

		// Ensure WooCommerce session is available
		if (!WC()->session || !WC()->session->has_session()) {
			wp_send_json(array('error' => true, 'message' => 'WooCommerce session not initialized.'));
		}

		$cart_data = maybe_serialize(WC()->session->get('cart')); // Serialized cart data.
		$cart_total = floatval(preg_replace('/[^\d.]/', '', WC()->cart->get_total('edit'))); // Cart total amount.
		$applied_coupons = WC()->cart->get_applied_coupons();// Fetch applied coupon details
		$serialized_coupons = maybe_serialize($applied_coupons);

		$session_key = WC()->session->get('notifier_session_key');

		$customer_data = array(
			'billing_first_name'    => sanitize_text_field($_POST['billing_first_name'] ?? WC()->checkout()->get_value('billing_first_name')),
			'billing_last_name'     => sanitize_text_field($_POST['billing_last_name'] ?? WC()->checkout()->get_value('billing_last_name')),
			'billing_company'       => sanitize_text_field($_POST['billing_company'] ?? WC()->checkout()->get_value('billing_company')),
			'billing_address_1'     => sanitize_textarea_field($_POST['billing_address_1'] ?? WC()->checkout()->get_value('billing_address_1')),
			'billing_address_2'     => sanitize_textarea_field($_POST['billing_address_2'] ?? WC()->checkout()->get_value('billing_address_2')),
			'billing_city'          => sanitize_text_field($_POST['billing_city'] ?? WC()->checkout()->get_value('billing_city')),
			'billing_postcode'      => sanitize_text_field($_POST['billing_postcode'] ?? WC()->checkout()->get_value('billing_postcode')),
			'billing_state'         => sanitize_text_field($_POST['billing_state'] ?? WC()->checkout()->get_value('billing_state')),
			'billing_country'       => sanitize_text_field($_POST['billing_country'] ?? WC()->checkout()->get_value('billing_country')),
			'billing_phone'         => sanitize_text_field($_POST['billing_phone'] ?? WC()->checkout()->get_value('billing_phone')),
			'billing_email'         => sanitize_email($_POST['billing_email'] ?? WC()->checkout()->get_value('billing_email')),
			'shipping_first_name'   => sanitize_text_field($_POST['shipping_first_name'] ?? WC()->checkout()->get_value('shipping_first_name')),
			'shipping_last_name'    => sanitize_text_field($_POST['shipping_last_name'] ?? WC()->checkout()->get_value('shipping_last_name')),
			'shipping_company'      => sanitize_text_field($_POST['shipping_company'] ?? WC()->checkout()->get_value('shipping_company')),
			'shipping_address_1'    => sanitize_textarea_field($_POST['shipping_address_1'] ?? WC()->checkout()->get_value('shipping_address_1')),
			'shipping_address_2'    => sanitize_textarea_field($_POST['shipping_address_2'] ?? WC()->checkout()->get_value('shipping_address_2')),
			'shipping_city'         => sanitize_text_field($_POST['shipping_city'] ?? WC()->checkout()->get_value('shipping_city')),
			'shipping_postcode'     => sanitize_text_field($_POST['shipping_postcode'] ?? WC()->checkout()->get_value('shipping_postcode')),
			'shipping_state'        => sanitize_text_field($_POST['shipping_state'] ?? WC()->checkout()->get_value('shipping_state')),
			'shipping_country'      => sanitize_text_field($_POST['shipping_country'] ?? WC()->checkout()->get_value('shipping_country')),
			'shipping_phone'        => sanitize_text_field($_POST['shipping_phone'] ?? WC()->checkout()->get_value('shipping_phone')),
		);

		$serialized_customer_data = maybe_serialize($customer_data);
		$phone_number = sanitize_text_field($customer_data['billing_phone']);

		// Handle case where phone number is missing
		if (empty($phone_number)) {
			if($session_key){
				as_unschedule_all_actions('notifier_check_cart_abandonment', array($session_key));
				$wpdb->delete($table_name, array('session_key' => sanitize_key($session_key)), array('%s'));
				WC()->session->set( 'notifier_session_key', null );
			}
			wp_send_json(array('error' => true, 'message' => 'phone number is empty.'));
		}

		if (WC()->cart->is_empty()) {
			if($session_key){
				as_unschedule_all_actions('notifier_check_cart_abandonment', array($session_key));
				$wpdb->delete($table_name, array('session_key' => sanitize_key($session_key)), array('%s'));
				WC()->session->set( 'notifier_session_key', null );
			}
			wp_send_json(array('error' => true, 'message' => 'cart is empty.'));
		}

		// Handle session ID
		if (!$session_key) {
			$session_key = notifier_generate_random_key(16); // Generate a unique session ID.
			WC()->session->set('notifier_session_key', $session_key);
		}

		// Check if an existing entry exists
		$existing_entry = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $table_name WHERE session_key = %s",
			$session_key
		));

		// If entry exists and is triggered, do not schedule action
		if ($existing_entry && $existing_entry->triggered) {
			wp_send_json(array('error' => true, 'message' => 'Cart abandonment already processed.' ));
		}

		// Fetch all active session_keys for this phone number
		$existing_session_keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT session_key FROM $table_name WHERE phone_number = %s AND triggered = 0 AND order_id = 0",
				$phone_number
			)
		);

		// Delete all other sessions except the current one
		if (!empty($existing_session_keys)) {
			foreach ($existing_session_keys as $old_session_key) {
				if ($session_key !== $old_session_key) {
					$wpdb->query(
						$wpdb->prepare(
							"DELETE FROM $table_name WHERE session_key = %s",
							$old_session_key
						)
					);
				}
			}
		}

		// Update or insert cart and customer data into the table
		if ($existing_entry) {
			// Update the existing entry
			$wpdb->update(
				$table_name,
				array(
					'phone_number'      => $phone_number,
					'cart_data'         => $cart_data,
					'cart_total'        => $cart_total,
					'coupon_data'       => $serialized_coupons,
					'customer_data'     => $serialized_customer_data,
					'updated_time'      => current_time('mysql'),
				),
				array('session_key' => sanitize_key($session_key)),
				array('%s', '%s', '%f', '%s', '%s', '%s'),
				array('%s')
			);
		} else {
			// Insert a new entry
			$wpdb->insert(
				$table_name,
				array(
					'session_key'       => sanitize_key($session_key),
					'phone_number'      => $phone_number,
					'cart_data'         => $cart_data,
					'cart_total'        => $cart_total,
					'coupon_data'       => $serialized_coupons,
					'customer_data'     => $serialized_customer_data,
					'created_time'      => current_time('mysql'),
					'updated_time'      => current_time('mysql'),
				),
				array('%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s')
			);
		}

		// Unschedule previous actions for this session
		as_unschedule_all_actions('notifier_check_cart_abandonment', array($session_key));

		// Schedule a new action if billing phone is available
		if ( !as_next_scheduled_action('notifier_check_cart_abandonment', array($session_key))) {
			$run_time_minutes = (int) get_option('notifier_abandonment_cart_cron_run_time', 20); // Default to 20 minutes if not set
			$run_time_seconds = $run_time_minutes * MINUTE_IN_SECONDS;
			as_schedule_single_action(time() + $run_time_seconds, 'notifier_check_cart_abandonment', array($session_key));
		}

		wp_send_json(array('error' => false, 'message' => 'Cart abandonment request has been successfully processed. ' ));
	}

	/**
	 * Handle cart updates data updates.
	 */
	public static function notifier_update_cart_data_on_update() {
		// Check if cart abandonment tracking is enabled
		if ('yes' !== get_option('notifier_enable_abandonment_cart_tracking', 'no')) {
			return;
		}

		// Prevent execution on the Thank You and order recieved page
		if (is_checkout() || is_wc_endpoint_url('order-received')) {
			return;
		}

		if (!WC()->session || !WC()->session->has_session()) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . NOTIFIER_CART_ABANDONMENT_TABLE;
		$cart_data = maybe_serialize(WC()->session->get('cart')); // Serialized cart data.
		$cart_total = floatval(preg_replace('/[^\d.]/', '', WC()->cart->get_total('edit'))); // Cart total amount.

		$session_key = WC()->session->get('notifier_session_key');

		//Check if an order is already linked to this session record
		$existing_order_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT order_id FROM $table_name WHERE session_key = %s",
				sanitize_key($session_key)
			)
		);

		// If an order exists, do NOT delete the record
		if (!empty($existing_order_id)) {
			as_unschedule_all_actions('notifier_check_cart_abandonment', array($session_key));
			WC()->session->set( 'notifier_session_key', null );
			return;
		}

		if (WC()->cart->is_empty()) {
			if($session_key){
				as_unschedule_all_actions('notifier_check_cart_abandonment', array($session_key));
				WC()->session->set( 'notifier_session_key', null );
			}
			return;
		}

		// If session_key is not set, create and save a new one
		if (!$session_key) {
			$session_key = notifier_generate_random_key(16); // Generate a 16-character session key
			WC()->session->set('notifier_session_key', $session_key);
		}

		// Check if an existing entry exists for the session_key
		$existing_entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT session_id FROM $table_name WHERE session_key = %s",
				$session_key
			)
		);

		if ($existing_entry) {
			// Update the existing entry with the new cart data
			$wpdb->update(
				$table_name,
				array(
					'cart_data'   => $cart_data,
					'cart_total'  => $cart_total,
					'updated_time'=> current_time('mysql'),
				),
				array('session_key' => $session_key),
				array('%s', '%f', '%s'),
				array('%s')
			);
		} else {
			// Insert a new entry for the session
			$wpdb->insert(
				$table_name,
				array(
					'session_key' => $session_key,
					'cart_data'   => $cart_data,
					'cart_total'  => $cart_total,
					'created_time'=> current_time('mysql'),
					'updated_time'=> current_time('mysql'),
				),
				array('%s', '%s', '%f', '%s', '%s')
			);
		}
	}

    /**
     * Fire when cart data get updated
     */
	public static function notifier_process_cart_abandonment($session_key) {
		// Check if cart abandonment tracking is enabled
		if ('yes' !== get_option('notifier_enable_abandonment_cart_tracking', 'no')) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . NOTIFIER_CART_ABANDONMENT_TABLE;

		// Check if the session data still exists in the DB
		$abandoned_cart_entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE session_key = %s AND triggered = 0 AND order_id = 0",
				$session_key
			),
			ARRAY_A
		);

		if (!empty($abandoned_cart_entry)) {
			// Fire the notifier process action
			do_action('notifier_process_abandoned_cart', $abandoned_cart_entry);

			// Mark the triggered to true
			$wpdb->update(
				$table_name,
				array('triggered' => 1),
				array('session_key' => $session_key),
				array('%d'),
				array('%s')
			);
			as_unschedule_all_actions('notifier_check_cart_abandonment', array(sanitize_key($session_key)));
		}
	}

    /**
     * Fetch comma separated products from cart
     */
	public static function get_comma_separated_products( $cart_data ) {
		$product_names = [];

		foreach ($cart_data as $item) {
			if (!isset($item['product_id'])) {
				continue; // Skip if product_id is missing
			}

			$product = wc_get_product($item['product_id']);
			if ($product) {
				$product_names[] = $product->get_name();
			}
		}

		// Return comma-separated product names
		return implode(', ', $product_names);
	}

    /**
     * retrive cart data and set from url param
     */
	public static function retrive_cart_data_from_url(){
		if(!is_checkout() && !is_cart()){
			return;
		}

		if (isset($_GET['wano_session'])) {
			global $wpdb;
			$table_name = $wpdb->prefix . NOTIFIER_CART_ABANDONMENT_TABLE;
			$session_key = sanitize_key($_GET['wano_session']);
			if (empty($session_key)) {
				return;
			}

			$cart_entry = $wpdb->get_row(
				$wpdb->prepare("SELECT cart_data, customer_data FROM $table_name WHERE session_key = %s", $session_key),
				ARRAY_A
			);

			if (!empty($cart_entry['cart_data'])) {
				WC()->cart->empty_cart();
				$cart_contents = maybe_unserialize($cart_entry['cart_data']);
				foreach ($cart_contents as $item_key => $item) {
					WC()->cart->add_to_cart($item['product_id'], $item['quantity'], $item['variation_id'], $item['variation']);
				}
			}
		}
	}

    // ========================================
    // v3 PAYLOAD BUILDER
    // ========================================

    /**
     * Build the full WooCommerce order payload for v3 trigger dispatch.
     *
     * Fetches all order data in bulk (one call per data type), then maps
     * each defined merge tag and recipient field using source/path or value closure.
     * Meta keys discovered here that are new are persisted and trigger an async schema push.
     *
     * @param int    $order_id   WooCommerce order ID.
     * @param string $trigger_id Trigger ID (e.g. 'woo_order_new').
     * @return array Payload with merge_tags_data and recipient_fields.
     */
    public static function build_wc_order_payload( $order_id, $trigger_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return array( 'merge_tags_data' => array(), 'recipient_fields' => array() );
        }

        $customer_id = $order->get_customer_id();

        // Single bulk fetch — all data in ~6 calls total.
        $bulk_data = array(
            'order'     => $order,
            'base'      => $order->get_base_data(),
            'items'     => $order->get_items( 'line_item' ),
            'shipping'  => $order->get_items( 'shipping' ),
            'coupons'   => $order->get_items( 'coupon' ),
            'fees'      => $order->get_items( 'fee' ),
            'meta'      => $order->get_meta_data(),
            'user_meta' => $customer_id ? get_user_meta( $customer_id ) : array(),
        );

        // Index meta by key for fast lookups.
        $meta_by_key = array();
        foreach ( $bulk_data['meta'] as $meta_item ) {
            if ( ! empty( $meta_item->key ) ) {
                $meta_by_key[ $meta_item->key ] = $meta_item->value;
            }
        }

        // Index user meta (get_user_meta returns arrays of values per key).
        $user_meta_by_key = array();
        foreach ( $bulk_data['user_meta'] as $key => $values ) {
            $user_meta_by_key[ $key ] = is_array( $values ) ? $values[0] : $values;
        }

        $merge_tags_data  = array();
        $recipient_fields = array();

        $all_merge_tags = Notifier_Notification_Merge_Tags::get_merge_tags( self::get_order_merge_tag_types() );
        foreach ( $all_merge_tags as $group => $tags ) {
            foreach ( $tags as $tag ) {
                if ( empty( $tag['id'] ) ) {
                    continue;
                }
                if ( 0 === strpos( $tag['id'], 'woo_order_meta_' ) || 0 === strpos( $tag['id'], 'woo_customer_meta_' ) ) {
                    continue;
                }
                $value = self::resolve_field_from_bulk( $tag, $bulk_data, $meta_by_key, $user_meta_by_key );
                if ( null !== $value ) {
                    $merge_tags_data[ $tag['id'] ] = html_entity_decode( sanitize_text_field( (string) $value ) );
                }
            }
        }

        $all_recipient_fields = Notifier_Notification_Merge_Tags::get_recipient_fields( self::get_order_recipient_types() );
        foreach ( $all_recipient_fields as $group => $fields ) {
            foreach ( $fields as $field ) {
                if ( empty( $field['id'] ) ) {
                    continue;
                }
                if ( 0 === strpos( $field['id'], 'woo_order_meta_' ) || 0 === strpos( $field['id'], 'woo_customer_meta_' ) ) {
                    continue;
                }
                $value = self::resolve_field_from_bulk( $field, $bulk_data, $meta_by_key, $user_meta_by_key );
                if ( null !== $value ) {
                    $recipient_fields[ $field['id'] ] = html_entity_decode( sanitize_text_field( (string) $value ) );
                }
            }
        }

        foreach ( $meta_by_key as $key => $value ) {
            $tag_id                        = 'woo_order_meta_' . $key;
            $merge_tags_data[ $tag_id ]    = is_array( $value ) || is_object( $value ) ? json_encode( $value ) : (string) $value;
            $recipient_fields[ $tag_id ]   = $merge_tags_data[ $tag_id ];
        }

        foreach ( $user_meta_by_key as $key => $value ) {
            $tag_id                        = 'woo_customer_meta_' . $key;
            $merge_tags_data[ $tag_id ]    = is_array( $value ) || is_object( $value ) ? json_encode( $value ) : (string) $value;
            $recipient_fields[ $tag_id ]   = $merge_tags_data[ $tag_id ];
        }

        // Live diff: detect new meta keys and persist + schedule async schema push.
        self::maybe_update_known_meta_keys( 'shop_order', array_keys( $meta_by_key ) );
        if ( $customer_id ) {
            self::maybe_update_known_meta_keys( '_user', array_keys( $user_meta_by_key ) );
        }

        return array(
            'merge_tags_data'  => $merge_tags_data,
            'recipient_fields' => $recipient_fields,
        );
    }

    /**
     * Resolve a single merge tag or recipient field value from pre-fetched bulk data.
     *
     * @param array $field_def     Field definition with source, path, and/or value closure.
     * @param array $bulk_data     Pre-fetched bulk data array.
     * @param array $meta_by_key  Order meta indexed by key.
     * @param array $user_meta_by_key User meta indexed by key.
     * @return string|null Resolved value, or null if not applicable.
     */
    private static function resolve_field_from_bulk( $field_def, $bulk_data, $meta_by_key, $user_meta_by_key ) {
        if ( isset( $field_def['value'] ) && is_callable( $field_def['value'] ) ) {
            return call_user_func( $field_def['value'], $bulk_data );
        }

        $source = isset( $field_def['source'] ) ? $field_def['source'] : null;
        $path   = isset( $field_def['path'] ) ? $field_def['path'] : null;

        if ( ! $source || ! $path ) {
            return null;
        }

        if ( 'base' === $source ) {
            $parts = explode( '.', $path );
            $data  = $bulk_data['base'];
            foreach ( $parts as $part ) {
                if ( ! isset( $data[ $part ] ) ) {
                    return '';
                }
                $data = $data[ $part ];
            }
            return (string) $data;
        }

        if ( 'meta' === $source ) {
            $value = $meta_by_key[ $path ] ?? '';
            return is_array( $value ) || is_object( $value ) ? json_encode( $value ) : (string) $value;
        }

        if ( 'user_meta' === $source ) {
            $value = $user_meta_by_key[ $path ] ?? '';
            return is_array( $value ) || is_object( $value ) ? json_encode( $value ) : (string) $value;
        }

        return null;
    }

    /**
     * Compare discovered meta keys against stored known keys.
     * If new keys found, update the persistent option and schedule an async schema push.
     *
     * @param string $type     Post type key (e.g. 'shop_order') or '_user' for user meta.
     * @param array  $live_keys Keys found in the current object's meta.
     */
    private static function maybe_update_known_meta_keys( $type, $live_keys ) {
        if ( '_user' === $type ) {
            $known = get_option( 'notifier_user_meta_keys', array() );
            $new_keys = array_diff( $live_keys, $known );
            if ( ! empty( $new_keys ) ) {
                update_option( 'notifier_user_meta_keys', array_unique( array_merge( $known, $new_keys ) ), false );
                as_enqueue_async_action( 'notifier_push_updated_schema', array( 'slug' => 'woocommerce' ), 'notifier' );
            }
            return;
        }

        $all_known = get_option( 'notifier_custom_meta_keys', array() );
        $known     = isset( $all_known[ $type ] ) ? $all_known[ $type ] : array();
        $new_keys  = array_diff( $live_keys, $known );
        if ( ! empty( $new_keys ) ) {
            $all_known[ $type ] = array_unique( array_merge( $known, $new_keys ) );
            update_option( 'notifier_custom_meta_keys', $all_known, false );
            as_enqueue_async_action( 'notifier_push_updated_schema', array( 'slug' => 'woocommerce' ), 'notifier' );
        }
    }

    /**
     * Action Scheduler handler: push updated trigger schema to SaaS after new meta keys discovered.
     *
     * @param string $slug Integration slug.
     */
    public static function handle_push_updated_schema( $slug ) {
        if ( ! Notifier_Connection::is_connected( $slug ) ) {
            return;
        }
        $trigger_schema = Notifier_Connection::build_trigger_schema( $slug );
        Notifier_Connection::send_saas_request( $slug, 'sync-triggers', array( 'triggers' => $trigger_schema ) );
    }

    // ========================================
    // v3 CONTACT SYNC
    // ========================================

    /**
     * Sync a WP user as a contact to SaaS when they register.
     *
     * @param int $user_id
     */
    public static function on_user_register( $user_id ) {
        self::sync_customer_contact( $user_id, 'woo_customer_created' );
    }

    /**
     * Sync a WP user as a contact to SaaS when their profile is updated.
     *
     * @param int     $user_id
     * @param WP_User $old_user_data
     */
    public static function on_user_update( $user_id, $old_user_data ) {
        self::sync_customer_contact( $user_id, 'woo_customer_updated' );
    }

    /**
     * Sync contact from checkout billing data (covers guest checkouts).
     *
     * Only runs when continuous sync is enabled in the connection config.
     *
     * @param int $order_id
     */
    public static function on_checkout_contact_sync( $order_id ) {
        $conn = Notifier_Connection::get_connection( 'woocommerce' );
        if ( empty( $conn ) || empty( $conn['continuous_sync'] ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $phone = $order->get_billing_phone();
        if ( empty( $phone ) ) {
            return;
        }

        $contact = array(
            'billing' => array(
                'phone'      => $phone,
                'email'      => $order->get_billing_email(),
                'first_name' => $order->get_billing_first_name(),
                'last_name'  => $order->get_billing_last_name(),
                'company'    => $order->get_billing_company(),
                'address_1'  => $order->get_billing_address_1(),
                'address_2'  => $order->get_billing_address_2(),
                'city'       => $order->get_billing_city(),
                'state'      => $order->get_billing_state(),
                'postcode'   => $order->get_billing_postcode(),
                'country'    => $order->get_billing_country(),
            ),
        );

        self::push_contacts_to_saas( array( $contact ), array(), 'woo_customer_created' );
    }

    /**
     * Sync a WordPress/WooCommerce user as a contact to the SaaS.
     *
     * Only runs when continuous sync is enabled in the connection config.
     *
     * @param int    $user_id
     * @param string $event_key Trigger slug: 'woo_customer_created' or 'woo_customer_updated'.
     */
    private static function sync_customer_contact( $user_id, $event_key ) {
        $conn = Notifier_Connection::get_connection( 'woocommerce' );
        if ( empty( $conn ) ) {
            return;
        }

        // Respect the continuous sync toggle.
        if ( empty( $conn['continuous_sync'] ) ) {
            return;
        }

        $phone = get_user_meta( $user_id, 'billing_phone', true );
        if ( empty( $phone ) ) {
            return; // Can't sync without a phone number.
        }

        $contact = self::build_customer_contact_data( $user_id );
        self::push_contacts_to_saas( array( $contact ), array(), $event_key );
    }

    /**
     * Push contacts to the SaaS via the unified /{webhook_key}/event endpoint.
     *
     * @param array  $contacts   Array of contact data arrays in WC customer format.
     * @param array  $meta       Optional metadata (total_contacts, remaining_contacts).
     * @param string $event_key  Trigger slug identifying the event: 'woo_customer_created',
     *                           'woo_customer_updated', or 'woo_customer_batch_sync'.
     */
    private static function push_contacts_to_saas( $contacts, $meta = array(), $event_key = 'woo_customer_created' ) {
        $conn = Notifier_Connection::get_connection( 'woocommerce' );
        if ( empty( $conn ) || empty( $conn['webhook_key'] ) || empty( $conn['saas_url'] ) ) {
            return;
        }

        $url  = trailingslashit( $conn['saas_url'] ) . NOTIFIER_APP_API_PATH . '/integrations/woocommerce/' . $conn['webhook_key'] . '/event';
        $body = array_merge( array(
            'event_key' => $event_key,
            'contacts'  => $contacts,
        ), $meta );


        $response = wp_remote_post( $url, array(
            'headers'   => array(
                'Content-Type' => 'application/json',
                'X-Auth-Key'   => $conn['auth_key'] ?? '',
            ),
            'body'      => wp_json_encode( $body ),
            'timeout'   => 30,
            'sslverify' => true,
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'WANotifier: push_contacts_to_saas failed: ' . $response->get_error_message() );
        }
    }

    /**
     * REST handler: SaaS pushes config updates (e.g. continuous_sync toggle).
     *
     * Stores the config values in the plugin's WooCommerce connection option so
     * the real-time sync hooks can read them without calling the SaaS.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle_rest_update_config( $request ) {
        $data = $request->get_json_params();

        $conn = Notifier_Connection::get_connection( 'woocommerce' );
        if ( empty( $conn ) ) {
            return new WP_REST_Response( array( 'error' => true, 'message' => 'Not connected.' ), 400 );
        }

        if ( array_key_exists( 'continuous_sync', $data ) ) {
            $conn['continuous_sync'] = ! empty( $data['continuous_sync'] );
            Notifier_Connection::save_connection( 'woocommerce', $conn );
        }

        return new WP_REST_Response( array( 'error' => false, 'message' => 'Config updated.' ), 200 );
    }

    /**
     * REST handler: SaaS requests the plugin to stop a running batch customer sync.
     *
     * Cancels all pending Action Scheduler jobs for the batch sync and sets a
     * transient flag so any currently-running page handler exits early.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle_rest_batch_sync_stop( $request ) {
        // Cancel all queued (not yet started) batch sync pages.
        as_unschedule_all_actions( 'notifier_wc_batch_contact_sync', array(), 'notifier' );

        // Set a short-lived flag so any in-progress page handler exits before pushing to SaaS.
        set_transient( 'notifier_wc_batch_sync_stopped', 1, 5 * MINUTE_IN_SECONDS );

        return new WP_REST_Response( array(
            'error'   => false,
            'message' => 'Batch sync stopped.',
        ), 200 );
    }

    /**
     * REST handler: SaaS requests the plugin to start a batch customer sync.
     *
     * Schedules an Action Scheduler job on the plugin side that pages through
     * all WooCommerce customers and pushes them to the SaaS /{webhook_key}/event endpoint.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle_rest_batch_sync_start( $request ) {

        // Cancel any existing pending batch sync jobs to avoid duplicates.
        as_unschedule_all_actions( 'notifier_wc_batch_contact_sync', array(), 'notifier' );

        // Clear any lingering stop flag from a previous stop request.
        delete_transient( 'notifier_wc_batch_sync_stopped' );

        // Count total customers with billing phone.
        $total_query = new WP_User_Query( array(
            'role__in'     => array( 'customer', 'subscriber' ),
            'meta_key'     => 'billing_phone',
            'meta_compare' => '!=',
            'meta_value'   => '',
            'number'       => -1,
            'fields'       => 'ID',
        ) );
        $total = count( $total_query->get_results() );


        // Schedule first page.
        as_enqueue_async_action(
            'notifier_wc_batch_contact_sync',
            array( 'page' => 1, 'per_page' => 50, 'total' => $total ),
            'notifier'
        );


        return new WP_REST_Response( array(
            'error'   => false,
            'message' => 'Batch sync started.',
            'total'   => $total,
        ), 200 );
    }

    /**
     * Action Scheduler handler: process one page of the batch customer sync.
     *
     * Runs in the plugin. Fetches a page of WC customers, builds contact data,
     * and pushes the batch to the SaaS /{webhook_key}/event endpoint. Schedules the next
     * page if more customers remain.
     *
     * @param int $page     Current page number.
     * @param int $per_page Number of customers per page.
     * @param int $total    Total customers to sync.
     */
    public static function process_batch_contact_sync_page( $page, $per_page, $total ) {
        // Abort immediately if a stop was requested while this job was queued.
        if ( get_transient( 'notifier_wc_batch_sync_stopped' ) ) {
            return;
        }

        $page     = (int) $page     ?: 1;
        $per_page = (int) $per_page ?: 50;
        $total    = (int) $total    ?: 0;


        $user_query = new WP_User_Query( array(
            'role__in'     => array( 'customer', 'subscriber' ),
            'meta_key'     => 'billing_phone',
            'meta_compare' => '!=',
            'meta_value'   => '',
            'number'       => $per_page,
            'paged'        => $page,
            'orderby'      => 'ID',
            'order'        => 'ASC',
        ) );

        $users = $user_query->get_results();

        if ( empty( $users ) ) {
            return;
        }


        $contacts = array();
        foreach ( $users as $user ) {
            $contacts[] = self::build_customer_contact_data( $user->ID );
        }

        $processed_so_far = $page * $per_page;
        $remaining        = max( 0, $total - $processed_so_far );


        self::push_contacts_to_saas( $contacts, array(
            'total_contacts'     => $total,
            'remaining_contacts' => $remaining,
        ), 'woo_customer_batch_sync' );

        if ( $remaining > 0 ) {
            as_enqueue_async_action(
                'notifier_wc_batch_contact_sync',
                array( 'page' => $page + 1, 'per_page' => $per_page, 'total' => $total ),
                'notifier'
            );
        }
    }

    /**
     * Build contact data array for a WP user in WC customer format.
     *
     * @param int $user_id
     * @return array
     */
    private static function build_customer_contact_data( $user_id ) {
        $user            = get_userdata( $user_id );
        $billing_phone   = get_user_meta( $user_id, 'billing_phone', true );
        $billing_country = get_user_meta( $user_id, 'billing_country', true );

        return array(
            'id'         => $user_id,
            'email'      => $user ? $user->user_email : '',
            'first_name' => get_user_meta( $user_id, 'first_name', true ) ?: get_user_meta( $user_id, 'billing_first_name', true ),
            'last_name'  => get_user_meta( $user_id, 'last_name', true ) ?: get_user_meta( $user_id, 'billing_last_name', true ),
            'username'   => $user ? $user->user_login : '',
            'billing'    => array(
                'phone'      => self::get_formatted_phone_number( $billing_phone, $billing_country ),
                'email'      => get_user_meta( $user_id, 'billing_email', true ),
                'first_name' => get_user_meta( $user_id, 'billing_first_name', true ),
                'last_name'  => get_user_meta( $user_id, 'billing_last_name', true ),
                'company'    => get_user_meta( $user_id, 'billing_company', true ),
                'address_1'  => get_user_meta( $user_id, 'billing_address_1', true ),
                'address_2'  => get_user_meta( $user_id, 'billing_address_2', true ),
                'city'       => get_user_meta( $user_id, 'billing_city', true ),
                'state'      => get_user_meta( $user_id, 'billing_state', true ),
                'postcode'   => get_user_meta( $user_id, 'billing_postcode', true ),
                'country'    => $billing_country,
            ),
        );
    }

    /**
     * store session id during order creation
     */
	public static function notifier_save_session_on_new_order($order_id) {
		if ('yes' !== get_option('notifier_enable_abandonment_cart_tracking', 'no')) {
			return;
		}

		if (!WC()->session || !WC()->session->has_session()) {
			return;
		}

		// Fetch specific session key
		$session_key = WC()->session->get('notifier_session_key');

		if (!empty($session_key)) {
			global $wpdb;
			$table_name = $wpdb->prefix . NOTIFIER_CART_ABANDONMENT_TABLE;
			$wpdb->update(
				$table_name,
				array('order_id' => $order_id), // Update the order_id
				array('session_key' => sanitize_key($session_key)), // Where condition
				array('%d'), // Data type for order_id
				array('%s')  // Data type for session_key
			);
			as_unschedule_all_actions('notifier_check_cart_abandonment', array(sanitize_key($session_key)));
		}
	}
}
