<?php
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
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_order_meta_box' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'edit_admin_order_meta' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'save_admin_order_meta' ), 10, 2 );
		
		add_action( 'wp_ajax_notifier_save_cart_abandonment_data', array(__CLASS__, 'notifier_save_cart_abandonment_data'));
		add_action( 'wp_ajax_nopriv_notifier_save_cart_abandonment_data', array(__CLASS__, 'notifier_save_cart_abandonment_data'));
		add_action( 'woocommerce_cart_updated', array( __CLASS__, 'notifier_update_cart_data_on_update'), 999);
		add_action( 'notifier_check_cart_abandonment', array( __CLASS__ , 'notifier_process_cart_abandonment' ), 10, 1);

		add_action( 'woocommerce_new_order', array( __CLASS__ , 'notifier_save_session_on_new_order' ), 10, 1);

		add_action( 'wp', array(__CLASS__, 'retrive_cart_data_from_url'));
	}

	/**
	 * Add Woocommerce notification triggers
	 */
	public static function get_woo_notification_triggers() {
		$merge_tag_types = array('WooCommerce', 'WooCommerce Order', 'WooCommerce Customer', 'WooCommerce Order Custom Meta', 'WooCommerce Customer User Meta', 'WooCommerce Cart Abandonment');
		$recipient_types = array('WooCommerce', 'WooCommerce Order Custom Meta', 'WooCommerce Customer User Meta');
		$triggers = array (
			array(
				'id'			=> 'woo_order_new',
				'label' 		=> 'New order is placed',
				'description'	=> 'Trigger notification when a new order is placed.',
				'merge_tags' 	=> Notifier_Notification_Merge_Tags::get_merge_tags($merge_tag_types),
				'recipient_fields'	=> Notifier_Notification_Merge_Tags::get_recipient_fields($recipient_types),
				'action'		=> array(
					'hook'		=> 'woocommerce_checkout_order_processed',
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
					'hook'		=> 'woocommerce_checkout_order_processed',
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
				'label'         => 'Cart is abandoned (New)',
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
				'value'			=> function ($args) {
					return (string) get_permalink( wc_get_page_id( 'shop' ) );
				}
			),
			array(
				'id' 			=> 'woo_my_account_url',
				'label' 		=> 'My Account page url',
				'preview_value' => 'https://example.com/my-account/',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					return get_permalink( wc_get_page_id( 'myaccount' ) );
				}
			),
		);

		$woo_fields = array ();
		$woo_fields['WooCommerce Order'] = array ( // Woocommerce order fields
			'id'					=> array(
				'preview'	=> '123',
				'label' 	=> 'Order ID'
			),
			'subtotal_to_display'	=> array(
				'preview'	=> '$50.00',
				'label' 	=> 'Order subtotal'
			),
			'coupon_codes'			=> array(
				'preview'	=> 'OFF50',
				'label' 	=> 'Order coupon codes',
				'value'		=> function ($order, $field_function) {
					return implode(', ', $order->$field_function());
				}
			),
			'discount_to_display'	=> array(
				'preview'	=> '$5.00',
				'label' 	=> 'Order discount amount'
			),
			'shipping_method'		=> array(
				'preview'	=> 'Flat rate',
				'label' 	=> 'Order shipping method'
			),
			'shipping_total'		=> array(
				'preview'	=> '$2.50',
				'label' 	=> 'Order shipping amount',
				'value'		=> function ($order, $field_function) {
					return wc_price( $order->$field_function(), array( 'currency' => $order->get_currency() ) );
				}
			),
			'total_tax'		=> array(
				'preview'	=> '$5.00',
				'label' 	=> 'Order tax amount',
				'value'		=> function ($order, $field_function) {
					return wc_price( $order->$field_function(), array( 'currency' => $order->get_currency() ) );
				}
			),
			'formatted_order_total' => array(
				'preview'	=> '$50.00',
				'label' 	=> 'Order total amount'
			),
			'payment_method_title'	=> array(
				'preview'	=> 'Cash on delivery',
				'label' 	=> 'Order payment method'
			),
			'checkout_payment_url'	=> array(
				'preview'	=> 'https://example.com/checkout/order-received/123/?key=wc_order_abcdef',
				'label' 	=> 'Order checkout payment URL'
			),
			'checkout_order_received_url'	=> array(
				'preview'	=> 'https://example.com/checkout/order-received/123/?key=wc_order_abcdef',
				'label' 	=> 'Order thank you page URL'
			),
			'view_order_url'				=> array(
				'preview'	=> 'https://example.com/my-account/view-order/123/',
				'label' 	=> 'Order view URL'
			),
			'status'			=>	array(
				'preview'	=> 'Processing',
				'label' 	=> 'Order status'
			),
			'first_product_image' => array(
				'preview'	=> '',
				'label' 	=> 'Order first item featured image',
				'return_type'	=> 'image',
				'value'		=> function ($order, $field_function) {
					$image_id = false;
					$image_url = '';
					foreach($order->get_items() as $item){
						$first_product_id = $item->get_product_id();
						$product = wc_get_product( $first_product_id );
						$image_id = $product->get_image_id();
						if($image_id){
							break;
						}
					}

					if($image_id){
						$image_url = wp_get_attachment_url( $product->get_image_id() );
					}
					else{
						$image_url = wc_placeholder_img_src();
					}
	                return $image_url;
				}
			),
			'products_list' => array(
				'preview'	=> '',
				'label' 	=> 'Order products list',
				'return_type'	=> 'text',
				'value'		=> function ($order, $field_function) {
					$order_item_data = array();
					foreach ( $order->get_items() as $item ) {
						$order_item = $item->get_name().' x '.$item->get_quantity().' ('.wc_price( $item->get_total() ).')';
						$order_item_data[] = sanitize_textarea_field($order_item);
					}

					return implode(', ',$order_item_data);
				}
			),
			'order_first_item_name' => array(
				'preview'      => '',
				'label'        => 'Order first item name',
				'return_type'  => 'text',
				'value'        => function ($order, $field_function) {
					foreach ($order->get_items() as $item) {
						return $item->get_name();
					}
					return '';
				}
			),
			'order_first_item_price' => array(
				'preview'      => '',
				'label'        => 'Order first item price',
				'return_type'  => 'text',
				'value'        => function ($order, $field_function) {
					foreach ($order->get_items() as $item) {
						$product = $item->get_product();
						if ( $product ) {
							return wc_price( $product->get_price(), array( 'currency' => $order->get_currency() ) );
						}
						break;
					}
					return '';
				}
			),
			'order_first_item_total' => array(
				'preview'      => '',
				'label'        => 'Order first item total',
				'return_type'  => 'text',
				'value'        => function ($order, $field_function) {
					foreach ($order->get_items() as $item) {
						return wc_price( $item->get_total(), array( 'currency' => $order->get_currency() ));
					}
					return '';
				}
			),
		);

		$woo_fields['WooCommerce Customer'] = array (
			'billing_first_name'	=> array('preview'	=> 'John'),
			'billing_last_name'		=> array('preview'	=> 'Doe'),
			'billing_company'		=> array('preview'	=> 'ABC Inc'),
			'billing_address_1'		=> array('preview'	=> '123, XYZ Street'),
			'billing_address_2'		=> array('preview'	=> 'XYZ Avenue'),
			'billing_city'			=> array('preview'	=> 'New York'),
			'billing_postcode'		=> array('preview'	=> '12345'),
			'billing_state'			=> array(
				'preview'	=> 'NY',
				'value'		=> function ($order, $field_function) {
					$country_function = str_replace('state', 'country', $field_function);
					$state_code = $order->$field_function();
					$country_code = $order->$country_function();
					return WC()->countries->states[$country_code][$state_code];
				}
			),
			'billing_country'		=> array(
				'preview'	=> 'US',
				'value'		=> function ($order, $field_function) {
					return WC()->countries->countries[$order->$field_function()];
				}
			),
			'billing_email'			=> array('preview'	=> 'john@example.com'),
			'billing_phone'			=> array('preview'	=> '987 654 321'),
			'formatted_billing_address' 	=> array(
				'preview'	=> '',
				'label'		=> 'Complete billing address',
				'value'		=> function ($order, $field_function) {
					return str_replace('<br/>', ', ', $order->$field_function());
				}
			),
			'shipping_first_name'	=> array('preview'	=> 'John'),
			'shipping_last_name'	=> array('preview'	=> 'Doe'),
			'shipping_company'		=> array('preview'	=> 'ABC Inc'),
			'shipping_address_1'	=> array('preview'	=> '123, XYZ Street'),
			'shipping_address_2'	=> array('preview'	=> 'XYZ Avenue'),
			'shipping_city'			=> array('preview'	=> 'New York'),
			'shipping_postcode'		=> array('preview'	=> '12345'),
			'shipping_state'		=> array(
				'preview'	=> 'NY',
				'value'		=> function ($order, $field_function) {
					$country_function = str_replace('state', 'country', $field_function);
					$state_code = $order->$field_function();
					$country_code = $order->$country_function();
					return WC()->countries->states[$country_code][$state_code];
				}
			),
			'shipping_country'		=> array(
				'preview'	=> 'US',
				'value'		=> '',
				'value'		=> function ($order, $field_function) {
					return WC()->countries->countries[$order->$field_function()];
				}
			),
			'shipping_phone'				=> array('preview'	=> '987 654 321'),
			'formatted_shipping_address'	=> array(
				'preview'	=> '',
				'label'		=> 'Complete shipping address',
				'value'		=> function ($order, $field_function) {
					return str_replace('<br/>', ', ', $order->$field_function());
				}
			)
		);

		foreach ($woo_fields as $woo_field_key => $woo_field) {
			foreach ($woo_field as $field => $field_data) {
				if (isset($field_data['label'])) {
					$label = $field_data['label'];
				} else {
					$label = ucfirst( str_replace('_', ' ', $field) );
				}

				$merge_tags[$woo_field_key][] = array(
					'id' 			=> 'woo_order_' . $field,
					'label' 		=> $label,
					'preview_value' => isset($field_data['preview']) ? $field_data['preview'] : '',
					'return_type'	=> isset($field_data['return_type']) ? $field_data['return_type'] : 'text',
					'value'			=> function ($args) use ($field, $field_data) {
						$order = wc_get_order( $args['object_id'] );
						$field_function = 'get_' . $field;
						if (isset($field_data['value'])) {
							$value = $field_data['value']($order, $field_function);
						} else {
							$value = $order->$field_function();
						}
						return html_entity_decode(sanitize_text_field($value));
					}
				);
			}
		}

		// Order meta keys
		$order_meta_keys = Notifier_Notification_Merge_Tags::get_custom_meta_keys('shop_order');
		if(!empty($order_meta_keys)){
			foreach($order_meta_keys as $order_meta_key){
				$merge_tags['WooCommerce Order Custom Meta'][] = array(
					'id' 			=> 'woo_order_meta_' . $order_meta_key,
					'label' 		=> $order_meta_key,
					'preview_value' => '123',
					'return_type'	=> 'all',
					'value'			=> function ($args) use ($order_meta_key) {
						$order = wc_get_order( $args['object_id'] );
						$value = $order->get_meta( $order_meta_key );
						if(is_array($value) || is_object($value)){
							$value = json_encode($value);
						}
						return (string) $value;
					}
				);
			}
		}

		// WooCommerce customer user meta
		$user_meta_keys = Notifier_Notification_Merge_Tags::get_user_meta_keys();
		if(!empty($user_meta_keys)){
			foreach($user_meta_keys as $user_meta_key){
				$merge_tags['WooCommerce Customer User Meta'][] = array(
					'id' 			=> 'woo_customer_meta_' . $user_meta_key,
					'label' 		=> $user_meta_key,
					'preview_value' => '123',
					'return_type'	=> 'all',
					'value'			=> function ($args) use ($user_meta_key) {
						$order = wc_get_order( $args['object_id'] );
						$user_id = $order->get_user_id();
						if(0 == $user_id){
							return '';
						}

						$value = get_user_meta($user_id, $user_meta_key, true);
						if(is_array($value) || is_object($value)){
							$value = json_encode($value);
						}
						return (string) $value;
					}
				);
			}
		}

		$merge_tags['WooCommerce Cart Abandonment'] = array(
			array(
				'id'            => 'wca_cart_products_list',
				'label'         => 'Adandoned cart products list',
				'preview_value' => '',
				'return_type'   => 'text',
				'value'         => function ($cart_details) {
                    return $cart_details ? self::get_comma_separated_products(maybe_unserialize($cart_details['cart_data'])) : '';
				}
			),
			array(
				'id'            => 'wca_cart_total',
				'label'         => 'Adandoned cart total',
				'preview_value' => '',
				'return_type'   => 'text',
				'value'         => function ($cart_details) {
					return $cart_entry['cart_total'] ?? '';
				}
			),
			array(
				'id'            => 'wca_cart_datetime',
				'label'         => 'Adandoned cart datetime',
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
					$coupon_data = $data ? maybe_unserialize($cart_details['coupon_content']) : [];
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
	public static function woocommerce_recipient_fields($recipient_fields){
		$recipient_fields['WooCommerce'] = array(
			array(
				'id'			=> 'woo_order_billing_phone',
				'label'			=> 'Billing phone number',
				'value'			=> function ($args) {
					$order = wc_get_order( $args['object_id'] );
					$phone_number = $order->get_billing_phone();
					$country_code = $order->get_billing_country();
					$phone_number = self::get_formatted_phone_number($phone_number, $country_code);
					return html_entity_decode(sanitize_text_field($phone_number));
				}
			),
			array(
				'id'			=> 'woo_order_shipping_phone',
				'label'			=> 'Shipping phone number',
				'value'			=> function ($args) {
					$order = wc_get_order( $args['object_id'] );
					$phone_number = $order->get_shipping_phone();
					$country_code = $order->get_shipping_country();
					$phone_number = self::get_formatted_phone_number($phone_number, $country_code);
					return html_entity_decode(sanitize_text_field($phone_number));
				}
			)
		);

		// Order meta keys
		$order_meta_keys = Notifier_Notification_Merge_Tags::get_custom_meta_keys('shop_order');
		if(!empty($order_meta_keys)){
			foreach($order_meta_keys as $order_meta_key){
				$recipient_fields['WooCommerce Order Custom Meta'][] = array(
					'id' 			=> 'woo_order_meta_' . $order_meta_key,
					'label' 		=> $order_meta_key,
					'preview_value' => '123',
					'return_type'	=> 'text',
					'value'			=> function ($args) use ($order_meta_key) {
						$order = wc_get_order( $args['object_id'] );
						$value = $order->get_meta( $order_meta_key );
						if(is_array($value) || is_object($value)){
							$value = json_encode($value);
						}
						return (string) $value;
					}
				);
			}
		}

		// WooCommerce customer user meta
		$user_meta_keys = Notifier_Notification_Merge_Tags::get_user_meta_keys();
		if(!empty($user_meta_keys)){
			foreach($user_meta_keys as $user_meta_key){
				$recipient_fields['WooCommerce Customer User Meta'][] = array(
					'id' 			=> 'woo_customer_meta_' . $user_meta_key,
					'label' 		=> $user_meta_key,
					'preview_value' => '123',
					'return_type'	=> 'text',
					'value'			=> function ($args) use ($user_meta_key) {
						$order = wc_get_order( $args['object_id'] );
						$user_id = $order->get_user_id();
						if(0 == $user_id){
							return '';
						}

						$value = get_user_meta($user_id, $user_meta_key, true);
						if(is_array($value) || is_object($value)){
							$value = json_encode($value);
						}
						return (string) $value;
					}
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
		$opt_in = sanitize_text_field($_POST[ NOTIFIER_PREFIX . 'whatsapp_opt_in' ]);
		if ( !empty($opt_in) ) {
			$order = wc_get_order( $order_id );
			$order->update_meta_data( NOTIFIER_PREFIX . 'whatsapp_opt_in', $opt_in );
    		$order->save();
		}
	}

	/**
	 * Register order meta box
	 */	
	public static function register_order_meta_box() {
		add_meta_box(
			'notifier_woocommerce_order_meta',
			__( 'WANotifier', 'your-textdomain' ),
			array( __CLASS__, 'edit_admin_order_meta' ),
			'shop_order',
			'side',
			'default'
		);
	}

	/**
	 * Display checkbox field on order page
	 */	
	public static function edit_admin_order_meta( $post ){
		if ( 'yes' !== get_option( NOTIFIER_PREFIX . 'enable_opt_in_checkbox_checkout' ) ) {
			return;
		}

		$order = wc_get_order( $post->ID ); // Get the order from post ID
		if ( ! $order ) {
			return;
		}

		$checkbox_text = get_option( NOTIFIER_PREFIX . 'checkout_opt_in_checkbox_text' );
		if ( empty( $checkbox_text ) ) {
			$checkbox_text = 'Receive updates on WhatsApp';
		}

		$opt_in  = $order->get_meta( NOTIFIER_PREFIX . 'whatsapp_opt_in' );
		$checked = checked( $opt_in, '1', false );

		echo '<p>';
		echo '<label for="' . esc_attr( NOTIFIER_PREFIX . 'whatsapp_opt_in' ) . '">';
		echo '<input type="checkbox" name="' . esc_attr( NOTIFIER_PREFIX . 'whatsapp_opt_in' ) . '" id="' . esc_attr( NOTIFIER_PREFIX . 'whatsapp_opt_in' ) . '" value="1" ' . $checked . ' />';
		echo '&nbsp;' . esc_html( $checkbox_text );
		echo '</label>';
		echo '</p>';
	}

	/**
	 * Hook to save the custom field data on order page
	 */
	public static function save_admin_order_meta( $post_id, $post ) {
		$order = wc_get_order( $post_id );

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

		// Determine created_time
		$created_time = $existing_entry ? $existing_entry->created_time : current_time('mysql');

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
