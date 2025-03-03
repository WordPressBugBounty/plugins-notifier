<?php
/**
 * FluentForms notifications
 *
 * @package    Wa_Notifier
 */
class Notifier_FluentForms {

	/**
	 * Init.
	 */
	public static function init() {
		add_filter( 'notifier_notification_triggers', array( __CLASS__, 'add_triggers'), 10 );
	}

	/**
	 * Add notification triggers
	 */
	public static function add_triggers( $existing_triggers ) {
		$triggers = array();

		$formApi = fluentFormApi('forms');

		$atts = [
		    'status' => 'published',
		    'sort_column' => 'id',
		    'sort_by' => 'DESC',
		    'page'        => 1,
		];

		$forms = $formApi->forms($atts, $withFields = false);

		if(is_array($forms['data'])){
			foreach($forms['data'] as $form){
				$trigger_id = 'fluentforms_' . $form->id;
				$form_id = $form->id;
				$title = $form->title;
				$triggers[] = array(
					'id'			=> $trigger_id,
					'label' 		=> 'Form "' . $title . '" is submitted',
					'description'	=> 'Trigger notification when <b>'.$title.'</b> form is submitted.',
					'merge_tags' 	=> self::get_merge_tags($form->id),
					'recipient_fields'	=> self::get_recipient_fields($form->id),
					'action'		=> array(
						'hook'		=> 'fluentform_submission_inserted',
						'callback' 	=> function ( $entryId, $formData, $form ) use ( $trigger_id, $form_id ) {
							if($form->id != $form_id){
								return;
							}
							Notifier_Notification_Triggers::send_trigger_request( $trigger_id, $formData );
						},
						'args_num'	=> 3,
					)
				);
			}
		}

		$existing_triggers['Fluent Forms'] = $triggers;
		return $existing_triggers;
	}

