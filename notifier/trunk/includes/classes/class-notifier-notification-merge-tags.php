<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notification Merge Tags class
 *
 * @package    Wa_Notifier
 */

use Automattic\WooCommerce\Utilities\OrderUtil;

class Notifier_Notification_Merge_Tags {

	/**
	 * Init.
	 */
	public static function init() {
		add_filter( 'notifier_notification_merge_tags', array(__CLASS__, 'post_merge_tags') );
		add_filter( 'notifier_notification_merge_tags', array(__CLASS__, 'comment_merge_tags') );
		add_filter( 'notifier_notification_merge_tags', array(__CLASS__, 'user_merge_tags') );
		add_filter( 'notifier_notification_merge_tags', array(__CLASS__, 'attachment_merge_tags') );
		add_filter( 'notifier_notification_recipient_fields', array( __CLASS__, 'post_recipient_fields') );
		add_filter( 'notifier_notification_recipient_fields', array( __CLASS__, 'user_recipient_fields') );
	}

	/**
	 * Merge tag group types for a post/page/CPT trigger context.
	 *
	 * @param string $type_label Singular label of the post type (e.g. 'Post', 'Page').
	 * @return array
	 */
	public static function get_post_merge_tag_types( $type_label ) {
		return array( 'WordPress', $type_label, $type_label . ' Custom Meta' );
	}

	/**
	 * Recipient field group types for a post/page/CPT trigger context.
	 *
	 * @param string $type_label Singular label of the post type (e.g. 'Post', 'Page').
	 * @return array
	 */
	public static function get_post_recipient_types( $type_label ) {
		return array( $type_label . ' Custom Meta' );
	}

	/**
	 * Merge tag group types for a user trigger context.
	 *
	 * @return array
	 */
	public static function get_user_merge_tag_types() {
		return array( 'WordPress', 'User', 'User Custom Meta' );
	}

	/**
	 * Recipient field group types for a user trigger context.
	 *
	 * @return array
	 */
	public static function get_user_recipient_types() {
		return array( 'User Custom Meta' );
	}

	/**
	 * Merge tag group types for a comment trigger context.
	 *
	 * @return array
	 */
	public static function get_comment_merge_tag_types() {
		return array( 'WordPress', 'Comment' );
	}

	/**
	 * Merge tag group types for an attachment trigger context.
	 *
	 * @return array
	 */
	public static function get_attachment_merge_tag_types() {
		return array( 'WordPress', 'Attachment', 'Attachment Custom Meta' );
	}

	/**
	 * Recipient field group types for an attachment trigger context.
	 *
	 * @return array
	 */
	public static function get_attachment_recipient_types() {
		return array( 'Attachment Custom Meta' );
	}

