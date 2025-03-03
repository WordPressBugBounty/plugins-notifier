<?php
/**
 * Notifier Helper Functions
 *
 * @package    Wa_Notifier
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Create new post type
 *
 * @return null
 */
function notifier_register_post_type($cpt_slug, $cpt_name, $cpt_name_plural, $args = []) {
    $labels = array (
        'name'                => $cpt_name_plural,
        'singular_name'       => $cpt_name,
        'menu_name'           => $cpt_name_plural,
        'parent_item_colon'   => "Parent {$cpt_name}",
        'all_items'           => "All {$cpt_name_plural}",
        'view_item'           => "View {$cpt_name}",
        'add_new_item'        => "Add New {$cpt_name}",
        'add_new'             => 'Add New',
        'edit_item'           => "Edit {$cpt_name}",
        'update_item'         => "Update {$cpt_name}",
        'search_items'        => "Search {$cpt_name}",
        'not_found'           => "No {$cpt_name_plural} found",
        'not_found_in_trash'  => "No {$cpt_name_plural} found in Trash"
    );
    $args = wp_parse_args($args, array (
        'label'               => $cpt_slug,
        'description'         => "{$cpt_name_plural} Post Type",
        'labels'              => $labels,
        'supports'            => array( 'title'),
        'hierarchical'        => false,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => false,
        'show_in_nav_menus'   => false,
        'show_in_admin_bar'   => false,
        'menu_position'       => 5,
        'can_export'          => true,
        'has_archive'         => false,
        'capability_type'     => 'post',
        'show_in_rest'        => true,
    ));
    register_post_type($cpt_slug, $args);
}

/**
 * Create new taxonomy for a post type
 *
 * @return null
 */
function notifier_register_taxonomy($taxonomy_slug, $taxonomy_name, $taxonomy_name_plural, $cpt_slug, $args = array()) {
    $labels = array (
        'name' => $taxonomy_name_plural,
        'singular_name' => $taxonomy_name,
        'search_items' =>  "Search {$taxonomy_name_plural}",
        'all_items' => "All {$taxonomy_name_plural}",
        'parent_item' => "Parent {$taxonomy_name}",
        'parent_item_colon' => "Parent {$taxonomy_name}:",
        'edit_item' => "Edit {$taxonomy_name}",
        'update_item' => "Update {$taxonomy_name}",
        'add_new_item' => "Add New {$taxonomy_name}",
        'new_item_name' => "New {$taxonomy_name} Name",
        'menu_name' => $taxonomy_name_plural,
    );

    $args =  wp_parse_args( $args, array (
        'hierarchical' => false,
        'labels' => $labels,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'show_in_rest' => true,
        'rewrite' => array( 'slug' => $taxonomy_slug )
    ));

    register_taxonomy($taxonomy_slug, [ $cpt_slug ], $args);
}

/**
 * Sanitize array
 *
 * @return Array
 */
function notifier_sanitize_array ( $array ) {
	foreach ( $array as $key => &$value ) {
        if ( is_array( $value ) ) {
            $value = notifier_sanitize_array($value);
        } else {
            $value = sanitize_text_field( $value );
        }
    }
    return $array;
}

/**
 * Upload image from URL programmatically
 */
function notifier_upload_file_by_url( $image_url ) {

	// it allows us to use download_url() and wp_handle_sideload() functions
	require_once ABSPATH . 'wp-admin/includes/file.php';

	// download to temp dir
	$temp_file = download_url( $image_url );

	if ( is_wp_error( $temp_file ) ) {
		return false;
	}

	// move the temp file into the uploads directory
	$file = array(
		'name'     => basename( parse_url($image_url, PHP_URL_PATH) ),
		'type'     => mime_content_type( $temp_file ),
		'tmp_name' => $temp_file,
		'size'     => filesize( $temp_file ),
	);

	$sideload = wp_handle_sideload(
		$file,
		array(
			'test_form'   => false // no needs to check 'action' parameter
		)
	);

	if ( ! empty( $sideload[ 'error' ] ) ) {
		// you may return error message if you want
		return false;
	}

	// it is time to add our uploaded image into WordPress media library
	$attachment_id = wp_insert_attachment(
		array(
			'guid'           => $sideload[ 'url' ],
			'post_mime_type' => $sideload[ 'type' ],
			'post_title'     => basename( $sideload[ 'file' ] ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$sideload[ 'file' ]
	);

	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		return false;
	}

	// update medatata, regenerate image sizes
	require_once ABSPATH . 'wp-admin/includes/image.php';

	wp_update_attachment_metadata(
		$attachment_id,
		wp_generate_attachment_metadata( $attachment_id, $sideload[ 'file' ] )
	);

	return $attachment_id;

}

/**
 * Sanitize phone number
 */
function notifier_sanitize_phone_number( $phone_number ) {
	return preg_replace( '/[^\d+]/', '', $phone_number );
}

/**
 * Validate phone number
 */
function notifier_validate_phone_number( $phone_number ) {
    return preg_match( '/^\+[1-9]\d{8,15}$/', $phone_number );
}

/**
 * Maybe add default country code
 */
function notifier_maybe_add_default_country_code( $phone_number ) {
	if('+' === substr($phone_number, 0, 1) || '' == trim($phone_number)){
		return $phone_number;
	}

	$defualt_code = get_option( NOTIFIER_PREFIX . 'default_country_code', '' );
	if('' == trim($defualt_code)){
		return $phone_number;
	}

	$phone_number = notifier_sanitize_phone_number($defualt_code) . $phone_number;
	return $phone_number;
}

/*
 * Generate API key.
 */
function notifier_generate_random_key( $length = 25 ) {
	$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$charactersLength = strlen($characters);
	$randomString = '';
	for ( $i = 0; $i < $length; $i++ ) {
		$randomString .= $characters[rand(0, $charactersLength - 1)];
	}
	return $randomString;
}

/**
 * Converts a UTC date string to the WordPress site's timezone.
 */
function notifier_convert_date_from_utc($utc_date, $format = 'Y-m-d H:i:s') {
    if ($utc_date === '') {
        return current_time('mysql'); 
    }

    try {
        $timezone_string = wp_timezone_string() ?: 'UTC';
        $site_timezone = new DateTimeZone($timezone_string);
        $date = new DateTime($utc_date, new DateTimeZone('UTC'));
        $date->setTimezone($site_timezone);
        return $date->format($format);
    } catch (Exception $e) {
        error_log('Error converting UTC date to WordPress timezone: ' . $e->getMessage());
        return '';
    }
}

/**
 * Converts a date string from the WordPress site's timezone to UTC.
 */
function notifier_convert_date_to_utc($wp_date, $format = 'Y-m-d H:i:s') {
    if ($wp_date === '') {
        return current_time('mysql');
    }

    try {
        $timezone_string = wp_timezone_string() ?: 'UTC';
        $site_timezone = new DateTimeZone($timezone_string);
        $date = new DateTime($wp_date, $site_timezone);
        $date->setTimezone(new DateTimeZone('UTC'));
        return $date->format($format);
    } catch (Exception $e) {
        error_log('Error converting date from WordPress timezone to UTC: ' . $e->getMessage());
        return '';
    }
}