	/**
	 * Get merge tags for the current form
	 */
	public static function get_merge_tags( $form_id ) {
		$merge_tags = Notifier_Notification_Merge_Tags::get_merge_tags();

		$fields_data = array();
		$formApi = fluentFormApi('forms')->form($form_id);
		$form_fields = $formApi->fields();
		$excluded_field_types = array('custom_submit_button', 'section_break', 'custom_html','tabular_grid','repeater_field','terms_and_condition','action_hook','form_step','chained_select','save_progress_button', 'cpt_selection','shortcode');

		if(is_array($form_fields)){
			foreach($form_fields['fields'] as $key => $value){
				if(in_array($value['element'], $excluded_field_types)){
					continue;
				}

				if('input_name' === $value['element']){
					foreach($value['fields'] as $sub_fkey => $sub_fvalue){
						$field_type = $sub_fvalue['attributes']['type'];
						$field_lbl = $sub_fvalue['settings']['label'];
						$field_name = $sub_fvalue['attributes']['name'];

						$fields_data[$value['attributes']['name']]['type'] = $field_type;
						$fields_data[$value['attributes']['name']]['label'] = $value['settings']['admin_field_label'];
						$fields_data[$value['attributes']['name']][$sub_fkey] = [
							'label' => $field_lbl
						];
					}
				}else if('container' === $value['element']){
					if(!empty($value['columns']) && is_array($value['columns'])){
						foreach($value['columns'] as $col_key => $col_value){
							foreach($col_value['fields'] as $ckey => $cval){

								if(in_array($cval['element'], $excluded_field_types)){
									continue;
								}

								$field_type = isset($cval['attributes']['type']) ? $cval['attributes']['type']: '';
								$field_lbl = !empty($cval['settings']['label'])?$cval['settings']['label']:'Field_'.$cval['attributes']['name'];
								$field_name = $cval['attributes']['name'];
								if('input_image' == $cval['element']){
									$field_type = 'image';
								}

								if($field_name == 'names') {
									foreach($cval['fields'] as $field_key => $field_val){
										$field_type = $field_val['attributes']['type'];
										$field_name = $field_val['attributes']['name'];
										$field_lbl = $field_val['settings']['label'];

										$fields_data[$cval['attributes']['name']]['type'] = $field_type;
										$fields_data[$cval['attributes']['name']]['label'] = $cval['settings']['admin_field_label'];
										$fields_data[$cval['attributes']['name']][$field_key] = [
											'label' => $field_lbl
										];
									}
								}else{
									$fields_data[$field_name] = [
										'label' => $field_lbl,
										'type' => $field_type,
									];
								}
							}
						}
					}
				}else if('input_image' === $value['element']) {
					$field_type = 'image';
					$field_name = $value['attributes']['name'];

					if(!empty($value['settings']['label'])){
						$field_lbl = $value['settings']['label'];
					}else if(!empty($value['settings']['admin_field_label'])){
						$field_lbl = $value['settings']['admin_field_label'];
					}else{
						$field_lbl = 'Field_'.$value['attributes']['name'];
					}

					$fields_data[$field_name] = [
						'label' => $field_lbl,
						'type' => $field_type,
					];
				}else {
					$field_type = !empty($value['attributes']['type'])?$value['attributes']['type']:'text';
					$field_name = $value['attributes']['name'];

					if(!empty($value['settings']['label'])){
						$field_lbl = $value['settings']['label'];
					}else if(!empty($value['settings']['admin_field_label'])){
						$field_lbl = $value['settings']['admin_field_label'];
					}else{
						$field_lbl = 'lbl_'.$value['attributes']['name'];
					}

					$fields_data[$field_name] = [
						'label' => $field_lbl,
						'type' => $field_type,
					];
				}
			}
		}

		if(is_array($fields_data) && !empty($fields_data)){
			foreach($fields_data as $field_key => $field_value){

				$field_name = isset($field_value['name']) ? $field_value['name'] : '';
				$field_label = $field_value['label'];
				$field_type = $field_value['type'];
				if($field_type == 'image'){
					$return_type = 'image';
				}else{
					$return_type = 'text';
				}


				$merge_tags['Fluent Forms'][] = array(
					'id' 			=> 'fluentforms_' . $form_id . '_' . $field_key,
					'label' 		=> ucfirst(str_replace('_',' ',$field_label)),
					'preview_value' => '',
					'return_type'	=> $return_type,
					'value'			=> function ( $formData ) use ( $field_key ) {
						$value = isset($formData[$field_key]) ? $formData[$field_key] : '';
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
		$recipient_fields = array();
		$fields_data = array();
		$formApi = fluentFormApi('forms')->form($form_id);
		$form_fields = $formApi->fields();
		$excluded_field_types = array('custom_submit_button', 'section_break', 'custom_html','tabular_grid','repeater_field','terms_and_condition','action_hook','form_step','chained_select','save_progress_button', 'cpt_selection', 'shortcode');

		if(is_array($form_fields)){
			foreach($form_fields['fields'] as $key => $value){
				if(in_array($value['element'], $excluded_field_types)){
					continue;
				}

				if('container' === $value['element']){
					if(!empty($value['columns']) && is_array($value['columns'])){
						foreach($value['columns'] as $col_key => $col_value){
							foreach($col_value['fields'] as $ckey => $cval){

								if(in_array($cval['element'], $excluded_field_types)){
									continue;
								}

								$field_type = isset($cval['attributes']['type']) ? $cval['attributes']['type'] : '';
								if('tel' == $field_type){
									$field_lbl = !empty($cval['settings']['label'])?$cval['settings']['label']:'Field_'.$cval['attributes']['name'];
									$field_name = $cval['attributes']['name'];

									$fields_data[$field_name] = [
										'label' => $field_lbl,
										'type' => $field_type,
									];
								}
							}
						}
					}
				}else {
					$field_type = isset($value['attributes']['type']) ? $value['attributes']['type'] : '';
					if('tel' == $field_type){
						$field_name = $value['attributes']['name'];

						if(!empty($value['settings']['label'])){
							$field_lbl = $value['settings']['label'];
						}else if(!empty($value['settings']['admin_field_label'])){
							$field_lbl = $value['settings']['admin_field_label'];
						}else{
							$field_lbl = 'lbl_'.$value['attributes']['name'];
						}

						$fields_data[$field_name] = [
							'label' => $field_lbl,
							'type' => $field_type,
						];
					}
				}
			}
		}

		if(is_array($fields_data) && !empty($fields_data)){
			foreach($fields_data as $field_key => $field_value){
				if('tel' != $field_value['type']){
					continue;
				}

				$field_name = isset($field_value['name']) ? $field_value['name'] : '';
				$field_label = $field_value['label'];

				$recipient_fields['Fluent Forms'][] = array(
					'id' 			=> 'fluentforms_' . $form_id . '_' . $field_key,
					'label' 		=> ucfirst(str_replace('_',' ',$field_label)),
					'value'			=> function ( $formData ) use ( $field_key ) {
						$value = isset($formData[$field_key]) ? $formData[$field_key] : '';
						return html_entity_decode(sanitize_text_field($value));
					}
				);
			}
		}

		return $recipient_fields;
	}
}
