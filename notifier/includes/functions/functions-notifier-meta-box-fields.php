<?php
/**
 * Notifier Meta Box Functions
 *
 * @package     Notifier
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Output a text input box.
 *
 * @param array $field
 */
function notifier_wp_text_input( $field ) {
	global $post;

	$field = apply_filters ('notifier_wp_text_input_args', $field, $post);

	$field = wp_parse_args(
		$field, array(
			'label'             => '',
			'placeholder'       => '',
			'class'             => 'form-control',
			'style'             => '',
			'wrapper_class'     => '',
			'value'             => '',
			'name'              => $field['id'],
			'description'       => '',
			'type'              => 'text',
			'limit'             => 0,
			'conditional_logic' => '',
			'show_wrapper'      => true,
			'custom_attributes' => array(),
			'data_type'         => '',
			'required'          => false,
			'invalid_message'   => 'This is a required field.',
            'conditional_operator'  => 'OR',
            'datalist'              => array()
		)
	);

	$field['conditional_logic'] = ( '' != $field['conditional_logic'] ) ? json_encode($field['conditional_logic']) : '';

	switch ( $field['data_type'] ) {
		case 'url':
			$field['class'] .= ' notifier_input_url';
			$field['value']  = esc_url( $field['value'] );
			break;

		default:
			break;
	}

	$show_limit_text = '';
	if ( 0 != $field['limit'] ) {
		$show_limit_text = '<span class="limit-text"><span class="limit-used">0</span> / <span>' . $field['limit'] . '</span></span>';
		$field['custom_attributes']['data-limit'] = $field['limit'];
		$field['class'] = $field['class'] . ' force-text-limit';
	}

	// Custom attribute handling
	$custom_attributes = array();

	if ( isset($field['custom_attributes']['disabled']) && 'disabled' == $field['custom_attributes']['disabled'] ) {
		$field['custom_attributes']['data-disabled'] = 'yes';
	}

	if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {
		foreach ( $field['custom_attributes'] as $attribute => $value ) {
			$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
		}
	}

	if ( $field['required'] ) {
		$custom_attributes[] = 'required="required"';
	}

	$custom_attributes_string = implode( ' ', $custom_attributes );

	if ( $field['show_wrapper'] ) {
		do_action('notifier_before_meta_field_wrapper', $field, $post);
		echo '<p class="form-field mb-3 ' . esc_attr( $field['id'] ) . '_field ' . esc_attr( $field['wrapper_class'] ) . '" data-conditions="' . esc_js( $field['conditional_logic'] ) . '" data-conditions-operator="' . esc_js( $field['conditional_operator'] ) . '">';
	}

	if ( '' != $field['label'] ) {
		echo '<label for="' . esc_attr( $field['id'] ) . '" class="form-label">' . wp_kses_post( $field['label'] . $show_limit_text ) . '</label>';
	}

    $datalist_attr = '';
    if(!empty($field['datalist'])){
        $datalist_attr = ' list="' . esc_attr( $field['id'] ) . '_datalist" data-min-length="0" data-no-results-text=""';
        $field['class'] = $field['class'] . ' datalist-field';
    }

	do_action('notifier_before_meta_field', $field, $post);

	echo '<input type="' . esc_attr( $field['type'] ) . '" class="' . esc_attr( $field['class'] ) . '" style="' . esc_attr( $field['style'] ) . '" name="' . esc_attr( $field['name'] ) . '" id="' . esc_attr( $field['id'] ) . '" value="' . esc_attr( $field['value'] ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" ' . $custom_attributes_string . $datalist_attr . ' /> ';

    if(!empty($field['datalist'])){
        echo '<datalist id="' . esc_attr( $field['id'] ) . '_datalist">';
        foreach($field['datalist'] as $item){
            echo '<option value="'.$item.'">';
        }
        echo '</datalist>';
    }

	if ( $field['required'] ) {
		echo '<span class="invalid-feedback">' . wp_kses_post($field['invalid_message']) . '</span>';
	}

	do_action('notifier_after_meta_field', $field, $post);

	if ( '' != $field['description'] ) {
		echo '<span class="description">' . wp_kses_post( $field['description'] ) . '</span>';
	}

	do_action('notifier_after_meta_field_description', $field, $post);

	if ( $field['show_wrapper'] ) {
		echo '</p>';
		do_action('notifier_after_meta_field_wrapper', $field, $post);
	}
}

/**
 * Output a hidden input box.
 *
 * @param array $field
 */
function notifier_wp_hidden_input( $field ) {
	global $post;

	$field = apply_filters ('notifier_wp_hidden_input', $field, $post);

	$field = wp_parse_args(
		$field, array(
			'class'             => '',
			'value'             => ''
		)
	);

	do_action('notifier_before_meta_field', $field, $post);

	echo '<input type="hidden" class="' . esc_attr( $field['class'] ) . '" name="' . esc_attr( $field['id'] ) . '" id="' . esc_attr( $field['id'] ) . '" value="' . esc_attr( $field['value'] ) . '" /> ';

	do_action('notifier_after_meta_field', $field, $post);
}

/**
 * Output a textarea input box.
 *
 * @param array $field
 */
function notifier_wp_textarea_input( $field ) {
	global $post;

	$field = apply_filters ('notifier_wp_textarea_input', $field, $post);

	$field = wp_parse_args(
		$field, array(
			'label'             => '',
			'placeholder'       => '',
			'class'             => 'form-control',
			'style'             => '',
			'wrapper_class'     => '',
			'value'             => '',
			'name'              => $field['id'],
			'description'       => '',
			'limit'             => 0,
			'conditional_logic' => '',
			'show_wrapper'      => true,
			'custom_attributes' => array(),
			'rows'              => 2,
			'cols'              => 20,
			'required'          => false,
            'conditional_operator'  => 'OR'
		)
	);

	$field['conditional_logic'] = ( '' != $field['conditional_logic'] ) ? json_encode($field['conditional_logic']) : '';

	$show_limit_text = '';
	if ( $field['limit'] != 0 ) {
		$show_limit_text = '<span class="limit-text"><span class="limit-used">0</span> / <span>' . $field['limit'] . '</span></span>';
		$field['custom_attributes']['data-limit'] = $field['limit'];
		$field['class'] = $field['class'] . ' force-text-limit';
	}

	// Custom attribute handling
	$custom_attributes = array();

	if ( isset($field['custom_attributes']['disabled']) && 'disabled' == $field['custom_attributes']['disabled'] ) {
		$field['custom_attributes']['data-disabled'] = 'yes';
	}

	if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {
		foreach ( $field['custom_attributes'] as $attribute => $value ) {
			$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
		}
	}

	if ( $field['required'] ) {
		$custom_attributes[] = 'required="required"';
	}

	$custom_attributes_string = implode( ' ', $custom_attributes );

	if ( $field['show_wrapper'] ) {
		do_action('notifier_before_meta_field_wrapper', $field, $post);
		echo '<p class="form-field mb-3 ' . esc_attr( $field['id'] ) . '_field ' . esc_attr( $field['wrapper_class'] ) . '" data-conditions="' . esc_js( $field['conditional_logic'] ) . '" data-conditions-operator="' . esc_js( $field['conditional_operator'] ) . '">';
	}

	if ( '' != $field['label'] ) {
		echo '<label for="' . esc_attr( $field['id'] ) . '" class="form-label">' . wp_kses_post( $field['label'] . $show_limit_text ) . '</label>';
	}

	do_action('notifier_before_meta_field', $field, $post);

	echo '<textarea class="' . esc_attr( $field['class'] ) . '" style="' . esc_attr( $field['style'] ) . '"  name="' . esc_attr( $field['name'] ) . '" id="' . esc_attr( $field['id'] ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" rows="' . esc_attr( $field['rows'] ) . '" cols="' . esc_attr( $field['cols'] ) . '" ' . $custom_attributes_string . ' >' . esc_textarea( $field['value'] ) . '</textarea> ';

	if ( $field['required'] ) {
		echo '<span class="invalid-feedback">This is a required field.</span>';
	}

	do_action('notifier_after_meta_field', $field, $post);

	if ( '' != $field['description'] ) {
		echo '<span class="description">' . wp_kses_post( $field['description'] ) . '</span>';
	}

	do_action('notifier_after_meta_field_description', $field, $post);

	if ( $field['show_wrapper'] ) {
		echo '</p>';
		do_action('notifier_after_meta_field_wrapper', $field, $post);
	}
}

/**
 * Output a checkbox input box.
 *
 * @param array $field
 */
function notifier_wp_checkbox( $field ) {
	global $post;

	$field = apply_filters ('notifier_wp_checkbox', $field, $post);

	$field = wp_parse_args(
		$field, array(
			'label'             => '',
			'class'             => 'form-control',
			'style'             => '',
			'wrapper_class'     => '',
			'value'             => '',
			'cbvalue'           => 'yes',
			'name'              => $field['id'],
			'description'       => '',
			'conditional_logic' => '',
			'show_wrapper'      => true,
			'custom_attributes' => array(),
			'required'          => false
		)
	);

	$field['conditional_logic'] = ( '' != $field['conditional_logic'] ) ? json_encode($field['conditional_logic']) : '';

	// Custom attribute handling
	$custom_attributes = array();

	if ( isset($field['custom_attributes']['disabled']) && 'disabled' == $field['custom_attributes']['disabled'] ) {
		$field['custom_attributes']['data-disabled'] = 'yes';
	}

	if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {
		foreach ( $field['custom_attributes'] as $attribute => $value ) {
			$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
		}
	}

	if ( $field['required'] ) {
		$custom_attributes[] = 'required="required"';
	}

	$custom_attributes_string = implode( ' ', $custom_attributes );

	if ( $field['show_wrapper'] ) {
		do_action('notifier_before_meta_field_wrapper', $field, $post);
		echo '<p class="form-field mb-3 ' . esc_attr( $field['id'] ) . '_field ' . esc_attr( $field['wrapper_class'] ) . '" data-conditions="' . esc_js( $field['conditional_logic'] ) . '">';
	}

	if ( '' != $field['label'] ) {
		echo '<label for="' . esc_attr( $field['id'] ) . '" class="form-label">' . wp_kses_post( $field['label'] ) . '</label>';
	}

	do_action('notifier_before_meta_field', $field, $post);

	echo '<input type="checkbox" class="' . esc_attr( $field['class'] ) . '" style="' . esc_attr( $field['style'] ) . '" name="' . esc_attr( $field['name'] ) . '" id="' . esc_attr( $field['id'] ) . '" value="' . esc_attr( $field['cbvalue'] ) . '" ' . checked( $field['value'], $field['cbvalue'], false ) . '  ' . $custom_attributes_string . '/> ';

	if ( $field['required'] ) {
		echo '<span class="invalid-feedback">This is a required field.</span>';
	}

	do_action('notifier_after_meta_field', $field, $post);

	if ( '' != $field['description'] ) {
		echo '<span class="description">' . wp_kses_post( $field['description'] ) . '</span>';
	}

	do_action('notifier_after_meta_field_description', $field, $post);

	if ( $field['show_wrapper'] ) {
		echo '</p>';
		do_action('notifier_after_meta_field_wrapper', $field, $post);
	}
}

/**
 * Output multiple checkboxes
 *
 * @param array $field
 */
function notifier_wp_multi_checkboxes( $field ) {
    global $post;

    $field = apply_filters ('notifier_wp_multi_checkboxes', $field, $post);

    $field = wp_parse_args(
        $field, array(
            'label'             => '',
            'class'             => 'form-check-input',
            'style'             => '',
            'wrapper_class'     => '',
            'value'             => '',
            'name'              => $field['id'],
            'description'       => '',
            'conditional_logic' => '',
            'show_wrapper'      => true,
            'custom_attributes' => array(),
            'required'          => false
        )
    );

    $field['conditional_logic'] = ( '' != $field['conditional_logic'] ) ? json_encode($field['conditional_logic']) : '';

    // Custom attribute handling
    $custom_attributes = array();

    if ( isset($field['custom_attributes']['disabled']) && 'disabled' == $field['custom_attributes']['disabled'] ) {
        $field['custom_attributes']['data-disabled'] = 'yes';
    }

    if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {
        foreach ( $field['custom_attributes'] as $attribute => $value ) {
            $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
        }
    }

    $custom_attributes_string = implode( ' ', $custom_attributes );

    if ( $field['show_wrapper'] ) {
        do_action('notifier_before_meta_field_wrapper', $field, $post);
        echo '<fieldset class="form-field mb-3 mt-1 ' . esc_attr( $field['id'] ) . '_field ' . esc_attr( $field['wrapper_class'] ) . '" data-conditions="' . esc_js($field['conditional_logic']) . '">';
    }

    if ( '' != $field['label'] ) {
        echo '<legend class="form-label">' . wp_kses_post( $field['label'] ) . '</legend>';
    }

    do_action('notifier_before_meta_field', $field, $post);

    echo '<div class="multi-checkbox-wrapper">';

    foreach ( $field['options'] as $key => $value ) {
        if(in_array($key, (array) $field['value'])){
            $checked = true;
            $checked_class = 'form-check-checked';
        }
        else{
            $checked = false;
            $checked_class = '';
        }

        echo '<div class="form-check '.$checked_class.'"><label class="form-check-label"><input
                name="' . esc_attr( $field['name'] ) . '"
                value="' . esc_attr( $key ) . '"
                type="checkbox"
                class="' . esc_attr( $field['class'] ) . '"
                style="' . esc_attr( $field['style'] ) . '"
                ' . checked( $checked, true, false ) . '
                ' . $custom_attributes_string . '
                /> ' . esc_html( $value ) . '</label>
        </div>';
    }

    echo '</div>';

    if ( $field['required'] ) {
        echo '<span class="invalid-feedback">Please select at least one '.wp_kses_post( strtolower($field['label']) ).'.</span>';
    }

    do_action('notifier_after_meta_field', $field, $post);

    if ( '' != $field['description'] ) {
        echo '<span class="description">' . wp_kses_post( $field['description'] ) . '</span>';
    }

    do_action('notifier_after_meta_field_description', $field, $post);

    if ( $field['show_wrapper'] ) {
        echo '</fieldset>';
        do_action('notifier_after_meta_field_wrapper', $field, $post);
    }

}

/**
 * Output a select input box.
 *
 * @param array $field Data about the field to render.
 */
function notifier_wp_select( $field ) {
	global $post;

	$field = apply_filters ('notifier_wp_select', $field, $post);

	$field = wp_parse_args(
		$field, array(
			'label'             => '',
			'class'             => 'form-select',
			'style'             => '',
			'wrapper_class'     => '',
			'value'             => '',
			'name'              => $field['id'],
			'description'       => '',
			'conditional_logic' => '',
			'show_wrapper'      => true,
			'custom_attributes' => array(),
			'required'          => false
		)
	);

	$field['conditional_logic'] = ( '' != $field['conditional_logic'] ) ? json_encode($field['conditional_logic']) : '';

	// Custom attribute handling
	$custom_attributes = array();

	if ( isset($field['custom_attributes']['disabled']) && 'disabled' == $field['custom_attributes']['disabled'] ) {
		$field['custom_attributes']['data-disabled'] = 'yes';
	}

	if ( $field['required'] ) {
		$custom_attributes[] = 'required="required"';
	}

	if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {
		foreach ( $field['custom_attributes'] as $attribute => $value ) {
			$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
		}
	}

	$custom_attributes_string = implode( ' ', $custom_attributes );

	$description = ! empty( $field['description'] ) ? $field['description'] : '';

	if ( $field['show_wrapper'] ) {
		do_action('notifier_before_meta_field_wrapper', $field, $post);
		echo '<p class="form-field mb-3 ' . esc_attr( $field['id'] ) . '_field ' . esc_attr( $field['wrapper_class'] ) . '" data-conditions="' . esc_js( $field['conditional_logic'] ) . '">';
	}

	if ( '' != $field['label'] ) {
		echo '<label for="' . esc_attr( $field['id'] ) . '" class="form-label">' . wp_kses_post( $field['label'] ) . '</label>';
	}

	do_action('notifier_before_meta_field', $field, $post);

	echo '<select class="' . esc_attr( $field['class'] ) . '" style="' . esc_attr( $field['style'] ) . '" name="' . esc_attr( $field['name'] ) . '" id="' . esc_attr( $field['id'] ) . '"' . $custom_attributes_string . '/> ';

	foreach ( $field['options'] as $key => $value ) {
		if ( is_array($value) ) {
			$opt_group_options = $value;
			echo '<optgroup label="' . esc_attr($key) . '">';
			foreach ( $opt_group_options as $opt_key => $opt_val ) {
				if(is_array($field['value'])){
					$selected = in_array($opt_key, $field['value']) ? 'selected' : '';
				}
				else{
					$selected = selected( $opt_key, $field['value'], false);
				}
				echo '<option value="' . esc_attr( $opt_key ) . '"' . $selected . '>' . esc_html( $opt_val ) . '</option>';
			}
			echo '</optgroup>';
		} else {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $key, $field['value'], false) . '>' . esc_html( $value ) . '</option>';
		}
	}

	echo '</select>';

	if ( $field['required'] ) {
		echo '<span class="invalid-feedback">This is a required field.</span>';
	}

	do_action('notifier_after_meta_field', $field, $post);

	if ( '' != $field['description'] ) {
		echo '<span class="description">' . wp_kses_post( $field['description'] ) . '</span>';
	}

	do_action('notifier_after_meta_field_description', $field, $post);

	if ( $field['show_wrapper'] ) {
		echo '</p>';
		do_action('notifier_after_meta_field_wrapper', $field, $post);
	}
}

/**
 * Output a radio input box.
 *
 * @param array $field
 */
function notifier_wp_radio( $field ) {
	global $post;

	$field = apply_filters ('notifier_wp_radio', $field, $post);

	$field = wp_parse_args(
		$field, array(
			'label'             => '',
			'class'             => 'form-check-input',
			'style'             => '',
			'wrapper_class'     => '',
			'value'             => '',
			'name'              => $field['id'],
			'description'       => '',
			'conditional_logic' => '',
			'show_wrapper'      => true,
			'custom_attributes' => array()
		)
	);

	$field['conditional_logic'] = ( '' != $field['conditional_logic'] ) ? json_encode($field['conditional_logic']) : '';

	// Custom attribute handling
	$custom_attributes = array();

	if ( isset($field['custom_attributes']['disabled']) && 'disabled' == $field['custom_attributes']['disabled'] ) {
		$field['custom_attributes']['data-disabled'] = 'yes';
	}

	if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {
		foreach ( $field['custom_attributes'] as $attribute => $value ) {
			$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
		}
	}

	$custom_attributes_string = implode( ' ', $custom_attributes );

	if ( $field['show_wrapper'] ) {
		do_action('notifier_before_meta_field_wrapper', $field, $post);
		echo '<fieldset class="form-field mb-3 ' . esc_attr( $field['id'] ) . '_field ' . esc_attr( $field['wrapper_class'] ) . '" data-conditions="' . esc_js($field['conditional_logic']) . '">';
	}

	if ( '' != $field['label'] ) {
		echo '<legend class="form-label">' . wp_kses_post( $field['label'] ) . '</legend>';
	}

	do_action('notifier_before_meta_field', $field, $post);

	foreach ( $field['options'] as $key => $value ) {
		echo '<div class="form-check form-check-inline"><label class="form-check-label"><input
				name="' . esc_attr( $field['name'] ) . '"
				value="' . esc_attr( $key ) . '"
				type="radio"
				class="' . esc_attr( $field['class'] ) . '"
				style="' . esc_attr( $field['style'] ) . '"
				' . checked( esc_attr( $field['value'] ), esc_attr( $key ), false ) . '
				' . $custom_attributes_string . '
				/> ' . esc_html( $value ) . '</label>
		</div>';
	}

	do_action('notifier_after_meta_field', $field, $post);

	if ( '' != $field['description'] ) {
		echo '<span class="description">' . wp_kses_post( $field['description'] ) . '</span>';
	}

	do_action('notifier_after_meta_field_description', $field, $post);

	if ( $field['show_wrapper'] ) {
		echo '</fieldset>';
		do_action('notifier_after_meta_field_wrapper', $field, $post);
	}

}

/**
 * Output a file upload input box.
 *
 * @param array $field
 */
function notifier_wp_file_input( $field ) {
	global $post;

	$field = apply_filters ('notifier_wp_text_input_args', $field, $post);

	$field = wp_parse_args(
		$field, array(
			'label'             => '',
			'placeholder'       => '',
			'class'             => '',
			'wrapper_class'     => '',
			'value'             => '',
			'name'              => $field['id'],
			'description'       => '',
			'conditional_logic' => '',
			'show_wrapper'      => true,
			'custom_attributes' => array(),
			'file_types'        => array(),
			'required'          => false
		)
	);

	$field['conditional_logic'] = ( '' != $field['conditional_logic'] ) ? json_encode($field['conditional_logic']) : '';

	$image_thumb = '';
	$video_url = '';
	$show_image = 'd-none';
	$show_video = 'd-none';
	$show_wrapper = 'd-none';

	$attachment_id = isset($field['value']) ? $field['value'] : '';

	$attachment_data = wp_get_attachment_metadata($attachment_id);

	if ( '' != $attachment_id ) {
		$file_types = $field['file_types'];
		$media_url = isset($attachment_data['url']) ? $attachment_data['url'] : '';
		if ( in_array('video/mp4', $file_types) ) {
			$video_url = $media_url;
			$show_image = 'd-none';
			$show_video = '';
			$field['custom_attributes']['data-url'] = $video_url;
		} else {
			$image_thumb = isset($attachment_data['thumb_url']) ? $attachment_data['thumb_url'] : $media_url;
			$file_mime_type = get_post_mime_type($attachment_id);
			$file_mime_type = explode('/', $file_mime_type);
			$show_image = '';
			$show_video = 'd-none';
			$field['custom_attributes']['data-url'] = $image_thumb;
		}
	}

	if ( $show_image == '' || $show_video == '' ) {
		$show_wrapper = '';
	}

	$field['custom_attributes']['accept'] = implode( ',', $field['file_types'] );

	$is_disabled = false;
	if ( isset($field['custom_attributes']['disabled']) && $field['custom_attributes']['disabled'] == 'disabled' ) {
		$is_disabled = true;
	}

	// Custom attribute handling
	$custom_attributes = array();

	if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {
		foreach ( $field['custom_attributes'] as $attribute => $v ) {
			$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $v ) . '"';
		}
	}

	$required_field = '';
	if ( $field['required'] ) {
		$required_field = 'required="required"';
	}

	$custom_attributes_string = implode( ' ', $custom_attributes );

	if ( $field['show_wrapper'] ) {
		do_action('notifier_before_meta_field_wrapper', $field, $post);
		echo '<p class="form-field mb-3 ' . esc_attr( $field['id'] ) . '_field ' . esc_attr( $field['wrapper_class'] ) . '" data-conditions="' . esc_js( $field['conditional_logic'] ) . '">';
	}

	if ( '' != $field['label'] ) {
		echo '<label for="' . esc_attr( $field['id'] ) . '" class="form-label">' . wp_kses_post( $field['label'] ) . '</label>';
	}

	do_action('notifier_before_meta_field', $field, $post);

	echo '<span class="wa-notifier-media-preview ' . $show_wrapper . '">';
	echo '<img id="' . esc_attr( $field['id'] ) . '_preview_image" class="wa-notifier-media-preview-item ' . esc_attr($show_image) . '" src="' . esc_url($image_thumb) . '" />';
	echo '<video id="' . esc_attr( $field['id'] ) . '_preview_video" class="wa-notifier-media-preview-item ' . esc_attr($show_video) . '"  width="300" height="169" controls muted><source src="' . esc_url($video_url) . '"  type="video/mp4"></video><br/>';
	echo '</span>';

	echo '<input id="' . esc_attr( $field['id'] ) . '_uploader" class="wa-notifier-media-upload" type="file" ' . $custom_attributes_string . '>';

	echo '<input id="' . esc_attr( $field['id'] ) . '" value="' . esc_attr( $attachment_id ) . '" name="' . esc_attr( $field['name'] ) . '" class="wa-notifier-media" type="hidden" ' . $required_field . '>';

	if ( ! $is_disabled ) {
		echo '<button class="btn btn-primary btn-sm wa-notifier-media-trigger-upload me-2 ' . esc_attr( $field['class'] ) . '">Choose File</button>';
		echo '<button class="btn btn-secondary btn-sm wa-notifier-media-delete">Remove</button>';
	}

	echo '<span class="wa-notifier-media-upload-status d-none wa-notifier-media-uploading"><i class="bx bx-loader bx-spin"></i> Uploading...</span>';
	echo '<span class="wa-notifier-media-upload-status d-none wa-notifier-media-upload-done text-success"><i class="bx bx-check"></i> Upload done!</span>';
	echo '<span class="wa-notifier-media-upload-status d-none wa-notifier-media-upload-error text-danger"><i class="bx bxs-error"></i> There was an error during upload. Please try again later.</span>';

	if ( $field['required'] ) {
		echo '<span class="invalid-feedback">This is a required field.</span>';
	}

	do_action('notifier_after_meta_field', $field, $post);

	if ( '' != $field['description'] ) {
		echo '<span class="clearfix"></span><span class="description">' . wp_kses_post( $field['description'] ) . '</span>';
	}

	do_action('notifier_after_meta_field_description', $field, $post);

	if ( $field['show_wrapper'] ) {
		echo '</p>';
		do_action('notifier_after_meta_field_wrapper', $field, $post);
	}
}

