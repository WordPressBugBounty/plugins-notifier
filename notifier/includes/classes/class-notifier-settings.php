<?php
/**
 * Settings page class
 *
 * @package    Wa_Notifier
 */
class Notifier_Settings {

	/**
	 * Init
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__ , 'setup_admin_page') );
        add_action( 'admin_init', array( __CLASS__ , 'save_settings_fields' ) );
        add_action( 'admin_init', array( __CLASS__ , 'disconnect_notifier' ) );
		add_action( 'wp_ajax_notifier_preview_btn_style', array(__CLASS__, 'preview_btn_style'));
	}

	/**
	 * Add settings page to men
	 */
	public static function setup_admin_page () {
		if (!Notifier::is_api_active()){
			return;
		}

		add_submenu_page( NOTIFIER_NAME, 'Settings', 'Settings', 'manage_options', NOTIFIER_NAME . '-settings', array( __CLASS__, 'output' ) );
	}

	/**
	 * Output
	 */
	public static function output() {
        include_once NOTIFIER_PATH . '/views/admin-settings.php';
	}

	/**
	 * Get settings tabs
	 */
	private static function get_settings_tabs() {
		$tabs = array(
			'general'       => 'General',
			'woocommerce'	=> 'WooCommerce',
			'whatsapp_chat_button' => 'WhatsApp Chat Button',
			'api'           => 'API',
			'advanced'      => 'Advanced',
		);

		if ( ! class_exists( 'WooCommerce' ) ) {
			unset($tabs['woocommerce']);
		}

		return $tabs;
	}

	/**
	 * Check if on settings page
	 */
	public static function is_settings_page() {
		$current_page = isset($_GET['page']) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		return strpos($current_page, NOTIFIER_NAME . '-settings') !== false;
	}

