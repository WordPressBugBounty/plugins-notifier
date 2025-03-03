<?php
/**
 * WooCommerce Cart Abandonment Recovery
 *
 * @package    Wa_Notifier
 */
class Notifier_WCAR {

	/**
	 * Init.
	 */
	public static function init() {
		add_filter( 'notifier_notification_triggers', array( __CLASS__, 'add_notification_triggers'), 10 );
		add_filter( 'notifier_notification_merge_tags', array( __CLASS__, 'wcar_merge_tags') );
		add_filter( 'notifier_notification_recipient_fields', array( __CLASS__, 'wcar_recipient_fields') );
	}

	/**
	 * Add notification trigger
	 */
	public static function add_notification_triggers ($triggers) {
		$merge_tag_types = array('WooCommerce', 'WooCommerce Cart Abandonment Recovery');
		$recipient_types = array('WooCommerce Cart Abandonment Recovery');
		$triggers['WooCommerce Abondoned Cart Recovery'][] = array(
			'id'			=> 'woocommerce_cart_abandonment_recovery',
			'label' 		=> 'Cart is abandoned',
			'description'	=> 'Trigger notification when cart is abandoned as per your settings in the plugin.',
			'merge_tags' 	=> Notifier_Notification_Merge_Tags::get_merge_tags($merge_tag_types),
			'recipient_fields'	=> Notifier_Notification_Merge_Tags::get_recipient_fields($recipient_types),
			'action'		=> array(
				'hook'		=> 'wcf_ca_process_abandoned_order',
				'callback' 	=> function ( $checkout_details ) {
					$checkout_details = (array) $checkout_details;
					Notifier_Notification_Triggers::send_trigger_request('woocommerce_cart_abandonment_recovery', $checkout_details);
				}
			)
		);
		return $triggers;
	}

