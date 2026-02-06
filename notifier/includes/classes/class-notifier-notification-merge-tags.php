<?php
/**
 * Notification Merge Tags class
 *
 * @package    Wa_Notifier
 */

use Automattic\WooCommerce\Utilities\OrderUtil;

class Notifier_Notification_Merge_Tags {

	public $merge_tags = array();

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
	public static function post_merge_tags($merge_tags) {
		$args = array(
	 		'public' => true,
		);
		$post_types = get_post_types( $args, 'objects');
		unset($post_types['attachment']);

		foreach($post_types as $post){
			$post_meta_keys = self::get_custom_meta_keys($post->name);
			if(!empty($post_meta_keys)){
				foreach($post_meta_keys as $post_meta_key){
					$merge_tags[$post->labels->singular_name . ' Custom Meta'][] = array(
						'id' 			=> $post->name . '_meta_' . $post_meta_key,
						'label' 		=> $post_meta_key,
						'preview_value' => '123',
						'return_type'	=> 'all',
						'value'			=> function ($args) use ($post_meta_key) {
							$value = get_post_meta($args['object_id'], $post_meta_key, true);
							if(is_array($value) || is_object($value)){
								$value = json_encode($value);
							}
							return (string) $value;
						}
					);
				}
			}

			$merge_tags[$post->labels->singular_name][] = array(
				'id' 			=> $post->name.'_ID',
				'label' 		=> $post->labels->singular_name.' ID',
				'preview_value' => '123',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					return $args['object_id'];
				}
			);

			$merge_tags[$post->labels->singular_name][]	= array(
				'id' 			=> $post->name.'_title',
				'label' 		=> $post->labels->singular_name.' title',
				'preview_value' => 'Hello World!',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$post = get_post($args['object_id']);
					return $post->post_title;
				}
			);

			$merge_tags[$post->labels->singular_name][]	= array(
					'id' 			=> $post->name.'_permalink',
					'label' 		=> $post->labels->singular_name.' permalink',
					'preview_value' => site_url() . '/hello-world/',
					'return_type'	=> 'text',
					'value'			=> function ($args) {
						$post = get_post($args['object_id']);
						return get_permalink( $post->ID );
					}
			);

			$merge_tags[$post->labels->singular_name][]	= array(
					'id' 			=> $post->name.'_author',
					'label' 		=> $post->labels->singular_name.' author',
					'preview_value' => 'John Doe',
					'return_type'	=> 'text',
					'value'			=> function ($args) {
						$post = get_post($args['object_id']);
						return $post->post_author;
					}
			);

