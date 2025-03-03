<?php
/**
 * Gravity Forms notifications
 *
 * @package    Wa_Notifier
 */
class Notifier_GravityForms {

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
		$forms = GFAPI::get_forms(true);
		foreach($forms as $form){
			$trigger_id = 'gravityforms_' . $form['id'];
			$triggers[] = array(
				'id'			=> $trigger_id,
				'label' 		=> 'Form "' . $form['title'] . '" is submitted',
				'description'	=> 'Trigger notification when <b>'.$form['title'].'</b> form is submitted.',
				'merge_tags' 	=> self::get_merge_tags($form),
				'recipient_fields'	=> self::get_recipient_fields($form),
				'action'		=> array(
					'hook'		=> 'gform_after_submission_' . $form['id'],
					'callback' 	=> function ( $entry ) use ( $trigger_id ) {
						Notifier_Notification_Triggers::send_trigger_request( $trigger_id, $entry );
					}
				)
			);
		}
		$existing_triggers['Gravity Forms'] = $triggers;
		return $existing_triggers;
	}

	/**
	 * Get merge tags for the current form
	 */
	public static function get_merge_tags( $form ) {
		$merge_tags = Notifier_Notification_Merge_Tags::get_merge_tags();
		$gf_fields = array(
			'ip'			=> array(
				'label'		=> 'User IP',
				'preview'	=> '0.0.0.0'
			),
			'source_url'	=> array(
				'label'		=> 'Source URL',
				'preview'	=> site_url()
			),
			'date_created'	=> array(
				'label'		=> 'Date created'
			),
			'date_updated'	=> array(
				'label'		=> 'Date updated'
			),
			'user_agent'	=> array(
				'label'		=> 'User agent',
				'preview'	=> 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36'
			)
		);

		foreach ($gf_fields as $field_id => $field_data) {
			$merge_tags['Gravity Forms'][] = array(
				'id' 			=> 'gravityforms_' . $field_id,
				'label' 		=> $field_data['label'],
				'preview_value' => isset($field_data['preview']) ? $field_data['preview'] : '',
				'return_type'	=> isset($field_data['return_type']) ? $field_data['return_type'] : 'text',
				'value'			=> function ( $entry ) use ($field_id) {
					$value = isset($entry[$field_id]) ? $entry[$field_id] : '';
					return html_entity_decode(sanitize_text_field($value));
				}
			);
		}

		$excluded_field_types = array('html', 'section', 'captcha', 'page');
		foreach($form['fields'] as $field){
			if(in_array($field->type, $excluded_field_types)){
				continue;
			}

			if('post_image' == $field->type){
				$return_type = 'image';
			}
			else{
				$return_type = 'text';
			}

			if(empty($field->inputs)) {
				$field_id = $field->id;
				$field_type = $field->type;
				$merge_tags['Gravity Forms'][] = array(
					'id' 			=> 'gravityforms_' . $form['id'] . '_' . $field_id,
					'label' 		=> $field->label,
					'preview_value' => '',
					'return_type'	=> $return_type,
					'value'			=> function ( $entry ) use ( $field_id, $field_type ) {
						if('list' == $field_type){
							$values = maybe_unserialize( $entry[$field_id] );
							$value = implode(', ', $values);
						}
						else{
							$value = isset($entry[$field_id]) ? $entry[$field_id] : '';
						}
						return html_entity_decode(sanitize_text_field($value));
					}
				);
			}
			else if('checkbox' == $field->type){
				$field_id = $field->id;
				$merge_tags['Gravity Forms'][] = array(
					'id' 			=> 'gravityforms_' . $form['id'] . '_' . $field_id,
					'label' 		=> $field->label,
					'preview_value' => '',
					'return_type'	=> $return_type,
					'value'			=> function ( $entry ) use ( $field_id ) {
						$values = array();
						foreach($entry as $key => $value){
							if ( strpos($key, $field_id . '.') !== false && '' != trim($value)){
								$values[] = $value;
							}
						}
						$final_value = implode(', ', $values);
						return html_entity_decode(sanitize_text_field($final_value));
					}
				);
			}
			else {
				foreach($field->inputs as $input){
					$field_id = $input['id'];
					if('1' == $input['isHidden']){
						continue;
					}
					$merge_tags['Gravity Forms'][] = array(
						'id' 			=> 'gravityforms_' . $form['id'] . '_' . $field_id,
						'label' 		=> $field->label . ' (' . $input['label'] . ')',
						'preview_value' => '',
						'return_type'	=> $return_type,
						'value'			=> function ( $entry ) use ( $field_id ) {
							$value = isset($entry[$field_id]) ? $entry[$field_id] : '';
							return html_entity_decode(sanitize_text_field($value));
						}
					);
				}
			}
		}
		return $merge_tags;
	}

	/*
	 * Get recipient fields
	 */
	public static function get_recipient_fields($form){
		$recipient_fields = array();
		foreach($form['fields'] as $field){
			if('phone' != $field->type){
				continue;
			}
			$field_id = $field->id;
			$recipient_fields['Gravity Forms'][] = array(
				'id' 			=> 'gravityforms_' . $form['id'] . '_' . $field_id,
				'label' 		=> $field->label,
				'value'			=> function ( $entry ) use ( $field_id ) {
					$value = isset($entry[$field_id]) ? $entry[$field_id] : '';
					return html_entity_decode(sanitize_text_field($value));
				}
			);
		}
		return $recipient_fields;
	}

}
