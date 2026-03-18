<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notifier_WSForm {

	/**
	 * Init
	 */
	public static function init() {
		add_filter( 'notifier_notification_triggers', array( __CLASS__, 'add_triggers'), 10 );
	}

	/**
	 * Add notification triggers
	 */
	public static function add_triggers( $existing_triggers ) {

		if ( ! class_exists( 'WS_Form_Form' ) ) {
			return $existing_triggers;
		}

		$ws_form = new WS_Form_Form();
		$forms = $ws_form->db_read_all( '', 'NOT status="trash"', 'label ASC', '', '', false, true );

		if ( empty( $forms ) || ! is_array( $forms ) ) {
			return $existing_triggers;
		}

		$triggers = array();
		foreach ( $forms as $form ) {
			$form_id = $form['id'];
			$trigger_id = 'wsform_' . $form_id;

			$triggers[] = array(
				'id'            => $trigger_id,
				'label'         => 'Form "' . $form['label'] . '" submitted',
				'description'   => 'Trigger notification when <b>' . $form['label'] . '</b> form is submitted.',
				'merge_tags'    => self::get_merge_tags( $form ),
				'recipient_fields' => self::get_recipient_fields( $form ),
				'action'        => array(
					'hook'     => 'wsf_submit_post_complete',
					'args_num' => 1,
					'callback' => function ( $submit ) use ( $trigger_id, $form_id ) {
						if ( ! is_object( $submit ) || empty( $submit->form_id ) ) {
							return;
						}

						if ( (int) $submit->form_id !== (int) $form_id ) {
							return;
						}

						$sanitized_data = array();

						if ( ! empty( $submit->meta ) && is_array( $submit->meta ) ) {
							foreach ( $submit->meta as $meta_key => $meta_data ) {
								if ( strpos( $meta_key, 'field_' ) === 0 ) {
									$value = isset( $meta_data['value'] ) ? $meta_data['value'] : '';
									if ( is_array( $value ) ) {
										$sanitized_data[ $meta_key ] = notifier_sanitize_array( $value );
									} else {
										$sanitized_data[ $meta_key ] = sanitize_text_field( $value );
									}
								}
							}
						}

						Notifier_Notification_Triggers::send_trigger_request( $trigger_id, $sanitized_data );
					}
				)
			);
		}

		$existing_triggers['WS Form'] = $triggers;
		return $existing_triggers;
	}

	/**
	 * Get form object with groups for field extraction.
	 *
	 * @param array $form Form row from db_read_all.
	 * @return object|null Form object with groups or null.
	 */
	private static function get_form_object( $form ) {

		if ( empty( $form['id'] ) || ! class_exists( 'WS_Form_Form' ) ) {
			return null;
		}

		$ws_form = new WS_Form_Form();
		$ws_form->id = (int) $form['id'];

		try {
			$form_object = $ws_form->db_read( true, true, false, false, true );
			return is_object( $form_object ) && ! empty( $form_object->groups ) ? $form_object : null;
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Get merge tags
	 *
	 * @param array $form Form row from db_read_all.
	 */
	public static function get_merge_tags( $form ) {

		$merge_tags = Notifier_Notification_Merge_Tags::get_merge_tags();
		$form_object = self::get_form_object( $form );

		if ( ! $form_object || ! method_exists( 'WS_Form_Common', 'get_fields_from_form' ) ) {
			return $merge_tags;
		}

		$fields = WS_Form_Common::get_fields_from_form( $form_object, true );

		if ( empty( $fields ) ) {
			return $merge_tags;
		}

		$form_id = (int) $form['id'];
		$excluded_types = array( 'html', 'section', 'captcha', 'page', 'header', 'spacer' );

		foreach ( $fields as $field ) {

			if ( empty( $field->id ) || in_array( $field->type, $excluded_types, true ) ) {
				continue;
			}

			$field_name = 'field_' . $field->id;
			$label      = isset( $field->label ) ? $field->label : $field_name;

			$merge_tags['WS Form'][] = array(
				'id'           => 'wsform_' . $form_id . '_' . $field_name,
				'label'       => $label,
				'preview_value'=> '',
				'return_type'  => 'text',
				'value'       => function ( $sanitized_data ) use ( $field_name ) {

					$value = isset( $sanitized_data[ $field_name ] ) ? $sanitized_data[ $field_name ] : '';

					if ( is_array( $value ) ) {
						$value = implode( ', ', $value );
					}

					return html_entity_decode( sanitize_text_field( $value ) );
				}
			);
		}

		return $merge_tags;
	}

	/**
	 * Get recipient fields
	 *
	 * @param array $form Form row from db_read_all.
	 */
	public static function get_recipient_fields( $form ) {

		$recipient_fields = array();
		$form_object = self::get_form_object( $form );

		if ( ! $form_object || ! method_exists( 'WS_Form_Common', 'get_fields_from_form' ) ) {
			return $recipient_fields;
		}

		$fields = WS_Form_Common::get_fields_from_form( $form_object, true );

		if ( empty( $fields ) ) {
			return $recipient_fields;
		}

		$form_id = (int) $form['id'];

		foreach ( $fields as $field ) {

			if ( empty( $field->id ) || $field->type !== 'tel' ) {
				continue;
			}

			$field_name = 'field_' . $field->id;
			$label      = isset( $field->label ) ? $field->label : $field_name;

			$recipient_fields['WS Form'][] = array(
				'id'    => 'wsform_' . $form_id . '_' . $field_name,
				'label' => $label,
				'value' => function ( $sanitized_data ) use ( $field_name ) {

					$value = isset( $sanitized_data[ $field_name ] ) ? $sanitized_data[ $field_name ] : '';

					return html_entity_decode( sanitize_text_field( $value ) );
				}
			);
		}

		return $recipient_fields;
	}

}