			$merge_tags[$post->labels->singular_name][]	= array(
				'id' 			=> $post->name.'_publish_date',
				'label' 		=> $post->labels->singular_name.' publish date',
				'preview_value' => date(get_option('date_format')),
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$post = get_post($args['object_id']);
					return $post->post_date;
				}
			);

			$merge_tags[$post->labels->singular_name][]	= array(
				'id' 			=> $post->name.'_content',
				'label' 		=> $post->labels->singular_name.' content',
				'preview_value' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$post = get_post($args['object_id']);
					return sanitize_text_field($post->post_content);
				}
			);

			$merge_tags[$post->labels->singular_name][]	= array(
				'id' 			=> $post->name.'_slug',
				'label' 		=> $post->labels->singular_name.' slug',
				'preview_value' => 'hello-world',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$post = get_post($args['object_id']);
					return $post->post_name;
				}
			);

			$merge_tags[$post->labels->singular_name][]	= array(
				'id' 			=> $post->name.'_status',
				'label' 		=> $post->labels->singular_name.' status',
				'preview_value' => 'publish',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$post = get_post($args['object_id']);
					return $post->post_status;
				}
			);

			$merge_tags[$post->labels->singular_name][]	= array(
				'id' 			=> $post->name.'_modified_date',
				'label' 		=> $post->labels->singular_name.' modified date',
				'preview_value' => date(get_option('date_format')),
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$post = get_post($args['object_id']);
					return $post->post_modified;
				}
			);

			$merge_tags[$post->labels->singular_name][]	= array(
				'id' 			=> $post->name.'_featured_image',
				'label' 		=> $post->labels->singular_name.' featured image',
				'preview_value' => get_custom_logo(),
				'return_type'	=> 'image',
				'value'			=> function ($args) {
					$post = get_post($args['object_id']);
				 	return get_the_post_thumbnail_url($post, 'full');
				}
			);

			if($post->name == 'post'){
				$merge_tags[$post->labels->singular_name][] = array(
					'id' 			=> 'post_excerpt',
					'label' 		=> 'Post excerpt',
					'preview_value' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
					'return_type'	=> 'text',
					'value'			=> function ($args) {
						$post = get_post($args['object_id']);
						return sanitize_text_field($post->post_excerpt);
					}
				);
			}
		}

		return $merge_tags;
	}

	/**
	 * Comment merge tags
	 */
	public static function comment_merge_tags($merge_tags) {
		$merge_tags['Comment'] = array(
			array(
				'id' 			=> 'comment_ID',
				'label' 		=> 'Comment ID',
				'preview_value' => '123',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					return $args['object_id'];
				}
			),
			array(
				'id' 			=> 'comment_author',
				'label' 		=> 'Comment author',
				'preview_value' => 'John Doe',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$comment = get_comment($args['object_id']);
					return $comment->comment_author;
				}
			),
			array(
				'id' 			=> 'comment_author_email',
				'label' 		=> 'Comment author email',
				'preview_value' => 'john@example.com',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$comment = get_comment($args['object_id']);
					return $comment->comment_author_email;
				}
			),
			array(
				'id' 			=> 'comment_author_url',
				'label' 		=> 'Comment author URL',
				'preview_value' => 'https://example.com',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$comment = get_comment($args['object_id']);
					return $comment->comment_author_url;
				}
			),
			array(
				'id' 			=> 'comment_content',
				'label' 		=> 'Comment content',
				'preview_value' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$comment = get_comment($args['object_id']);
					return sanitize_text_field($comment->comment_content);
				}
			),
			array(
				'id' 			=> 'comment_post_ID',
				'label' 		=> 'Comment post ID',
				'preview_value' => '124',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$comment = get_comment($args['object_id']);
					return $comment->comment_post_ID;
				}
			),
			array(
				'id' 			=> 'comment_post_title',
				'label' 		=> 'Comment post title',
				'preview_value' => 'Hello World!',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$comment = get_comment($args['object_id']);
					return get_the_title($comment->comment_post_ID);
				}
			),
			array(
				'id' 			=> 'comment_post_url',
				'label' 		=> 'Comment post URL',
				'preview_value' => 'https://example.com/hello-world/',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$comment = get_comment($args['object_id']);
					return get_the_permalink($comment->comment_post_ID);
				}
			),
		);
		return $merge_tags;
	}

	/**
	 * User merge tags
	 */
	public static function user_merge_tags($merge_tags) {
		$merge_tags['User'] = array(
			array(
				'id' 			=> 'user_ID',
				'label' 		=> 'User ID',
				'preview_value' => '123',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					return $args['object_id'];
				}
			),
			array(
				'id' 			=> 'user_login',
				'label' 		=> 'Username',
				'preview_value' => 'username',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$user = get_userdata($args['object_id']);
					return $user->user_login;
				}
			),
			array(
				'id' 			=> 'user_email',
				'label' 		=> 'User email',
				'return_type'	=> 'text',
				'preview_value' => 'john@example.com',
				'value'			=> function ($args) {
					$user = get_userdata($args['object_id']);
					return $user->user_email;
				}
			),
			array(
				'id' 			=> 'user_first_name',
				'label' 		=> 'User first name',
				'preview_value' => 'John',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$user = get_userdata($args['object_id']);
					return $user->first_name;
				}
			),
			array(
				'id' 			=> 'user_last_name',
				'label' 		=> 'User last name',
				'preview_value' => 'Doe',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$user = get_userdata($args['object_id']);
					return $user->last_name;
				}
			),
			array(
				'id' 			=> 'user_display_name',
				'label' 		=> 'User display name',
				'preview_value' => 'John Doe',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$user = get_userdata($args['object_id']);
					return $user->display_name;
				}
			),
			array(
				'id' 			=> 'user_url',
				'label' 		=> 'User website',
				'preview_value' => 'https://example.com',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$user = get_userdata($args['object_id']);
					return $user->user_url;
				}
			),
			array(
				'id' 			=> 'user_role',
				'label' 		=> 'User role',
				'preview_value' => 'Subscriber',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$user = get_userdata($args['object_id']);
					return $user->role;
				}
			),
		);

		$user_meta_keys = self::get_user_meta_keys();
		if(!empty($user_meta_keys)){
			foreach($user_meta_keys as $user_meta_key){
				$merge_tags['User Custom Meta'][] = array(
					'id' 			=> 'user_meta_' . $user_meta_key,
					'label' 		=> $user_meta_key,
					'preview_value' => '123',
					'return_type'	=> 'all',
					'value'			=> function ($args) use ($user_meta_key) {
						$value = get_user_meta($args['object_id'], $user_meta_key, true);
						if(is_array($value) || is_object($value)){
							$value = json_encode($value);
						}
						return (string) $value;
					}
				);
			}
		}

		return $merge_tags;
	}

	/**
	 * Attachement merge tags
	 */
	public static function attachment_merge_tags($merge_tags) {

		$merge_tags['Attachment'][] = array(
			'id' 			=> 'attachment_ID',
			'label' 		=> 'Attachment ID',
			'preview_value' => '123',
			'return_type'	=> 'text',
			'value'			=> function ($args) {
				return $args['object_id'];
			}
		);

		$merge_tags['Attachment'][]	= array(
			'id' 			=> 'attachment_title',
			'label' 		=> 'Attachment title',
			'preview_value' => 'Hello World!',
			'return_type'	=> 'text',
			'value'			=> function ($args) {
				$post = get_post($args['object_id']);
				return $post->post_title;
			}
		);

		$merge_tags['Attachment'][]	= array(
				'id' 			=> 'attachment_permalink',
				'label' 		=> 'Attachment permalink',
				'preview_value' => site_url() . '/hello-world/',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$post = get_post($args['object_id']);
					return get_permalink( $post->ID );
				}
		);

		$merge_tags['Attachment'][]	= array(
				'id' 			=> 'attachment_author',
				'label' 		=> 'Attachment author',
				'preview_value' => 'John Doe',
				'return_type'	=> 'text',
				'value'			=> function ($args) {
					$post = get_post($args['object_id']);
					return $post->post_author;
				}
		);

		$merge_tags['Attachment'][]	= array(
			'id' 			=> 'attachment_publish_date',
			'label' 		=> 'Attachment publish date',
			'preview_value' => date(get_option('date_format')),
			'return_type'	=> 'text',
			'value'			=> function ($args) {
				$post = get_post($args['object_id']);
				return $post->post_date;
			}
		);

		$merge_tags['Attachment'][]	= array(
			'id' 			=> 'attachment_file_url',
			'label' 		=> 'Attachment File URL',
			'preview_value' => site_url() . '/hello-world/',
			'return_type'	=> 'text',
			'value'			=> function ($args) {
				$post = get_post($args['object_id']);
				return wp_get_attachment_url( $post->ID );
			}
		);

		$post_meta_keys = self::get_custom_meta_keys('attachment');
		if(!empty($post_meta_keys)){
			foreach($post_meta_keys as $post_meta_key){
				$merge_tags['Attachment Custom Meta'][] = array(
					'id' 			=> 'attachment_meta_' . $post_meta_key,
					'label' 		=> $post_meta_key,
					'preview_value' => '123',
					'return_type'	=> 'all',
					'value'			=> function ($args) use ($post_meta_key) {
						$value = get_post_meta($args['object_id'], $post_meta_key, true);
						if(is_array($value) || is_object($value)){
							$value = json_encode($value);
						}
						return (string) $value;
					}
				);
			}
		}

		return $merge_tags;
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
						if ( isset($tag['id']) && $tag_id == $tag['id'] ) {
							$value = $tag['value']($context_args);
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
	public static function post_recipient_fields($recipient_fields){
		$args = array(
	 		'public' => true
		);
		$post_types = get_post_types( $args, 'objects');
		foreach($post_types as $post){
			$post_meta_keys = self::get_custom_meta_keys($post->name);
			if(!empty($post_meta_keys)){
				foreach($post_meta_keys as $post_meta_key){
					$recipient_fields[$post->labels->singular_name . ' Custom Meta'][] = array(
						'id' 			=> $post->name . '_meta_' . $post_meta_key,
						'label' 		=> $post_meta_key,
						'preview_value' => '123',
						'return_type'	=> 'text',
						'value'			=> function ($args) use ($post_meta_key) {
							$value = get_post_meta($args['object_id'], $post_meta_key, true);
							if(is_array($value) || is_object($value)){
								$value = json_encode($value);
							}
							return (string) $value;
						}
					);
				}
			}
		}
		return $recipient_fields;
	}

	/*
	 * User recipient fields
	 */
	public static function user_recipient_fields($recipient_fields){
		$user_meta_keys = self::get_user_meta_keys();
		if(!empty($user_meta_keys)){
			foreach($user_meta_keys as $user_meta_key){
				$recipient_fields['User Custom Meta'][] = array(
					'id' 			=> 'user_meta_' . $user_meta_key,
					'label' 		=> $user_meta_key,
					'preview_value' => '123',
					'return_type'	=> 'text',
					'value'			=> function ($args) use ($user_meta_key) {
						$value = get_user_meta($args['object_id'], $user_meta_key, true);
						if(is_array($value) || is_object($value)){
							$value = json_encode($value);
						}
						return (string) $value;
					}
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
						// Backward compatibility
						if(in_array( $recipient_field, array('billing_phone', 'shipping_phone') )){
							$recipient_field = 'woo_order_' . $recipient_field;
						}
						if ( isset($field['id']) && $recipient_field == $field['id'] ) {
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
	 * Get custom meta keys
	 */
	public static function get_custom_meta_keys($custom_post_type) {
	    global $wpdb;

	    $custom_meta_keys = get_transient('notifier_custom_meta_keys');

	    if (false === $custom_meta_keys) {
	        $custom_meta_keys = array();
	    }

	    if (isset($custom_meta_keys[$custom_post_type])) {
	        $meta_keys = $custom_meta_keys[$custom_post_type];
	    } else {
	        $show_hidden_keys = get_option(NOTIFIER_PREFIX . 'hidden_custom_keys', 'no');
	        $hidden_key_filter = ('yes' == $show_hidden_keys) ? "" : "AND meta_key NOT LIKE '\_%'";

	        if ($custom_post_type === 'shop_order') {
	            $is_hpos_enabled = OrderUtil::custom_orders_table_usage_is_enabled();
	            if ($is_hpos_enabled) {
	                // HPOS enabled: query the wc_orders_meta table
	                $sql_query = "
	                    SELECT DISTINCT meta_key
	                    FROM {$wpdb->prefix}wc_orders_meta
	                    WHERE 1=1 {$hidden_key_filter}
	                    ORDER BY meta_key ASC
	                ";
	            } else {
	                // HPOS disabled: use wp_postmeta for shop_order
	                $sql_query = "
	                    SELECT DISTINCT pm.meta_key
	                    FROM {$wpdb->postmeta} pm
	                    LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
	                    WHERE p.post_type = '%s' {$hidden_key_filter}
	                    ORDER BY pm.meta_key ASC
	                ";
	                $sql_query = $wpdb->prepare($sql_query, $custom_post_type);
	            }
	        } else {
	            // Non shop_order post types
	            $sql_query = "
	                SELECT DISTINCT pm.meta_key
	                FROM {$wpdb->postmeta} pm
	                LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
	                WHERE p.post_type = '%s' {$hidden_key_filter}
	                ORDER BY pm.meta_key ASC
	            ";
	            $sql_query = $wpdb->prepare($sql_query, $custom_post_type);
	        }

	        // Execute the query and fetch the meta keys
	        $meta_keys = $wpdb->get_col($sql_query);

	        // Cache the result for 10 minutes
	        $custom_meta_keys[$custom_post_type] = $meta_keys;
	        set_transient('notifier_custom_meta_keys', $custom_meta_keys, 600); // set for 10 min.
	    }

	    return $meta_keys;
	}


	/**
	 * Get user meta keys
	 */
	public static function get_user_meta_keys() {
	    global $wpdb;

	    $meta_keys = get_transient('_notifier_user_meta_keys');

	    if(false === $meta_keys){
	    	$show_hidden_keys = get_option( NOTIFIER_PREFIX . 'hidden_custom_keys', 'no' );
	    	if('yes' == $show_hidden_keys){
				$hidden_key_filter = "";
			}
			else{
				$hidden_key_filter = "WHERE pm.meta_key NOT LIKE '\_%'";
			}

		    $sql_query = "
		        SELECT DISTINCT pm.meta_key
		        FROM {$wpdb->usermeta} pm
		        LEFT JOIN {$wpdb->users} p ON p.ID = pm.user_id
		        {$hidden_key_filter}
		        ORDER BY pm.meta_key ASC
		    ";

		    $prepared_query = $wpdb->prepare($sql_query);
		    $meta_keys = $wpdb->get_col($prepared_query);

		    set_transient('_notifier_user_meta_keys', $meta_keys, 600); // set for 10 min.
		}

	    return $meta_keys;
	}

}