	/**
	 * Settings fields
	 */
	private static function settings_fields($tab) {
		$api_key = get_option(NOTIFIER_PREFIX . 'api_key');
		if(Notifier::is_api_active() && '' != $api_key) {
			$disabled = true;
			$description = 'You are successfully connected to WANotifier.com. <a href="?notifier_disconnect=1">Disconnect</a>.';
		}
		else{
			$disabled = false;
			$description = 'You are not connected to WANotifier.com yet. Please enter valid API key and save the settings. You can get your WANotifier.com API Key from <a href="https://app.wanotifier.com/settings/api/" target="_blank">here</a>.';
		}
		$settings = array();
		switch ($tab) {
			case 'general':
				$settings = array(
					array(
						'title'			=> 'General',
						'type'			=> 'title',
						'description'	=> ''
					),
					array(
						'id' 			=> 'default_country_code',
						'title'			=> 'Default Country Code (Optional)',
						'type'			=> 'text',
						'placeholder'	=> 'Eg. +91',
						'description'	=> 'Enter default country code that will be added to all recipient phone number fields before sending to WANotifier that do not start with a + sign.',
						'default'		=> ''
					),
				);

				break;
			case 'whatsapp_chat_button':
				$settings = array(
					array(
						'title'			=> 'WhatsApp Chat Button',
						'description'	=> 'Add a WhatsApp chat button to your website to let your visitors start chat with you on WhatsApp.',
						'type'			=> 'title',
					),
					array(
						'id' 			=> 'ctc_enable',
						'title'			=> 'Enable',
						'type'			=> 'checkbox',
						'default'		=> '',
						'name'          => 'ctc_enable',
					),
					array(
						'id' 			=> 'ctc_whatsapp_number',
						'title'			=> 'Whatsapp Number',
						'type'			=> 'text',
						'placeholder'	=> 'Enter your WhatsApp number',
						'name'          => 'ctc_whatsapp_number',
						'description'	=> 'Enter your WhatsApp number with country code. Eg. +919876543210',
					),
					array(
						'id' 			=> 'ctc_greeting_text',
						'title'			=> 'Greeting Message',
						'type'			=> 'text',
						'name'          => 'ctc_greeting_text',
						'placeholder'	=> 'Enter your greeting message here',
						'description'	=> 'This text will be added to user\'s WhatsApp chat text field when they click on the button.' ,
					),
					array(
						'id' 			=> 'ctc_button_style',
						'title'			=> 'Button Style',
						'name'          => 'ctc_button_style',
						'class'         => 'chat-button-style',
						'type'			=> 'select',
						'default'		=> '',
						'options'       => self::get_button_styles(),
						'description'	=> 'Select a button style. Preview will be shown on bottom right of this screen. You can update button style by writing custom CSS in your theme.',
					),
					array(
						'id' 			=> 'ctc_custom_button_image_url',
						'title'			=> 'Button image url',
						'type'			=> 'text',
						'placeholder'	=> 'https://',
						'name'          => 'ctc_custom_button_image_url',
						'description'	=> 'Enter button image url here.',
						'tr_class'      => 'notifier-chat-btn-image-url',
					),
				);
				break;
			case 'api':
				$settings = array(
					array(
						'title'			=> 'API Configuration',
						'description'	=> '',
						'type'			=> 'title',
					),
					array(
						'id' 			=> 'api_key',
						'title'			=> 'WANotifier.com API key',
						'type'			=> 'text',
						'placeholder'	=> 'Enter your WANotifier.com API key here',
						'default'		=> '',
						'description'	=> $description,
						'disabled'		=> $disabled
					),
				);
				break;
			case 'advanced':
				$settings = array(
					array(
						'title'			=> 'Advanced Settings',
						'description'	=> '',
						'type'			=> 'title',
					),
					array(
						'id' 			=> 'hidden_custom_keys',
						'title'			=> 'Hidden custom meta keys', 
						'type'			=> 'checkbox',
						'label'         => 'Enable',
						'description'	=> 'Enable hidden custom meta keys that start with underscores (e.g. _field_name) to be avaialbe in Data and Recipient Fields. Note that enabling this might impact your website performance slightly.',
						'default'		=> ''
					),
					array(
						'id' 			=> 'enable_async_triggers',
						'title'			=> 'Async triggers',
						'type'			=> 'checkbox',
						'default'		=> '',
						'label'         => 'Enable',
						'name'          => 'enable_async_triggers',
						'description'	=> 'Plugin slowing down checkout or form submission? Enable this option to send triggers asynchronously using Action Scheduler. Note that if you have a site with strong caching, this might not work as expected.' ,
					),
					array(
						'id' 			=> 'enable_activity_log',
						'title'			=> 'Activity log',
						'type'			=> 'checkbox',
						'default'		=> '',
						'label'         => 'Enable',
						'name'          => 'enable_activity_log',
						'description'	=> 'Enabling this option will activate the activity logging feature. You can view activity logs <a href="/wp-admin/admin.php?page=notifier-tools">here</a>.' ,
					),					
				);
				break;
			case 'woocommerce':
				if ( class_exists( 'WooCommerce' ) ) {
					$settings = array(
						array(
							'title'			=> 'Checkout',
							'description'	=> '',
							'type'			=> 'title',
						),
						array(
							'id' 			=> 'enable_opt_in_checkbox_checkout',
							'title'			=> 'Opt-in Consent on Checkout',
							'type'			=> 'checkbox',
							'default'		=> '',
							'label'			=> 'Enable',
							'name'          => 'enable_opt_in_checkbox_checkout',
							'description'	=> 'Add an opt-in checkbox to WooCommerce checkout form. Once enabled, WhatsApp notification will be sent only to customers who opt-in during checkout.'
						),
						array(
							'id' 			=> 'checkout_opt_in_checkbox_text',
							'title'			=> 'Opt-in Checkbox Text',
							'type'			=> 'textarea',
							'placeholder'	=> 'Enter text for the opt-in checkbox',
							'default'		=> 'Receive updates on WhatsApp',
							'name'          => 'checkout_opt_in_checkbox_text',
						),
						array(
							'title'			=> 'Abandoned Cart',
							'description'	=> '',
							'type'			=> 'title',
						),
						array(
							'id' 			=> 'enable_abandonment_cart_tracking',
							'title'			=> 'Abandoned cart tracking',
							'type'			=> 'checkbox',
							'default'		=> '',
							'label'			=> 'Start capturing abandoned carts',
							'name'          => 'enable_abandonment_cart_tracking',
							'description'	=> 'Enable this to start capturing abandoned carts in database. Carts will be considered as abandoned if order is not placed by the user after adding items in the cart within cart abandoned cut-off time set below.'
						),
						array(
							'id' 			=> 'abandonment_cart_cron_run_time',
							'title'			=> 'Cart abandoned cut-off time',
							'type'			=> 'number',
							'placeholder'	=> 'Enter time in minutes. Eg. 20',
							'description'	=> 'Enter time in minutes after which the cart will be considered as abandoned. This is the time from when items are added / updated in cart.',
							'default'		=> '20',
							'custom_attributes'	=> array('min' => 20),
						),
					);
				}
				break;
		}
		$settings = apply_filters( 'notifier_$tab_settings_fields', $settings );
		return $settings;
	}

