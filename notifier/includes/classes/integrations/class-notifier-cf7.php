<?php
/**
 * Contact Form 7 notifications
 *
 * @package    Wa_Notifier
 */
class Notifier_ContactForm7 {

	/**
	 * Init.
	 */
	public static function init() {
		add_filter( 'notifier_notification_triggers', array( __CLASS__, 'add_triggers'), 10 );
	}

	/**
	 * Add notification triggers
	 */
	public static function add_triggers($existing_triggers) {
		$triggers = array();
		$form_ids = get_posts(array (
			'post_type' 	=> 'wpcf7_contact_form',
			'post_status' 	=> 'publish',
			'numberposts' 	=> -1,
			'fields' 		=> 'ids'
		));
		foreach($form_ids as $form_id){
			$trigger_id = 'cf7_' . $form_id;
			$title = get_the_title( $form_id );
			$triggers[] = array(
				'id'			=> $trigger_id,
				'label' 		=> 'Form "' . $title . '" is submitted',
				'description'	=> 'Trigger notification when <b>'.$title.'</b> form is submitted.',
				'merge_tags' 	=> self::get_merge_tags($form_id),
				'recipient_fields'	=> self::get_recipient_fields($form_id),
				'action'		=> array(
					'hook'		=> 'wpcf7_before_send_mail',
					'callback' 	=> function ( $form ) use ( $trigger_id ) {
						$trigger_form_id = str_replace('cf7_', '', $trigger_id);
						if($form->id() != $trigger_form_id){
							return;
						}
						$sanitized_data = array();
						foreach($_POST as $key => $data){
							if(is_array($data)){
								$sanitized_data[$key] = notifier_sanitize_array($data);
							}
							else{
								$sanitized_data[$key] = sanitize_text_field($data);
							}
						}
						Notifier_Notification_Triggers::send_trigger_request( $trigger_id, $sanitized_data );
					}
				)
			);
		}
		$existing_triggers['Contact Form 7'] = $triggers;
		return $existing_triggers;
	}

	/**
	 * Get merge tags for the current form
	 */
	public static function get_merge_tags( $form_id ) {
		$fieldsArray   = get_post_meta($form_id);
		$meta          = $fieldsArray['_form'][0];
		$TagsManager   = WPCF7_FormTagsManager::get_instance();
		$form_fields   = $TagsManager->filter( $meta, array() );

		$merge_tags = Notifier_Notification_Merge_Tags::get_merge_tags();

		$excluded_field_types = array('submit');
		foreach($form_fields as $field){
			if(in_array($field->type, $excluded_field_types)){
				continue;
			}
			$return_type = 'text';
			$field_name = $field->name;
			$field_type = $field->type;
			$merge_tags['Contact Form 7'][] = array(
				'id' 			=> 'cf7_' . $form_id . '_' . $field_name,
				'label' 		=> $field_name,
				'preview_value' => '',
				'return_type'	=> $return_type,
				'value'			=> function ( $sanitized_data ) use ( $field_name ) {
					$value = isset($sanitized_data[$field_name]) ? $sanitized_data[$field_name] : '';
					if(is_array($value)){
						$value = implode(', ', $value);
					}
					return html_entity_decode(sanitize_text_field($value));
				}
			);
		}

		return $merge_tags;
	}

	/*
	 * Get recipient fields
	 */
	public static function get_recipient_fields($form_id){
		$fieldsArray   = get_post_meta( $form_id );
		$meta          = $fieldsArray['_form'][0];
		$TagsManager   = WPCF7_FormTagsManager::get_instance();
		$form_fields   = $TagsManager->filter( $meta, array() );

		$recipient_fields = array();
		foreach($form_fields as $field){

			if('tel' != $field->basetype){
				continue;
			}

			$field_name = $field->name;
			$recipient_fields['Contact Form 7'][] = array(
				'id' 			=> 'cf7_' . $form_id . '_' . $field_name,
				'label' 		=> $field_name,
				'value'			=> function ( $sanitized_data ) use ( $field_name ) {
					$value = isset($sanitized_data[$field_name]) ? $sanitized_data[$field_name] : '';
					return html_entity_decode(sanitize_text_field($value));
				}
			);
		}
		return $recipient_fields;
	}

}
