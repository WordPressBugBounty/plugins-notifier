<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notifier_SureForms {

	/**
	 * Init
	 */
	public static function init() {
		add_filter( 'notifier_notification_triggers', array( __CLASS__, 'add_triggers' ), 10 );
	}

	/**
	 * Add notification triggers
	 */
	public static function add_triggers( $existing_triggers ) {

		if ( ! class_exists( 'SRFM\Plugin_Loader' ) || ! post_type_exists( 'sureforms_form' ) ) {
			return $existing_triggers;
		}

		$triggers = array();

		$form_ids = get_posts( array(
			'post_type'   => 'sureforms_form',
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields'      => 'ids',
		) );

		foreach ( $form_ids as $form_id ) {

			$trigger_id = 'sureforms_' . $form_id;
			$title      = get_the_title( $form_id );

			$triggers[] = array(
				'id'               => $trigger_id,
				'label'            => 'Form "' . $title . '" is submitted',
				'description'      => 'Trigger notification when <b>' . $title . '</b> form is submitted.',
				'merge_tags'       => self::get_merge_tags( $form_id ),
				'recipient_fields' => self::get_recipient_fields( $form_id ),
				'action'           => array(
					'hook'     => 'srfm_form_submit',
					'args_num' => 1,
					'callback' => function ( $response ) use ( $trigger_id, $form_id ) {

						if ( empty( $response['success'] ) || (int) $response['form_id'] !== (int) $form_id ) {
							return;
						}

						$payload = isset( $response['data'] ) ? $response['data'] : array();

						Notifier_Notification_Triggers::send_trigger_request( $trigger_id, $payload );
					},
				),
			);
		}

		$existing_triggers['SureForms'] = $triggers;

		return $existing_triggers;
	}

	/**
	 * Get merge tags
	 */
	public static function get_merge_tags( $form_id ) {

		$merge_tags = Notifier_Notification_Merge_Tags::get_merge_tags();
		$post       = get_post( $form_id );

		if ( ! $post ) {
			return $merge_tags;
		}

		$blocks = parse_blocks( $post->post_content );

		foreach ( $blocks as $block ) {

			if ( empty( $block['blockName'] ) || strpos( $block['blockName'], 'srfm/' ) !== 0 ) {
				continue;
			}

			if ( empty( $block['attrs']['slug'] ) ) {
				continue;
			}

			$slug     = $block['attrs']['slug'];
			$block_id = ! empty( $block['attrs']['block_id'] ) ? $block['attrs']['block_id'] : $slug;
			$label    = ! empty( $block['attrs']['label'] ) ? $block['attrs']['label'] : $slug;

			$merge_tags['SureForms'][] = array(
				'id'            => 'sureforms_' . $form_id . '_' . sanitize_key( $block_id . '_' . $slug ),
				'label'         => $label,
				'preview_value' => '',
				'return_type'   => 'text',
				'value'         => function ( $payload ) use ( $slug ) {
					$value = isset( $payload[ $slug ] ) ? $payload[ $slug ] : '';
					if ( is_array( $value ) ) {
						$value = implode( ', ', $value );
					}
					return sanitize_text_field( $value );
				},
			);
		}

		return $merge_tags;
	}

	/**
	 * Get recipient fields
	 */
	public static function get_recipient_fields( $form_id ) {

		$recipient_fields = array();
		$post             = get_post( $form_id );

		if ( ! $post ) {
			return $recipient_fields;
		}

		$blocks = parse_blocks( $post->post_content );

		foreach ( $blocks as $block ) {

			if ( empty( $block['blockName'] ) || $block['blockName'] !== 'srfm/phone' ) {
				continue;
			}

			if ( empty( $block['attrs']['slug'] ) ) {
				continue;
			}

			$slug     = $block['attrs']['slug'];
			$block_id = ! empty( $block['attrs']['block_id'] ) ? $block['attrs']['block_id'] : $slug;
			$label    = ! empty( $block['attrs']['label'] ) ? $block['attrs']['label'] : $slug;

			$recipient_fields['SureForms'][] = array(
				'id'    => 'sureforms_' . $form_id . '_' . sanitize_key( $block_id . '_' . $slug ),
				'label' => $label,
				'value' => function ( $payload ) use ( $slug ) {
					$value = isset( $payload[ $slug ] ) ? $payload[ $slug ] : '';
					return sanitize_text_field( $value );
				},
			);
		}

		return $recipient_fields;
	}

}