	/**
	 * Generate HTML for displaying individual fields
	 */
	public static function display_field( $field ) {
		$field = wp_parse_args($field, array(
			'title'			=> '',
			'description'	=> '',
			'label'			=> ''
		));

		$html = '';

		if (isset($field['id'])) {
			$option_name = NOTIFIER_PREFIX . $field['id'];
			$option = get_option( $option_name );
		}

		$data = isset($option) ? $option : '';
		if ( isset( $field['default'] ) && empty($option) ) {
			$data = $field['default'];
		}

		$custom_attributes = array();
		if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {
			foreach ( $field['custom_attributes'] as $attribute => $value ) {
				$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
			}
		}

		if ( isset($field['required']) && $field['required'] ) {
			$custom_attributes[] = 'required="required"';
		}

		if ( isset($field['disabled']) && $field['disabled'] ) {
			$custom_attributes[] = 'disabled="disabled"';
		}

		if(isset($field['tr_class'])){
			$tr_class = $field['tr_class'];
		}else{
			$tr_class = '';
		}

		$custom_attributes_string = implode( ' ', $custom_attributes );

		if ( 'title' === $field['type']) {
			$html .= '<tr class="'.esc_attr($tr_class).'"><th class="section-title" colspan="2"><h3>' . $field['title'] . '</h3>';
			$html .= '<p class="description">' . $field['description'] . '</p></th></tr>';
		} else {
			$html .= '<tr class="'.esc_attr($tr_class).'">';
			$html .= '<th>' . $field['title'] . '</th>';
			$html .= '<td>';

			switch ( $field['type'] ) {

				case 'text':
				case 'password':
				case 'number':
					$html .= '<input id="' . esc_attr( $option_name ) . '" type="' . $field['type'] . '" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" value="' . esc_attr($data) . '" '.$custom_attributes_string.'/>';
				    break;

				case 'textarea':
					$html .= '<textarea id="' . esc_attr( $option_name ) . '" rows="5" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" '.$custom_attributes_string.'>' . esc_textarea($data) . '</textarea><br/>';
				    break;

				case 'checkbox':
					$checked = '';
					if ( $option && 'yes' == $option ) {
						$checked = 'checked="checked"';
					}
					$html .= '<label><input id="' . esc_attr( $option_name ) . '" type="' . $field['type'] . '" name="' . esc_attr( $option_name ) . '" ' . $checked . ' '.$custom_attributes_string.' value="yes"/>' . $field['label'] . '</label>';
				    break;

				case 'checkbox_multi':
					foreach ( $field['options'] as $k => $v ) {
						$checked = false;
						if ( in_array( $k, $data ) ) {
							$checked = true;
						}
						$html .= '<p><label for="' . esc_attr( $field['id'] . '_' . $k ) . '"><input type="checkbox" ' . checked( $checked, true, false ) . ' name="' . esc_attr( $option_name ) . '[]" value="' . esc_attr( $k ) . '" id="' . esc_attr( $option_name . '_' . $k ) . '" '.$custom_attributes_string.'/> ' . $v . '</label></p>';
					}
				    break;

				case 'radio':
					foreach ( $field['options'] as $k => $v ) {
						$checked = false;
						if ( $k == $data ) {
							$checked = true;
						}
						$html .= '<p><label for="' . esc_attr( $field['id'] . '_' . $k ) . '"><input type="radio" ' . checked( $checked, true, false ) . ' name="' . esc_attr( $option_name ) . '" value="' . esc_attr( $k ) . '" id="' . esc_attr( $option_name . '_' . $k ) . '" '.$custom_attributes_string.'/> ' . $v . '</label></p>';
					}
				    break;

				case 'select':
					$html .= '<select name="' . esc_attr( $option_name ) . '" id="' . esc_attr( $field['id'] ) . '" '.$custom_attributes_string.'>';
					foreach ( $field['options'] as $k => $v ) {
						$selected = false;
						if ( $k == $data ) {
							$selected = true;
						}
						$html .= '<option ' . selected( $selected, true, false ) . ' value="' . esc_attr( $k ) . '">' . $v . '</option>';
					}
					$html .= '</select> ';
				    break;

				case 'select_multi':
					$html .= '<select name="' . esc_attr( $option_name ) . '[]" id="' . esc_attr( $field['id'] ) . '" multiple="multiple" '.$custom_attributes_string.'>';
					foreach ( $field['options'] as $k => $v ) {
						$selected = false;
						if ( in_array( $k, $data ) ) {
							$selected = true;
						}
						$html .= '<option ' . selected( $selected, true, false ) . ' value="' . esc_attr( $k ) . '" />' . $v . '</label> ';
					}
					$html .= '</select> ';
				    break;

				case 'file':
					$uploader_title = isset($field['uploader_title']) ? $field['uploader_title'] : 'Upload media';
					$uploader_button_text = isset($field['uploader_button_text']) ? $field['uploader_button_text'] : 'Select';
					$file_types = isset($field['uploader_supported_file_types']) ? $field['uploader_supported_file_types'] : array();

					$image_thumb = '';
					$video_url = '';
					$show_image = 'hide';
					$show_video = 'hide';

					$html .= '<span class="notifier-media-preview">';
					if ( '' != $data ) {
						$file_type = $field['uploader_supported_file_types'];
						switch ($file_type) {
						    case 'image':
						    case 'image/jpeg':
						    case 'image/png':
						    case 'application/pdf':
						    	$image_thumb = wp_get_attachment_thumb_url( $data );
						    	$show_image = '';
						    	$show_video = 'hide';
						    	break;

						    case 'video/mp4':
						    	$video_url = wp_get_attachment_url( $data );
								$show_image = 'hide';
								$show_video = '';
								break;

							default:
								$show_image = 'hide';
								$show_video = 'hide';
					  	}
					}
					$html .= '<img id="' . esc_attr( $option_name ) . '_preview_image" class="notifier-media-preview-item '. esc_attr($show_image) .'" src="' . esc_url($image_thumb) . '" />';
				  	$html .= '<video id="' . esc_attr( $option_name ) . '_preview_video" class="notifier-media-preview-item '. esc_attr($show_video) .'"  width="300" height="169" controls muted><source src="' . esc_url($video_url) . '"  type="video/mp4"></video><br/>';
					$html .= '</span>';

					$html .= '<input id="' . esc_attr( $option_name ) . '_button" type="button" data-uploader_title="' . esc_attr($uploader_title) . '" data-uploader_button_text="' . esc_attr($uploader_button_text) . '" data-uploader_supported_file_types="' . esc_attr($file_types) . '" class="notifier-media-upload-button button" value="' . 'Upload' . '" /> ';
					$html .= '<input id="' . esc_attr( $option_name ) . '_delete" type="button" class="notifier-media-delete-button button" value="' . 'Remove' . '" />';
					$html .= '<input id="' . esc_attr( $option_name ) . '" class="notifier-media-attachment-id" type="hidden" name="' . esc_attr( $option_name ) . '" value="' . esc_attr( $data ) . '"/><br/>';
				    break;

				case 'color':
					?>
					<div class="color-picker" style="position:relative;">
				        <input type="text" name="<?php echo esc_attr( $option_name ); ?>" class="color" value="<?php echo esc_attr( $data ); ?>" />
				        <div style="position:absolute;background:#FFF;z-index:99;border-radius:100%;" class="colorpicker"></div>
				    </div>
				    <?php
				    break;

			}

			if ( '' != $field['description'] ) {
				$html .= '<p class="description">' . wp_kses_post($field['description']) . '</p>';
			}
		}

		//phpcs:ignore
		echo $html;
	}