	/**
	 * Add merge tags
	 */
	public static function wcar_merge_tags($merge_tags) {
		$merge_tags['WooCommerce Cart Abandonment Recovery'] = array(
			array(
				'id' 			=> 'wcar_cart_products_list',
				'label' 		=> 'Adandoned cart products list',
				'preview_value' => '',
				'return_type'	=> 'text',
				'value'			=> function ($checkout_details) {
					$value = Cartflows_Ca_Helper::get_instance()->get_comma_separated_products( $checkout_details['cart_contents'] );
					return (string) $value;
				}
			),
			array(
				'id' 			=> 'wcar_cart_total',
				'label' 		=> 'Adandoned cart total',
				'preview_value' => '',
				'return_type'	=> 'text',
				'value'			=> function ($checkout_details) {
					$value = isset($checkout_details['cart_total']) ? $checkout_details['cart_total'] : '';
					return (string) $value;
				}
			),
			array(
				'id' 			=> 'wcar_cart_datetime',
				'label' 		=> 'Adandoned cart datetime',
				'preview_value' => '',
				'return_type'	=> 'text',
				'value'			=> function ($checkout_details) {
					$value = isset($checkout_details['time']) ? $checkout_details['time'] : '';
					return (string) $value;
				}
			),
			array(
				'id' 			=> 'wcar_cart_checkout_url',
				'label' 		=> 'Abandoned cart checkout URL',
				'preview_value' => '',
				'return_type'	=> 'text',
				'value'			=> function ($checkout_details) {
					$token_data = array(
						'wcf_session_id'    => $checkout_details['session_id'],
						'wcf_preview_email' => false,
					);
					$checkout_url = Cartflows_Ca_Helper::get_instance()->get_checkout_url( $checkout_details['checkout_id'], $token_data );
					return (string) $checkout_url;
				}
			),
			array(
				'id' 			=> 'wcar_cart_customer_email',
				'label' 		=> 'Billing email',
				'preview_value' => '',
				'return_type'	=> 'text',
				'value'			=> function ($checkout_details) {
					$value = isset($checkout_details['email']) ? $checkout_details['email'] : '';
					return (string) $value;
				}
			),
		);

		$wcar_other_fields = array (
			'first_name'		=> array(
				'preview'		=> 'John',
				'label'			=> 'Billing first name'
			),
			'last_name'			=> array(
				'preview'		=> 'Doe',
				'label'			=> 'Billing last name'
			),
			'billing_company'		=> array('preview'	=> 'ABC Inc'),
			'billing_address_1'		=> array('preview'	=> '123, XYZ Street'),
			'billing_address_2'		=> array('preview'	=> 'XYZ Avenue'),
			'billing_city'			=> array(
				'preview'	=> 'New York',
				'value'		=> function ($other_data) {
					$location = isset($other_data['wcf_location']) ? explode(', ', $other_data['wcf_location']) : '';
					if(!isset($location[1])){
						return '';
					}
					return (string) $location[1];
				}
			),
			'billing_postcode'		=> array('preview'	=> '12345'),
			'billing_state'			=> array(
				'preview'	=> 'NY',
				'value'		=> function ($other_data) {
					$state_code = isset($other_data['wcf_billing_state']) ? $other_data['wcf_billing_state'] : '';
					$location = isset($other_data['wcf_location']) ? explode(', ', $other_data['wcf_location']) : '';
					$country_code = isset($location[0]) ? $location[0] : '';
					if('' == $state_code || '' == $country_code){
						return '';
					}
					return WC()->countries->states[$country_code][$state_code];
				}
			),
			'billing_country'		=> array(
				'preview'	=> 'US',
				'value'		=> function ($other_data) {
					$location = isset($other_data['wcf_location']) ? explode(', ', $other_data['wcf_location']) : '';
					$country_code = isset($location[0]) ? $location[0] : '';
					if('' == $country_code){
						return '';
					}
					return WC()->countries->countries[$country_code];
				}
			),
			'phone_number'			=> array(
				'preview'			=> '987 654 321',
				'label'				=> 'Billing phone number'
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
				'value'		=> function ($other_data) {
					$state_code = isset($other_data['wcf_shipping_state']) ? $other_data['wcf_shipping_state'] : '';
					$country_code = isset($other_data['wcf_shipping_country']) ? $other_data['wcf_shipping_country'] : '';
					if('' == $state_code || '' == $country_code){
						return '';
					}
					return WC()->countries->states[$country_code][$state_code];
				}
			),
			'shipping_country'		=> array(
				'preview'	=> 'US',
				'value'		=> function ($other_data) {
					$country_code = isset($other_data['wcf_shipping_country']) ? $other_data['wcf_shipping_country'] : '';
					if('' == $country_code){
						return '';
					}
					return WC()->countries->countries[$country_code];
				}
			)

		);

		foreach ($wcar_other_fields as $field => $field_data) {
			if (isset($field_data['label'])) {
				$label = $field_data['label'];
			} else {
				$label = ucfirst( str_replace('_', ' ', $field) );
			}

			$merge_tags['WooCommerce Cart Abandonment Recovery'][] = array(
				'id' 			=> 'wcar_cart_customer_' . $field,
				'label' 		=> $label,
				'preview_value' => isset($field_data['preview']) ? $field_data['preview'] : '',
				'return_type'	=> isset($field_data['return_type']) ? $field_data['return_type'] : 'text',
				'value'			=> function ($checkout_details) use ($field, $field_data) {
					$other_fields = isset($checkout_details['other_fields']) ? $checkout_details['other_fields'] : '';
					$other_fields = maybe_unserialize($other_fields);
					if(empty($other_fields)){
						return '';
					}

					if (isset($field_data['value'])) {
						$value = $field_data['value']($other_fields);
					} else {
						$value = $other_fields['wcf_' . $field];
					}
					return html_entity_decode(sanitize_text_field($value));
				}
			);
		}

		return $merge_tags;
	}

	/**
	 * Add recipient fields
	 */
	public static function wcar_recipient_fields($recipient_fields){
		$recipient_fields['WooCommerce Cart Abandonment Recovery'] = array(
			array(
				'id'			=> 'wcar_cart_customer_billing_phone',
				'label'			=> 'Customer billing phone number',
				'value'			=> function ($checkout_details) {
					$other_fields = isset($checkout_details['other_fields']) ? $checkout_details['other_fields'] : '';
					$other_fields = maybe_unserialize($other_fields);
					if(empty($other_fields)){
						return '';
					}
					$phone_number = $other_fields['wcf_phone_number'];
					$location = isset($other_fields['wcf_location']) ? explode(', ', $other_fields['wcf_location']) : '';
					$country_code = isset($location[0]) ? $location[0] : '';
					$phone_number = Notifier_Woocommerce::get_formatted_phone_number($phone_number, $country_code);
					return html_entity_decode(sanitize_text_field($phone_number));
				}
			)
		);

		return $recipient_fields;
	}

}