	/**
	 * Get WordPress merge tags
	 */
	public static function get_merge_tags($types = array()) {
		$merge_tags = array();
		$merge_tags = apply_filters('notifier_notification_merge_tags', $merge_tags);
		$final_merge_tags = array();

		$final_merge_tags['WordPress'] = array(
			array(
				'id' 			=> 'site_title',
				'label' 		=> 'Site title',
				'preview_value' => get_bloginfo('name'),
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					return get_bloginfo('name');
				}
			),
			array(
				'id'			=> 'site_tagline',
				'label' 		=> 'Site tagline',
				'preview_value' => get_bloginfo('description'),
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					return get_bloginfo('description');
				}
			),
			array(
				'id'			=> 'site_url',
				'label'		 	=> 'Site URL',
				'preview_value' => get_bloginfo('url'),
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					return get_bloginfo('url');
				}
			),
			array(
				'id' 			=> 'site_logo_image',
				'label' 		=> 'Site logo image',
				'preview_value' => get_custom_logo(),
				'return_type'	=> 'image',
				'value'			=> function ($args) {
					$custom_logo_id = get_theme_mod( 'custom_logo' );
					return wp_get_attachment_image_url( $custom_logo_id, 'full' );
				}
			),
			array(
				'id'			=> 'admin_email',
				'label' 		=> 'Admin email',
				'preview_value' => get_bloginfo('admin_email'),
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					return get_bloginfo('admin_email');
				}
			),
			array(
				'id'			=> 'current_datetime',
				'label' 		=> 'Current datetime',
				'preview_value' => current_datetime()->format(get_option('date_format')) . ' ' . current_datetime()->format(get_option('time_format')),
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					return current_datetime()->format(get_option('date_format')) . ' ' . current_datetime()->format(get_option('time_format'));
				}
			),
			array(
				'id'			=> 'current_date',
				'label' 		=> 'Current date',
				'preview_value' => current_datetime()->format(get_option('date_format')),
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					return current_datetime()->format(get_option('date_format'));
				}
			),
			array(
				'id'			=> 'current_time',
				'label' 		=> 'Current time',
				'preview_value' => current_datetime()->format(get_option('time_format')),
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					return current_datetime()->format(get_option('time_format'));
				}
			)
		);

		foreach ($types as $type) {
			if( 'WordPress' == $type) {
				continue;
			}
			if(isset($merge_tags[$type])){
				$final_merge_tags[$type] = $merge_tags[$type];
			}
		}

		return $final_merge_tags;
	}

	/**
	 * Post merge tags
	 */
	public static function post_merge_tags( $merge_tags ) {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $post_types['attachment'] );

		foreach ( $post_types as $post_type ) {
			$type_name    = $post_type->name;
			$type_label   = $post_type->labels->singular_name;
			$meta_keys    = self::get_custom_meta_keys( $type_name );

			if ( ! empty( $meta_keys ) ) {
				foreach ( $meta_keys as $meta_key ) {
					$merge_tags[ $type_label . ' Custom Meta' ][] = array(
						'id'            => $type_name . '_meta_' . $meta_key,
						'label'         => $meta_key,
						'preview_value' => '123',
						'return_type'   => 'all',
						'source'        => 'meta',
						'path'          => $meta_key,
					);
				}
			}

			$fields = array(
				array( 'id' => $type_name . '_ID',             'label' => $type_label . ' ID',             'preview_value' => '123',                  'source' => 'post', 'path' => 'ID' ),
				array( 'id' => $type_name . '_title',          'label' => $type_label . ' title',          'preview_value' => 'Hello World!',          'source' => 'post', 'path' => 'post_title' ),
				array( 'id' => $type_name . '_permalink',      'label' => $type_label . ' permalink',      'preview_value' => site_url() . '/hello-world/', 'source' => 'computed', 'value' => function ( $bulk_data ) { return isset( $bulk_data['post'] ) ? get_permalink( $bulk_data['post']->ID ) : ''; } ),
				array( 'id' => $type_name . '_author',         'label' => $type_label . ' author',         'preview_value' => 'John Doe',              'source' => 'post', 'path' => 'post_author' ),
				array( 'id' => $type_name . '_publish_date',   'label' => $type_label . ' publish date',   'preview_value' => date( get_option( 'date_format' ) ), 'source' => 'post', 'path' => 'post_date' ),
				array( 'id' => $type_name . '_content',        'label' => $type_label . ' content',        'preview_value' => 'Lorem ipsum...',        'source' => 'post', 'path' => 'post_content' ),
				array( 'id' => $type_name . '_slug',           'label' => $type_label . ' slug',           'preview_value' => 'hello-world',           'source' => 'post', 'path' => 'post_name' ),
				array( 'id' => $type_name . '_status',         'label' => $type_label . ' status',         'preview_value' => 'publish',               'source' => 'post', 'path' => 'post_status' ),
				array( 'id' => $type_name . '_modified_date',  'label' => $type_label . ' modified date',  'preview_value' => date( get_option( 'date_format' ) ), 'source' => 'post', 'path' => 'post_modified' ),
				array( 'id' => $type_name . '_featured_image', 'label' => $type_label . ' featured image', 'preview_value' => '', 'return_type' => 'image', 'source' => 'computed', 'value' => function ( $bulk_data ) { return isset( $bulk_data['post'] ) ? get_the_post_thumbnail_url( $bulk_data['post'], 'full' ) : ''; } ),
			);

			if ( 'post' === $type_name ) {
				$fields[] = array( 'id' => 'post_excerpt', 'label' => 'Post excerpt', 'preview_value' => 'Lorem ipsum...', 'source' => 'post', 'path' => 'post_excerpt' );
			}

			foreach ( $fields as $field ) {
				$merge_tags[ $type_label ][] = array_merge( array( 'return_type' => 'text' ), $field );
			}
		}

		return $merge_tags;
	}

	/**
	 * Comment merge tags
	 */
	public static function comment_merge_tags( $merge_tags ) {
		$merge_tags['Comment'] = array(
			array( 'id' => 'comment_ID',          'label' => 'Comment ID',          'preview_value' => '123',                  'return_type' => 'text', 'source' => 'comment', 'path' => 'comment_ID' ),
			array( 'id' => 'comment_author',       'label' => 'Comment author',       'preview_value' => 'John Doe',             'return_type' => 'text', 'source' => 'comment', 'path' => 'comment_author' ),
			array( 'id' => 'comment_author_email', 'label' => 'Comment author email', 'preview_value' => 'john@example.com',     'return_type' => 'text', 'source' => 'comment', 'path' => 'comment_author_email' ),
			array( 'id' => 'comment_author_url',   'label' => 'Comment author URL',   'preview_value' => 'https://example.com',  'return_type' => 'text', 'source' => 'comment', 'path' => 'comment_author_url' ),
			array( 'id' => 'comment_content',      'label' => 'Comment content',      'preview_value' => 'Lorem ipsum...',       'return_type' => 'text', 'source' => 'comment', 'path' => 'comment_content' ),
			array( 'id' => 'comment_post_ID',      'label' => 'Comment post ID',      'preview_value' => '124',                  'return_type' => 'text', 'source' => 'comment', 'path' => 'comment_post_ID' ),
			array(
				'id'            => 'comment_post_title',
				'label'         => 'Comment post title',
				'preview_value' => 'Hello World!',
				'return_type'   => 'text',
				'source'        => 'computed',
				'value'         => function ( $bulk_data ) {
					return isset( $bulk_data['comment'] ) ? get_the_title( $bulk_data['comment']->comment_post_ID ) : '';
				},
			),
			array(
				'id'            => 'comment_post_url',
				'label'         => 'Comment post URL',
				'preview_value' => 'https://example.com/hello-world/',
				'return_type'   => 'text',
				'source'        => 'computed',
				'value'         => function ( $bulk_data ) {
					return isset( $bulk_data['comment'] ) ? get_the_permalink( $bulk_data['comment']->comment_post_ID ) : '';
				},
			),
		);
		return $merge_tags;
	}

	/**
	 * User merge tags
	 */
	public static function user_merge_tags( $merge_tags ) {
		$merge_tags['User'] = array(
			array( 'id' => 'user_ID',           'label' => 'User ID',           'preview_value' => '123',                'return_type' => 'text', 'source' => 'user', 'path' => 'ID' ),
			array( 'id' => 'user_login',        'label' => 'Username',          'preview_value' => 'username',           'return_type' => 'text', 'source' => 'user', 'path' => 'user_login' ),
			array( 'id' => 'user_email',        'label' => 'User email',        'preview_value' => 'john@example.com',   'return_type' => 'text', 'source' => 'user', 'path' => 'user_email' ),
			array( 'id' => 'user_first_name',   'label' => 'User first name',   'preview_value' => 'John',               'return_type' => 'text', 'source' => 'user', 'path' => 'first_name' ),
			array( 'id' => 'user_last_name',    'label' => 'User last name',    'preview_value' => 'Doe',                'return_type' => 'text', 'source' => 'user', 'path' => 'last_name' ),
			array( 'id' => 'user_display_name', 'label' => 'User display name', 'preview_value' => 'John Doe',           'return_type' => 'text', 'source' => 'user', 'path' => 'display_name' ),
			array( 'id' => 'user_url',          'label' => 'User website',      'preview_value' => 'https://example.com','return_type' => 'text', 'source' => 'user', 'path' => 'user_url' ),
			array( 'id' => 'user_role',         'label' => 'User role',         'preview_value' => 'Subscriber',         'return_type' => 'text', 'source' => 'computed', 'value' => function ( $bulk_data ) {
			$roles = isset( $bulk_data['user'] ) ? $bulk_data['user']->roles : array();
			return ! empty( $roles ) ? implode( ', ', $roles ) : '';
		} ),
		);

		$user_meta_keys = self::get_user_meta_keys();
		if ( ! empty( $user_meta_keys ) ) {
			foreach ( $user_meta_keys as $user_meta_key ) {
				$merge_tags['User Custom Meta'][] = array(
					'id'            => 'user_meta_' . $user_meta_key,
					'label'         => $user_meta_key,
					'preview_value' => '123',
					'return_type'   => 'all',
					'source'        => 'meta',
					'path'          => $user_meta_key,
				);
			}
		}

		return $merge_tags;
	}

	/**
	 * Attachment merge tags
	 */
	public static function attachment_merge_tags( $merge_tags ) {
		$merge_tags['Attachment'] = array(
			array( 'id' => 'attachment_ID',           'label' => 'Attachment ID',           'preview_value' => '123',                  'return_type' => 'text',  'source' => 'post', 'path' => 'ID' ),
			array( 'id' => 'attachment_title',        'label' => 'Attachment title',        'preview_value' => 'Hello World!',          'return_type' => 'text',  'source' => 'post', 'path' => 'post_title' ),
			array( 'id' => 'attachment_permalink',    'label' => 'Attachment permalink',    'preview_value' => site_url() . '/hello-world/', 'return_type' => 'text', 'source' => 'computed', 'value' => function ( $bulk_data ) { return isset( $bulk_data['post'] ) ? get_permalink( $bulk_data['post']->ID ) : ''; } ),
			array( 'id' => 'attachment_author',       'label' => 'Attachment author',       'preview_value' => 'John Doe',              'return_type' => 'text',  'source' => 'post', 'path' => 'post_author' ),
			array( 'id' => 'attachment_publish_date', 'label' => 'Attachment publish date', 'preview_value' => date( get_option( 'date_format' ) ), 'return_type' => 'text', 'source' => 'post', 'path' => 'post_date' ),
			array( 'id' => 'attachment_file_url',     'label' => 'Attachment File URL',     'preview_value' => site_url() . '/hello-world/', 'return_type' => 'text', 'source' => 'computed', 'value' => function ( $bulk_data ) { return isset( $bulk_data['post'] ) ? wp_get_attachment_url( $bulk_data['post']->ID ) : ''; } ),
		);

		$post_meta_keys = self::get_custom_meta_keys( 'attachment' );
		if ( ! empty( $post_meta_keys ) ) {
			foreach ( $post_meta_keys as $post_meta_key ) {
				$merge_tags['Attachment Custom Meta'][] = array(
					'id'            => 'attachment_meta_' . $post_meta_key,
					'label'         => $post_meta_key,
					'preview_value' => '123',
					'return_type'   => 'all',
					'source'        => 'meta',
					'path'          => $post_meta_key,
				);
			}
		}

		return $merge_tags;
	}

	// ========================================
	// BULK PAYLOAD BUILDERS (WordPress objects)
	// ========================================

	/**
	 * Build payload for a WordPress post trigger.
	 *
	 * Fetches post + all meta in 2 calls, maps all defined fields, flattens meta dynamically.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type slug.
	 * @return array Payload with merge_tags_data and recipient_fields.
	 */
	public static function build_post_payload( $post_id, $post_type = '' ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'merge_tags_data' => array(), 'recipient_fields' => array() );
		}

		$raw_meta = get_post_meta( $post_id );
		$meta_by_key = array();
		foreach ( $raw_meta as $key => $values ) {
			$meta_by_key[ $key ] = is_array( $values ) ? $values[0] : $values;
		}

		$bulk_data = array(
			'post' => $post,
			'meta' => $meta_by_key,
		);

		$merge_tags_data  = array();
		$recipient_fields = array();

		$enabled_merge_tags       = get_option( 'notifier_wp_enabled_merge_tags', array() );
		$enabled_recipient_fields = get_option( 'notifier_wp_enabled_recipient_fields', array() );
		$enabled_merge_tags       = is_array( $enabled_merge_tags ) ? $enabled_merge_tags : array();
		$enabled_recipient_fields = is_array( $enabled_recipient_fields ) ? $enabled_recipient_fields : array();

		$type        = $post_type ?: $post->post_type;
		$post_object = get_post_type_object( $type );
		$type_label  = $post_object ? $post_object->labels->singular_name : ucfirst( $type );
		$all_merge_tags = self::get_merge_tags( self::get_post_merge_tag_types( $type_label ) );
		foreach ( $all_merge_tags as $group => $tags ) {
			foreach ( $tags as $tag ) {
				if ( empty( $tag['id'] ) ) {
					continue;
				}
				// Custom meta tags are handled separately below.
				if ( false !== strpos( $tag['id'], '_meta_' ) ) {
					continue;
				}
				// Predefined fields are always included — no opt-in required.
				$value = self::resolve_wp_field_from_bulk( $tag, $bulk_data );
				if ( null !== $value ) {
					$merge_tags_data[ $tag['id'] ] = html_entity_decode( sanitize_text_field( (string) $value ) );
				}
			}
		}

		// Flatten post meta dynamically — only include keys selected in WordPress config.
		$enabled_dynamic_keys = array_unique( array_merge( $enabled_merge_tags, $enabled_recipient_fields ) );

		foreach ( $meta_by_key as $key => $value ) {
			$tag_id = $type . '_meta_' . $key;
			if ( ! in_array( $tag_id, $enabled_dynamic_keys, true ) ) {
				continue;
			}
			if ( in_array( $tag_id, $enabled_merge_tags, true ) ) {
				$merge_tags_data[ $tag_id ] = is_array( $value ) || is_object( $value ) ? json_encode( $value ) : (string) $value;
			}
			if ( in_array( $tag_id, $enabled_recipient_fields, true ) ) {
				$recipient_fields[ $tag_id ] = is_array( $value ) || is_object( $value ) ? json_encode( $value ) : (string) $value;
			}
		}

		// Live diff for new meta keys.
		self::maybe_update_known_post_meta_keys( $type, array_keys( $meta_by_key ) );

		return array(
			'merge_tags_data'  => $merge_tags_data,
			'recipient_fields' => $recipient_fields,
		);
	}

	/**
	 * Build payload for a WordPress user trigger.
	 *
	 * @param int $user_id User ID.
	 * @return array Payload with merge_tags_data and recipient_fields.
	 */
	public static function build_user_payload( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array( 'merge_tags_data' => array(), 'recipient_fields' => array() );
		}

		$raw_meta = get_user_meta( $user_id );
		$meta_by_key = array();
		foreach ( $raw_meta as $key => $values ) {
			$meta_by_key[ $key ] = is_array( $values ) ? $values[0] : $values;
		}

		$bulk_data = array(
			'user' => $user,
			'meta' => $meta_by_key,
		);

		$merge_tags_data  = array();
		$recipient_fields = array();

		$enabled_merge_tags       = get_option( 'notifier_wp_enabled_merge_tags', array() );
		$enabled_recipient_fields = get_option( 'notifier_wp_enabled_recipient_fields', array() );
		$enabled_merge_tags       = is_array( $enabled_merge_tags ) ? $enabled_merge_tags : array();
		$enabled_recipient_fields = is_array( $enabled_recipient_fields ) ? $enabled_recipient_fields : array();

		$all_merge_tags = self::get_merge_tags( self::get_user_merge_tag_types() );
		foreach ( $all_merge_tags as $group => $tags ) {
			foreach ( $tags as $tag ) {
				if ( empty( $tag['id'] ) ) {
					continue;
				}
				// Custom meta tags are handled separately below.
				if ( false !== strpos( $tag['id'], '_meta_' ) ) {
					continue;
				}
				$value = self::resolve_wp_field_from_bulk( $tag, $bulk_data );
				if ( null !== $value ) {
					$merge_tags_data[ $tag['id'] ] = html_entity_decode( sanitize_text_field( (string) $value ) );
				}
			}
		}

		// Flatten user meta dynamically — only include keys selected in WordPress config.
		$enabled_dynamic_keys = array_unique( array_merge( $enabled_merge_tags, $enabled_recipient_fields ) );

		foreach ( $meta_by_key as $key => $value ) {
			$tag_id = 'user_meta_' . $key;
			if ( ! in_array( $tag_id, $enabled_dynamic_keys, true ) ) {
				continue;
			}
			if ( in_array( $tag_id, $enabled_merge_tags, true ) ) {
				$merge_tags_data[ $tag_id ] = is_array( $value ) || is_object( $value ) ? json_encode( $value ) : (string) $value;
			}
			if ( in_array( $tag_id, $enabled_recipient_fields, true ) ) {
				$recipient_fields[ $tag_id ] = is_array( $value ) || is_object( $value ) ? json_encode( $value ) : (string) $value;
			}
		}

		// Live diff for new user meta keys.
		self::maybe_update_known_user_meta_keys( array_keys( $meta_by_key ) );

		return array(
			'merge_tags_data'  => $merge_tags_data,
			'recipient_fields' => $recipient_fields,
		);
	}

	/**
	 * Build payload for a WordPress comment trigger.
	 *
	 * @param int $comment_id Comment ID.
	 * @return array Payload with merge_tags_data and recipient_fields.
	 */
	public static function build_comment_payload( $comment_id ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return array( 'merge_tags_data' => array(), 'recipient_fields' => array() );
		}

		$raw_meta = get_comment_meta( $comment_id );
		$meta_by_key = array();
		foreach ( $raw_meta as $key => $values ) {
			$meta_by_key[ $key ] = is_array( $values ) ? $values[0] : $values;
		}

		$bulk_data = array(
			'comment' => $comment,
			'meta'    => $meta_by_key,
		);

		$merge_tags_data  = array();
		$recipient_fields = array();

		$all_merge_tags = self::get_merge_tags( self::get_comment_merge_tag_types() );
		foreach ( $all_merge_tags as $group => $tags ) {
			foreach ( $tags as $tag ) {
				if ( empty( $tag['id'] ) ) {
					continue;
				}
				$value = self::resolve_wp_field_from_bulk( $tag, $bulk_data );
				if ( null !== $value ) {
					$merge_tags_data[ $tag['id'] ] = html_entity_decode( sanitize_text_field( (string) $value ) );
				}
			}
		}

		return array(
			'merge_tags_data'  => $merge_tags_data,
			'recipient_fields' => $recipient_fields,
		);
	}

	/**
	 * Resolve a single merge tag or recipient field from pre-fetched WP bulk data.
	 *
	 * @param array $field_def Field definition.
	 * @param array $bulk_data Pre-fetched data (post/user/comment + meta).
	 * @return string|null
	 */
	private static function resolve_wp_field_from_bulk( $field_def, $bulk_data ) {
		$source = isset( $field_def['source'] ) ? $field_def['source'] : null;

		if ( isset( $field_def['value'] ) && is_callable( $field_def['value'] ) ) {
			try {
				return call_user_func( $field_def['value'], $bulk_data );
			} catch ( \Throwable $e ) {
				return null;
			}
		}

		$path = isset( $field_def['path'] ) ? $field_def['path'] : null;

		if ( ! $source || ! $path ) {
			return null;
		}

		if ( 'post' === $source && isset( $bulk_data['post'] ) ) {
			return isset( $bulk_data['post']->$path ) ? (string) $bulk_data['post']->$path : '';
		}

		if ( 'user' === $source && isset( $bulk_data['user'] ) ) {
			return isset( $bulk_data['user']->$path ) ? (string) $bulk_data['user']->$path : '';
		}

		if ( 'comment' === $source && isset( $bulk_data['comment'] ) ) {
			return isset( $bulk_data['comment']->$path ) ? (string) $bulk_data['comment']->$path : '';
		}

		if ( 'meta' === $source && isset( $bulk_data['meta'][ $path ] ) ) {
			$value = $bulk_data['meta'][ $path ];
			return is_array( $value ) || is_object( $value ) ? json_encode( $value ) : (string) $value;
		}

		return null;
	}

	/**
	 * Live diff: detect new post meta keys and persist + schedule async schema push.
	 *
	 * @param string $post_type Post type slug.
	 * @param array  $live_keys Keys found in the current post's meta.
	 */
	private static function maybe_update_known_post_meta_keys( $post_type, $live_keys ) {
		$all_known = get_option( 'notifier_custom_meta_keys', array() );
		$known     = isset( $all_known[ $post_type ] ) ? $all_known[ $post_type ] : array();
		$new_keys  = array_diff( $live_keys, $known );
		if ( ! empty( $new_keys ) ) {
			$all_known[ $post_type ] = array_unique( array_merge( $known, $new_keys ) );
			update_option( 'notifier_custom_meta_keys', $all_known, false );
		}
	}

	/**
	 * Live diff: detect new user meta keys and persist.
	 *
	 * @param array $live_keys Keys found in the current user's meta.
	 */
	private static function maybe_update_known_user_meta_keys( $live_keys ) {
		$known    = get_option( 'notifier_user_meta_keys', array() );
		$new_keys = array_diff( $live_keys, $known );
		if ( ! empty( $new_keys ) ) {
			update_option( 'notifier_user_meta_keys', array_unique( array_merge( $known, $new_keys ) ), false );
		}
	}

	/**
	 * Return notification merge tags for supplied trigger
	 */
	public static function get_trigger_merge_tags($trigger) {
		$tags = array();
		$main_triggers = Notifier_Notification_Triggers::get_notification_triggers();
		foreach ($main_triggers as $key => $triggers) {
			foreach ($triggers as $t) {
				if ( $trigger == $t['id'] ) {
					$tags = $t['merge_tags'];
					break 2;
				}
			}
		}

		$merge_tags = array();

		foreach ($tags as $tag_key => $merge_tags_list) {
			foreach($merge_tags_list as $tag) {
				$merge_tags[$tag_key][$tag['id']] = $tag['label'];
			}
		}

		return $merge_tags;
	}

	/**
	 * Return notification merge tag value for supplied trigger and object
	 */
	public static function get_trigger_merge_tag_value($tag_id, $context_args) {
		$object_type = isset( $context_args['object_type'] ) ? $context_args['object_type'] : '';
		$object_id   = isset( $context_args['object_id'] ) ? (int) $context_args['object_id'] : 0;

		// Route to bulk builders for known object types — avoids per-field DB calls.
		if ( $object_id ) {
			if ( 'order' === $object_type && class_exists( 'Notifier_Woocommerce' ) ) {
				static $wc_payload_cache = array();
				$cache_key = 'order_' . $object_id;
				if ( ! isset( $wc_payload_cache[ $cache_key ] ) ) {
					$wc_payload_cache[ $cache_key ] = Notifier_Woocommerce::build_wc_order_payload( $object_id, '' );
				}
				$payload = $wc_payload_cache[ $cache_key ];
				return isset( $payload['merge_tags_data'][ $tag_id ] ) ? $payload['merge_tags_data'][ $tag_id ] : '';
			}

			if ( 'user' === $object_type ) {
				static $user_payload_cache = array();
				if ( ! isset( $user_payload_cache[ $object_id ] ) ) {
					$user_payload_cache[ $object_id ] = self::build_user_payload( $object_id );
				}
				$payload = $user_payload_cache[ $object_id ];
				return isset( $payload['merge_tags_data'][ $tag_id ] ) ? $payload['merge_tags_data'][ $tag_id ] : '';
			}

			if ( 'comment' === $object_type ) {
				static $comment_payload_cache = array();
				if ( ! isset( $comment_payload_cache[ $object_id ] ) ) {
					$comment_payload_cache[ $object_id ] = self::build_comment_payload( $object_id );
				}
				$payload = $comment_payload_cache[ $object_id ];
				return isset( $payload['merge_tags_data'][ $tag_id ] ) ? $payload['merge_tags_data'][ $tag_id ] : '';
			}

			// Any post type (post, page, attachment, custom).
			$post_types = array_merge( array( 'post', 'page', 'attachment' ), array_keys( get_post_types( array( 'public' => true ) ) ) );
			if ( in_array( $object_type, $post_types, true ) ) {
				static $post_payload_cache = array();
				if ( ! isset( $post_payload_cache[ $object_id ] ) ) {
					$post_payload_cache[ $object_id ] = self::build_post_payload( $object_id, $object_type );
				}
				$payload = $post_payload_cache[ $object_id ];
				return isset( $payload['merge_tags_data'][ $tag_id ] ) ? $payload['merge_tags_data'][ $tag_id ] : '';
			}
		}

		// Fallback: find tag definition and call value closure directly (form plugins, cart abandonment, etc.).
		$value = '';
		$main_triggers = Notifier_Notification_Triggers::get_notification_triggers();
		foreach ($main_triggers as $key => $triggers) {
			foreach ($triggers as $t) {
				$merge_tags = (!empty($t['merge_tags'])) ? $t['merge_tags'] : array();
				if(empty($merge_tags)){
					continue;
				}
				foreach($merge_tags as $tags){
					if(empty($tags)){
						continue;
					}
				foreach($tags as $tag){
					if ( isset($tag['id']) && $tag_id == $tag['id'] && isset( $tag['value'] ) && is_callable( $tag['value'] ) ) {
						try {
							$value = $tag['value']($context_args);
						} catch ( \Throwable $e ) {
							$value = '';
						}
						goto end;
					}
				}
				}
			}
		}
		end:
		return $value;
	}

	/*
	 * Get recipient fields
	 */
	public static function get_recipient_fields($types = array()){
		$recipient_fields = array();
		$recipient_fields = apply_filters('notifier_notification_recipient_fields', $recipient_fields);

		$final_recipient_fields = array();

		if (empty($types)) {
			$final_recipient_fields = $recipient_fields;
		} else {
			foreach ($types as $type) {
				if(isset($recipient_fields[$type])){
					$final_recipient_fields[$type] = $recipient_fields[$type];
				}
			}
		}

		return $final_recipient_fields;
	}

	/*
	 * Post recipient fields
	 */
	public static function post_recipient_fields( $recipient_fields ) {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $post_types as $post_type ) {
			$post_meta_keys = self::get_custom_meta_keys( $post_type->name );
			if ( ! empty( $post_meta_keys ) ) {
				foreach ( $post_meta_keys as $post_meta_key ) {
					$recipient_fields[ $post_type->labels->singular_name . ' Custom Meta' ][] = array(
						'id'            => $post_type->name . '_meta_' . $post_meta_key,
						'label'         => $post_meta_key,
						'preview_value' => '123',
						'return_type'   => 'text',
						'source'        => 'meta',
						'path'          => $post_meta_key,
					);
				}
			}
		}
		return $recipient_fields;
	}

	/*
	 * User recipient fields
	 */
	public static function user_recipient_fields( $recipient_fields ) {
		$user_meta_keys = self::get_user_meta_keys();
		if ( ! empty( $user_meta_keys ) ) {
			foreach ( $user_meta_keys as $user_meta_key ) {
				$recipient_fields['User Custom Meta'][] = array(
					'id'            => 'user_meta_' . $user_meta_key,
					'label'         => $user_meta_key,
					'preview_value' => '123',
					'return_type'   => 'text',
					'source'        => 'meta',
					'path'          => $user_meta_key,
				);
			}
		}
		return $recipient_fields;
	}

	/*
	 * Get trigger recipient fields
	 */
	public static function get_trigger_recipient_fields($trigger){
		$recipient_fields = array();
		$main_triggers = Notifier_Notification_Triggers::get_notification_triggers();
		foreach ($main_triggers as $key => $triggers) {
			foreach ($triggers as $t) {
				if ( $trigger == $t['id'] ) {
					$recipient_fields = $t['recipient_fields'];
					break 2;
				}
			}
		}

		$final_fields = array();

		foreach ($recipient_fields as $field_key => $recipient_fields_list) {
			foreach($recipient_fields_list as $field) {
				$final_fields[$field_key][$field['id']] = $field['label'];
			}
		}

		return $final_fields;
	}

	/**
	 * Get recipient_field value
	 */
	public static function get_trigger_recipient_field_value($recipient_field, $context_args) {
		// Backward compatibility alias.
		if ( in_array( $recipient_field, array( 'billing_phone', 'shipping_phone' ), true ) ) {
			$recipient_field = 'woo_order_' . $recipient_field;
		}

		$object_type = isset( $context_args['object_type'] ) ? $context_args['object_type'] : '';
		$object_id   = isset( $context_args['object_id'] ) ? (int) $context_args['object_id'] : 0;

		// Route to bulk builders for known object types.
		if ( $object_id ) {
			if ( 'order' === $object_type && class_exists( 'Notifier_Woocommerce' ) ) {
				static $wc_rf_payload_cache = array();
				$cache_key = 'order_' . $object_id;
				if ( ! isset( $wc_rf_payload_cache[ $cache_key ] ) ) {
					$wc_rf_payload_cache[ $cache_key ] = Notifier_Woocommerce::build_wc_order_payload( $object_id, '' );
				}
				$payload = $wc_rf_payload_cache[ $cache_key ];
				$value   = isset( $payload['recipient_fields'][ $recipient_field ] ) ? $payload['recipient_fields'][ $recipient_field ] : '';
				$value   = notifier_sanitize_phone_number( $value );
				return notifier_maybe_add_default_country_code( $value );
			}
		}

		// Fallback: find field definition and call value closure directly.
		$value = '';
		$main_triggers = Notifier_Notification_Triggers::get_notification_triggers();
		foreach ($main_triggers as $key => $triggers) {
			foreach ($triggers as $t) {
				$recipient_fields = (!empty($t['recipient_fields'])) ? $t['recipient_fields'] : array();
				if(empty($recipient_fields)){
					continue;
				}
				foreach($recipient_fields as $fields){
					foreach($fields as $field){
						if ( isset($field['id']) && $recipient_field == $field['id'] && isset( $field['value'] ) && is_callable( $field['value'] ) ) {
							$value = notifier_sanitize_phone_number($field['value']($context_args));
							$value = notifier_maybe_add_default_country_code($value);
							break 2;
						}
					}
				}
			}
		}
		return $value;
	}

	/**
	 * Get custom meta keys for a post type, or all combined when called with no parameter.
	 * Always fetches and stores all keys (including hidden/_prefixed). Filter by show_hidden in REST when returning.
	 *
	 * @param string|null $custom_post_type Post type slug, or null/empty to return all keys from all public post types.
	 * @return array When $custom_post_type is set: list of meta key strings. When null: list of [ 'post_type' => string, 'key' => string ].
	 */
	public static function get_custom_meta_keys( $custom_post_type = null ) {
		global $wpdb;

		if ( $custom_post_type === null || $custom_post_type === '' ) {
			// Inline: build combined list from all public post types (no separate helper).
			$post_types = get_post_types( array( 'public' => true ), 'objects' );
			unset( $post_types['attachment'] );
			$combined = array();
			foreach ( $post_types as $pt ) {
				$keys = self::get_custom_meta_keys( $pt->name );
				if ( ! empty( $keys ) && is_array( $keys ) ) {
					foreach ( $keys as $key ) {
						$combined[] = array( 'post_type' => $pt->name, 'key' => $key );
					}
				}
			}
			return $combined;
		}

		$custom_meta_keys = get_option( 'notifier_custom_meta_keys', array() );

		if ( isset( $custom_meta_keys[ $custom_post_type ] ) ) {
			return $custom_meta_keys[ $custom_post_type ];
		}

		// Fetch all meta keys (normal and hidden); no filter.
		$hidden_key_filter = '';

		if ( $custom_post_type === 'shop_order' && class_exists( OrderUtil::class ) ) {
			$is_hpos_enabled = OrderUtil::custom_orders_table_usage_is_enabled();
			if ( $is_hpos_enabled ) {
				$sql_query = "
					SELECT DISTINCT meta_key
					FROM {$wpdb->prefix}wc_orders_meta
					WHERE 1=1 {$hidden_key_filter}
					ORDER BY meta_key ASC
				";
			} else {
				$sql_query = "
					SELECT DISTINCT pm.meta_key
					FROM {$wpdb->postmeta} pm
					LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					WHERE p.post_type = '%s' {$hidden_key_filter}
					ORDER BY pm.meta_key ASC
				";
				$sql_query = $wpdb->prepare( $sql_query, $custom_post_type );
			}
		} else {
			$sql_query = "
				SELECT DISTINCT pm.meta_key
				FROM {$wpdb->postmeta} pm
				LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = '%s' {$hidden_key_filter}
				ORDER BY pm.meta_key ASC
			";
			$sql_query = $wpdb->prepare( $sql_query, $custom_post_type );
		}

		$meta_keys = $wpdb->get_col( $sql_query );
		$custom_meta_keys[ $custom_post_type ] = $meta_keys;
		update_option( 'notifier_custom_meta_keys', $custom_meta_keys, false );

		return $meta_keys;
	}

	/**
	 * Get user meta keys. Always fetches and stores all keys (including hidden/_prefixed). Filter by show_hidden in REST when returning.
	 *
	 * @return array List of meta key strings.
	 */
	public static function get_user_meta_keys() {
		global $wpdb;

		$meta_keys = get_option( 'notifier_user_meta_keys', false );

		if ( false === $meta_keys ) {
			$sql_query = "
				SELECT DISTINCT pm.meta_key
				FROM {$wpdb->usermeta} pm
				LEFT JOIN {$wpdb->users} p ON p.ID = pm.user_id
				ORDER BY pm.meta_key ASC
			";
			$meta_keys = $wpdb->get_col( $sql_query );
			update_option( 'notifier_user_meta_keys', $meta_keys, false );
		}

		return is_array( $meta_keys ) ? $meta_keys : array();
	}

}