	/**
	 * Show the setting fields
	 */
	public static function show_settings_fields ($tab) {
		$setting_fields = self::settings_fields($tab);
		echo "<table class='notifier-fields-table'>";
		foreach ($setting_fields as $field) {
			self::display_field( $field );
		}
		echo '</table>';
	}

	/**
	 * Save the settings.
	 *
	 * TODO - check fields other than text and textarea
	 */
	public static function save_settings_fields() {
		if ( ! self::is_settings_page() ) {
			return;
		}

		if ( ! isset( $_POST['save'] ) ) {
			return;
		}

		//phpcs:ignore
		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], NOTIFIER_NAME . '-settings' ) ) {
			return;
		}

		$tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'general';

		$settings_fields = self::settings_fields($tab);
		$data = $_POST;
		$update_options = array();

		// Loop options and get values to save.
		foreach ( $settings_fields as $option ) {
			if ( ! isset( $option['id'] ) || ! isset( $option['type'] ) ) {
				continue;
			}

			if ( isset( $option['disabled'] ) && $option['disabled'] ) {
				continue;
			}

			$option_name  = NOTIFIER_PREFIX . $option['id'];
			$setting_name = '';
			$raw_value    = isset( $data[ $option_name ] ) ? wp_unslash( $data[ $option_name ] ) : null;

			// Format the value based on option type.
			switch ( $option['type'] ) {
				case 'checkbox':
					$value = '1' === $raw_value || 'yes' === $raw_value ? 'yes' : 'no';
					break;
				case 'textarea':
					$value = wp_kses_post( trim( $raw_value ) );
					break;
				case 'select':
					$allowed_values = empty( $option['options'] ) ? array() : array_map( 'strval', array_keys( $option['options'] ) );
					if ( empty( $option['default'] ) && empty( $allowed_values ) ) {
						$value = null;
						break;
					}
					$default = ( empty( $option['default'] ) ? $allowed_values[0] : $option['default'] );
					$value   = in_array( $raw_value, $allowed_values, true ) ? $raw_value : $default;
					break;
				default:
					$value = sanitize_text_field( $raw_value );
					break;
			}

			// Check if option is an array and handle that differently to single values.
			if ( $option_name && $setting_name ) {
				if ( ! isset( $update_options[ $option_name ] ) ) {
					$update_options[ $option_name ] = get_option( $option_name, array() );
				}
				if ( ! is_array( $update_options[ $option_name ] ) ) {
					$update_options[ $option_name ] = array();
				}
				$update_options[ $option_name ][ $setting_name ] = $value;
			} else {
				$update_options[ $option_name ] = $value;
			}
		}

		// Save all options in our array.
		foreach ( $update_options as $name => $value ) {
			if('notifier_api_key' == $name){
				$current_api_key = get_option($name);
				if($current_api_key == $value){
					continue;
				}

				update_option( NOTIFIER_PREFIX . 'api_key', $value);
				delete_option( NOTIFIER_PREFIX . 'enabled_triggers' );
				delete_option( NOTIFIER_PREFIX . 'api_activated' );

				$params = array(
					'site_url'	=> site_url(),
					'source'	=> 'wp'
		    	);

				$response = Notifier::send_api_request( 'verify_api', $params, 'POST' );

				if($response->error){
					$notices[] = array(
						'message' => 'There was an error validating API key. Error: ' . $response->message,
						'type' => 'error'
					);
				}
				else{
					update_option('notifier_api_activated', 'yes');
					$notices[] = array(
						'message' => 'API key validated and saved successfully. Your triggers have been reset. Please enable and save your triggers again from the <a href="'.admin_url('admin.php?page=notifier').'">WANotifier</a> page.',
						'type' => 'success'
					);
				}
			}
			elseif('notifier_ctc_whatsapp_number' == $name && !notifier_validate_phone_number($value)){
				$notices[] = array(
					'message' => 'Please enter a valid WhatsApp phone number with country code.',
					'type' => 'error'
				);
			}
			else{
				update_option( $name, $value, 'yes' );
			}
		}

		if('advanced' == $tab){
			delete_transient( '_notifier_custom_meta_keys' );
		}

		if(empty($notices)){
			$notices[] = array(
				'message' => 'Settings updated successfully.',
				'type' => 'success'
			);
		}

		new Notifier_Admin_Notices($notices);
	}

	/**
	 * Disconnect WANotifier.com connection
	 */
	public static function disconnect_notifier(){
		if(isset($_GET['notifier_disconnect']) && '1' == $_GET['notifier_disconnect']){
			delete_option( NOTIFIER_PREFIX . 'api_key' );
			delete_option( NOTIFIER_PREFIX . 'enabled_triggers' );
			delete_option( NOTIFIER_PREFIX . 'api_activated' );
			$notices[] = array(
				'message' => 'Disconnected successfully. To re-connect with WANotifier.com, enter API key below and click on <b>Save and validate</b>.',
				'type' => 'success'
			);
			new Notifier_Admin_Notices($notices, true);
			wp_redirect(admin_url('admin.php?page=notifier'));
		}
	}

	/**
	 * Get button styles
	 */
	public static function get_button_styles(){
		return array(
			'default' => 'Select button style',
			'btn-style-1' => 'Style 1' ,
			'btn-style-2' => 'Style 2',
			'btn-style-3' => 'Style 3',
			'btn-style-4' => 'Style 4',
			'btn-custom-image' => 'Add your own image'
		);
	}

	/**
	 * Show Chat Button Preview
	 */
	public static function preview_btn_style(){
		$btn_style = isset($_POST['btn_style']) ? sanitize_text_field($_POST['btn_style']) : '';

		$button_styles = self::get_button_styles();
		$button_style_keys = array_keys($button_styles);

		if(! in_array($btn_style, $button_style_keys)){
			wp_send_json( array(
				'preview'  => 'Invalid request'
			) );
		}

		ob_start();
		include_once NOTIFIER_PATH.'templates/buttons/'.$btn_style.'.php';
		$btn_output = ob_get_clean();

		wp_send_json( array(
			'preview'  => $btn_output
		) );
	}
}
