<?php
/**
 * Formidable Form notifications
 *
 * @package    Wa_Notifier
 */
class Notifier_Formidable {

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
		$forms = FrmForm::getall();

		if(is_array($forms)){
			foreach($forms as $form){
				$form_id 	= $form->id;
				$title 	= $form->name;
				$trigger_id = 'formidableforms_' . $form_id;

				$triggers[] = array(
					'id'			=> $trigger_id,
					'label' 		=> 'Form "' . $title . '" is submitted',
					'description'	=> 'Trigger notification when <b>'.$title.'</b> form is submitted.',
					'merge_tags' 	=> self::get_merge_tags($form_id),
					'recipient_fields'	=> self::get_recipient_fields($form_id),
					'action'		=> array(
						'hook'		=> 'frm_after_create_entry',
						'callback' 	=> function ( $entry_id, $form_id ) use ( $trigger_id ) {
							$trigger_form_id = str_replace('formidableforms_', '', $trigger_id);
							if($form_id != $trigger_form_id){
								return;
							}

							$sanitized_data = array();
							foreach($_POST['item_meta'] as $key => $data){
								if(is_array($data)){
									$sanitized_data[$key] = notifier_sanitize_array($data);
								}
								else{
									$sanitized_data[$key] = sanitize_text_field($data);
								}
							}

							Notifier_Notification_Triggers::send_trigger_request( $trigger_id, $sanitized_data );
						},
						'args_num'	=> 2,
					)
				);
			}
		}

		$existing_triggers['Formidable Forms'] = $triggers;
		return $existing_triggers;
	}

	/**
	 * Get merge tags for the current form
	 */
	public static function get_merge_tags( $form_id ) {

		$merge_tags = Notifier_Notification_Merge_Tags::get_merge_tags();
		$formidableforms_fields = FrmField::get_all_for_form( $form_id );

		$excluded_field_types = array('submit', 'captcha', 'spam', 'hr', 'repeater', 'html');
		$form_fields = $formidableforms_fields;


		if(is_array($form_fields)){
			foreach($form_fields as $field){

				if(in_array($field->type, $excluded_field_types)){
					continue;
				}

				$field_name = $field->type;
				$field_id  = $field->id;
				$return_type = 'text';

				$merge_tags['Formidable Forms'][] = array(
					'id' 			=> 'formidableforms_' . $form_id . '_' . $field_id,
					'label' 		=> $field_name,
					'preview_value' => '',
					'return_type'	=> $return_type,
					'value'			=> function ( $sanitized_data ) use ( $field_id ) {
						$value = isset($sanitized_data[$field_id]) ? $sanitized_data[$field_id] : '';
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
	public static function get_recipient_fields( $form_id ){
		$formidableforms_fields = FrmField::get_all_for_form( $form_id );
		$form_fields = $formidableforms_fields;
		$recipient_fields = array();

		if(is_array($form_fields)){
			foreach($form_fields as $field){
				if('phone' != $field->type){
					continue;
				}

				$field_name = $field->name;
				$field_id  = $field->id;

				$recipient_fields['Formidable Forms'][] = array(
					'id' 			=> 'formidableforms_' . $form_id . '_' . $field_id,
					'label' 		=> $field_name,
					'value'			=> function ( $sanitized_data ) use ( $field_id ) {
						$value = isset($sanitized_data[$field_id]) ? $sanitized_data[$field_id] : '';
						return html_entity_decode(sanitize_text_field($value));
					}
				);
			}
		}
		return $recipient_fields;
	}
}
