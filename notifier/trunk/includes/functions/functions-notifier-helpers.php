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
	$charactersLength = strlen( $characters );
	$randomString = '';
	for ( $i = 0; $i < $length; $i++ ) {
		$randomString .= $characters[ random_int( 0, $charactersLength - 1 ) ];
	}
	return $randomString;
}

/**
 * Converts a UTC date string to the WordPress site's timezone.
 */
function notifier_convert_date_from_utc($utc_date, $format = 'Y-m-d H:i:s') {
    if ($utc_date === '') {
        return current_time( 'mysql' );
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

