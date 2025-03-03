<?php
/**
 * WPForms notifications
 *
 * @package    Wa_Notifier
 */
class Notifier_WPForms {

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
			'post_type' 	=> 'wpforms',
			'post_status' 	=> 'publish',
			'numberposts' 	=> -1,
			'fields' 		=> 'ids'
		));

		foreach($form_ids as $form_id){
			$trigger_id = 'wpf_' . $form_id;
			$title = get_the_title( $form_id );
			$triggers[] = array(
				'id'			=> $trigger_id,
				'label' 		=> 'Form "' . $title . '" is submitted',
				'description'	=> 'Trigger notification when <b>'.$title.'</b> form is submitted.',
				'merge_tags' 	=> self::get_merge_tags($form_id),
				'recipient_fields'	=> self::get_recipient_fields($form_id),
				'action'		=> array(
					'hook'		=> 'wpforms_process_complete_'.$form_id,
					'callback' 	=> function ( $fields, $entry ) use ( $trigger_id ) {
						Notifier_Notification_Triggers::send_trigger_request( $trigger_id, $fields );
					},
					'args_num'	=> 2,
				)
			);
		}
		$existing_triggers['WPForms'] = $triggers;
		return $existing_triggers;
	}

	/**
	 * Get merge tags for the current form
	 */
	public static function get_merge_tags( $form_id ) {

		$merge_tags = Notifier_Notification_Merge_Tags::get_merge_tags();
		$wpforms = wpforms()->get( 'form' )->get( $form_id, [ 'content_only' => true ] );

		$excluded_field_types = array('html', 'captcha_recaptcha', 'pagebreak', 'divider');
		$form_fields = $wpforms['fields'];

		if(is_array($form_fields)){
			foreach($form_fields as $field){

				if(in_array($field['type'], $excluded_field_types)){
					continue;
				}

				$field_name = $field['label'];
				$field_id = $field['id'];
				$return_type = 'text';

				$merge_tags['WPForms'][] = array(
					'id' 			=> 'wpf_' . $form_id . '_' . $field_id,
					'label' 		=> $field_name,
					'preview_value' => '',
					'return_type'	=> $return_type,
					'value'			=> function ( $fields ) use ( $field_id ) {
						$value = isset($fields[$field_id][ 'value' ]) ? $fields[$field_id][ 'value' ] : '';
						if(is_array($value)){
							$value = implode(', ', $value);
						}
						return html_entity_decode(sanitize_text_field($value));
					}
				);
			}
		}
		return $merge_tags;
	}

	/*
	 * Get recipient fields
	 */
	public static function get_recipient_fields($form_id){
		$wpforms = wpforms()->get( 'form' )->get( $form_id, [ 'content_only' => true ] );
		$form_fields = $wpforms['fields'];

		$recipient_fields = array();

		if(is_array($form_fields)){
			foreach($form_fields as $field){
				if('phone' != $field['type']){
					continue;
				}

				$field_name = $field['label'];
				$field_id = $field['id'];

				$recipient_fields['WPForms'][] = array(
					'id' 			=> 'wpf_' . $form_id . '_' . $field_id,
					'label' 		=> $field_name,
					'value'			=> function ( $fields ) use ( $field_id ) {
						$value = isset($fields[$field_id][ 'value' ]) ? $fields[$field_id][ 'value' ] : '';
						return html_entity_decode(sanitize_text_field($value));
					}
				);
			}
		}
		return $recipient_fields;
	}
}
