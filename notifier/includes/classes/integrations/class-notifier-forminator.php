<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notifier_Forminator {

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

		if ( ! class_exists( 'Forminator' ) || ! post_type_exists( 'forminator_forms' ) ) {
			return $existing_triggers;
		}

		$triggers = array();

		$form_ids = get_posts(array(
			'post_type'   => 'forminator_forms',
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields'      => 'ids'
		));

		foreach ( $form_ids as $form_id ) {

			$trigger_id = 'forminator_' . $form_id;
			$title      = get_the_title( $form_id );

			$triggers[] = array(
				'id'          => $trigger_id,
				'label'       => 'Form "' . $title . '" is submitted',
				'description' => 'Trigger notification when <b>' . $title . '</b> form is submitted.',
				'merge_tags'  => self::get_merge_tags( $form_id ),
				'recipient_fields' => self::get_recipient_fields( $form_id ),
				'action'      => array(
					'hook'     => 'forminator_form_after_save_entry',
					'args_num' => 2,
					'callback' => function ( $form_id_hook, $response ) use ( $trigger_id, $form_id ) {
						if ( (int) $form_id_hook !== (int) $form_id ) {
							return;
						}

						$sanitized_data = array();

						if ( isset( $_POST ) ) {
							foreach ( $_POST as $key => $value ) {

								if ( is_array( $value ) ) {
									$sanitized_data[ $key ] = notifier_sanitize_array( $value );
								} else {
									$sanitized_data[ $key ] = sanitize_text_field( $value );
								}
							}
						}

						Notifier_Notification_Triggers::send_trigger_request(
							$trigger_id,
							$sanitized_data
						);
					}
				)
			);
		}

		$existing_triggers['Forminator Forms'] = $triggers;

		return $existing_triggers;
	}

	/**
	 * Get merge tags
	 */
	public static function get_merge_tags( $form_id ) {

		$merge_tags = Notifier_Notification_Merge_Tags::get_merge_tags();
		$form_settings = get_post_meta( $form_id, 'forminator_form_meta', true );

		if ( ! empty( $form_settings['fields'] ) ) {

			foreach ( $form_settings['fields'] as $field ) {

				if ( empty( $field['element_id'] ) ) {
					continue;
				}

				$field_name = $field['element_id'];

				$merge_tags['Forminator Forms'][] = array(
					'id'           => 'forminator_' . $form_id . '_' . $field_name,
					'label'        => $field_name,
					'preview_value'=> '',
					'return_type'  => 'text',
					'value'        => function ( $sanitized_data ) use ( $field_name ) {

						$value = isset( $sanitized_data[$field_name] ) ? $sanitized_data[$field_name] : '';

						if ( is_array( $value ) ) {
							$value = implode(', ', $value);
						}

						return html_entity_decode( sanitize_text_field( $value ) );
					}
				);
			}
		}

		return $merge_tags;
	}

	/**
	 * Get recipient fields
	 */
	public static function get_recipient_fields( $form_id ) {

		$recipient_fields = array();

		$form_settings = get_post_meta( $form_id, 'forminator_form_meta', true );

		if ( ! empty( $form_settings['fields'] ) ) {

			foreach ( $form_settings['fields'] as $field ) {

				if ( $field['type'] !== 'phone' ) {
					continue;
				}

				$field_name = $field['element_id'];

				$recipient_fields['Forminator Forms'][] = array(
					'id'    => 'forminator_' . $form_id . '_' . $field_name,
					'label' => $field_name,
					'value' => function ( $sanitized_data ) use ( $field_name ) {

						$value = isset( $sanitized_data[$field_name] ) ? $sanitized_data[$field_name] : '';

						return html_entity_decode( sanitize_text_field( $value ) );
					}
				);
			}
		}

		return $recipient_fields;
	}

}