/**
 * Notifier repeater field
 *
 * @param array $fields_data
 */
function notifier_wp_repeater($fields_data) {
    $fields_data = wp_parse_args(
        $fields_data, array(
            'label'                 => '',
            'name'                  => $fields_data['id'],
            'wrapper_class'         => '',
            'conditional_logic'     => '',
            'conditional_operator'  => 'OR'
        )
    );

    echo '<div class="form-field mb-3' . esc_attr( $fields_data['id'] ) . '_field ' . esc_attr( $fields_data['wrapper_class'] ) . '" data-conditions="' . esc_js( $fields_data['conditional_logic'] ) . '">';

    echo '<label class="form-label">' . $fields_data['label'] . '</label>';

    echo '<table class="fields-repeater">';

    // Table header
    echo '<thead>';
    echo '<tr>';
    foreach($fields_data['fields'] as $field){
        echo '<th>' . $field['label'] . '</th>';
    }
    echo '<th></th>';
    echo '</tr>';
    echo '</thead>';

    echo '<tbody>';
    echo '<tr class="d-none">';
    foreach($fields_data['fields'] as $field){
        $field['custom_attributes'] = array('disabled' => 'disabled');
        $field['label'] = '';
        $field['name'] = $field['id'] . '[]';
        $field['id'] = $field['id'] . '_dummy';
        echo '<td>';
        switch($field['type']) {
            case 'text':
                notifier_wp_text_input($field);
                break;
        }
        echo '</td>';
    }
    echo '<td><a href="#" class="delete-repeater-field"><i class="bx bx-trash text-secondary"></i></a></td>';
    echo '</tr>';

    if(!empty($fields_data['values'])){
        $x = 0;
        foreach($fields_data['values'] as $values){
            echo '<tr>';
            foreach($fields_data['fields'] as $key => $field){
                $field['label'] = '';
                $field['value'] = $values[$key];
                $field['name'] = $field['id'] . '[]';
                $field['id'] = $field['id'] . '_' . $x;
                echo '<td>';
                switch($field['type']) {
                    case 'text':
                        notifier_wp_text_input($field);
                        break;
                }
                echo '</td>';
            }
            echo '<td><a href="#" class="delete-repeater-field"><i class="bx bx-trash text-secondary"></i></a></td>';
            echo '</tr>';
            $x++;
        }
    }
    echo '</tbody>';
    echo '</table>';

    echo '<p class="description d-flex align-items-center mb-3 mt-1"><span>'.$fields_data['description'].'</span>
        <a href="" class="add-repeater-item ms-auto"><i class="bx bx-plus"></i> '.$fields_data['button_name'].'</a>
    </p>';

    echo '</div>';
}
