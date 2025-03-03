<?php
/**
 * Ninja Forms notifications
 *
 * @package    Wa_Notifier
 */
class Notifier_NinjaForms {

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
		$forms = Ninja_Forms()->form()->get_forms();

		if(is_array($forms)){
			foreach($forms as $form){
				$form_id 	= $form->get_id();
				$title 	= $form->get_setting( 'form_title' );
				$trigger_id = 'ninjaforms_' . $form_id;

				$triggers[] = array(
					'id'			=> $trigger_id,
					'label' 		=> 'Form "' . $title . '" is submitted',
					'description'	=> 'Trigger notification when <b>'.$title.'</b> form is submitted.',
					'merge_tags' 	=> self::get_merge_tags($form_id),
					'recipient_fields'	=> self::get_recipient_fields($form_id),
					'action'		=> array(
						'hook'		=> 'ninja_forms_after_submission',
						'callback' 	=> function ( $form_data ) use ( $trigger_id, $form_id ) {
							if( $form_id != $form_data['form_id'] ) {
								return;
							}

							Notifier_Notification_Triggers::send_trigger_request( $trigger_id, $form_data );
						},
					)
				);
			}
		}

		$existing_triggers['Ninja Forms'] = $triggers;
		return $existing_triggers;
	}

	/**
	 * Get merge tags for the current form
	 */
	public static function get_merge_tags( $form_id ) {

		$merge_tags = Notifier_Notification_Merge_Tags::get_merge_tags();
		$ninjaforms_fields = Ninja_Forms()->form( $form_id )->get_fields();

		$excluded_field_types = array('submit', 'recaptcha', 'confirm', 'spam', 'hr', 'repeater', 'save');
		$form_fields = $ninjaforms_fields;

		if(is_array($form_fields)){
			foreach($form_fields as $field){

				if(in_array($field->get_setting('type'), $excluded_field_types)){
					continue;
				}

				$field_name = $field->get_setting('label');
				$field_id  = $field->get_id();
				$return_type = 'text';

				$merge_tags['Ninja Forms'][] = array(
					'id' 			=> 'ninjaforms_' . $form_id . '_' . $field_id,
					'label' 		=> $field_name,
					'preview_value' => '',
					'return_type'	=> $return_type,
					'value'			=> function ( $form_data ) use ( $field_id ) {
						$value = isset($form_data['fields'][$field_id][ 'value' ]) ? $form_data['fields'][$field_id][ 'value' ] : '';
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
		$ninjaforms_fields = Ninja_Forms()->form( $form_id )->get_fields();
		$form_fields = $ninjaforms_fields;
		$recipient_fields = array();

		if(is_array($form_fields)){
			foreach($form_fields as $field){
				if('phone' != $field->get_setting('type')){
					continue;
				}

				$field_name = $field->get_setting('label');
				$field_id  = $field->get_id();

				$recipient_fields['Ninja Forms'][] = array(
					'id' 			=> 'ninjaforms_' . $form_id . '_' . $field_id,
					'label' 		=> $field_name,
					'value'			=> function ( $form_data ) use ( $field_id ) {
						$value = isset($form_data['fields'][$field_id][ 'value' ]) ? $form_data['fields'][$field_id][ 'value' ] : '';
						return html_entity_decode(sanitize_text_field($value));
					}
				);
			}
		}
		return $recipient_fields;
	}
}
