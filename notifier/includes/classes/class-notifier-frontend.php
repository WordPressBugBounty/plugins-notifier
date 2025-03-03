<?php
/**
 *
 * @package    Wa_Notifier
 */
class Notifier_Frontend {
	/**
	 * Init
	 */
	public static function init() {
        add_action( 'wp_footer', array( __CLASS__ , 'display_whatsapp_chat_button' ) );
	}

	/**
	 * Display Whatsapp chat button.
	 */
	public static function display_whatsapp_chat_button() {
		$enable_click_chat = get_option('notifier_ctc_enable');
		$btn_style = get_option('notifier_ctc_button_style');
		$click_chat_number = get_option('notifier_ctc_whatsapp_number');

		if(!$btn_style){
			return;
		}

		if($enable_click_chat === 'yes' &&  !empty($click_chat_number)){
			if($btn_style !== 'default'){
				include_once NOTIFIER_PATH . 'templates/buttons/'.$btn_style.'.php';
			}
		}
	}
}
