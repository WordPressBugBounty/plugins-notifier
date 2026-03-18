<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notification Triggers class
 *
 * @package    Wa_Notifier
 */
class Notifier_Notification_Triggers {

	/**
	 * Init.
	 */
	public static function init() {
		add_filter( 'init', array( __CLASS__ , 'register_cpt') );
		add_action( 'admin_menu', array( __CLASS__ , 'setup_admin_page') );
		add_action( 'init', array( __CLASS__ , 'setup_triggers_action_hooks' ));
		add_action( 'wp_ajax_notifier_change_trigger_status', array(__CLASS__, 'notifier_change_trigger_status'));
		add_action( 'wp_ajax_notifier_fetch_trigger_fields', array(__CLASS__, 'notifier_fetch_trigger_fields'));
	}

	/**
	 * Register custom post type.
	 *
	 * Always registered so existing v2 trigger posts remain accessible for
	 * migration queries and backward-compat firing. The admin UI is hidden.
	 */
	public static function register_cpt () {
		notifier_register_post_type('wa_notifier_trigger', 'Trigger', 'Triggers');
	}

	/**
	 * Add page to admin menu.
	 *
	 * Hidden after migration is complete — users no longer need the CPT UI.
	 */
	public static function setup_admin_page () {
		// v3: Trigger CPT menu items are always hidden.
		// The CPT is still registered (register_cpt) so posts exist and fire,
		// but users manage everything through the React Integrations UI.
		return;
	}

	/**
	 * Setup triggers action hooks
	 */
	public static function setup_triggers_action_hooks() {
		// Legacy path: register hooks for CPT-enabled triggers.
		$triggers_to_register = self::get_enabled_notification_triggers();

		// v3 path: also register hooks for any trigger whose integration is connected,
		// regardless of whether a legacy CPT trigger exists.
		if ( class_exists( 'Notifier_Connection' ) ) {
			$all_triggers = self::get_notification_triggers();
			foreach ( $all_triggers as $group => $group_triggers ) {
				foreach ( $group_triggers as $trigger ) {
					$trigger_id = isset( $trigger['id'] ) ? $trigger['id'] : '';
					$base       = self::get_trigger_id_without_site_key( $trigger_id );
					$slug       = self::get_integration_slug_for_trigger( $base );
					if ( $slug && Notifier_Connection::is_connected( $slug ) ) {
						$triggers_to_register[] = $trigger;
					}
				}
			}
		}

		// De-duplicate by trigger ID to avoid registering the same hook twice.
		$registered = array();
		foreach ( $triggers_to_register as $trigger ) {
			$trigger_id = isset( $trigger['id'] ) ? $trigger['id'] : '';
			$hook       = isset( $trigger['action']['hook'] )     ? $trigger['action']['hook']     : '';
			$callback   = isset( $trigger['action']['callback'] ) ? $trigger['action']['callback'] : '';
			$priority   = isset( $trigger['action']['priority'] ) ? $trigger['action']['priority'] : 10;
			$args_num   = isset( $trigger['action']['args_num'] ) ? $trigger['action']['args_num'] : 1;
			if ( '' === $hook || '' === $callback ) {
				continue;
			}
			$key = $trigger_id ?: ( $hook . '|' . ( is_string( $callback ) ? $callback : '' ) );
			if ( isset( $registered[ $key ] ) ) {
				continue;
			}
			$registered[ $key ] = true;
			add_action( $hook, $callback, $priority, $args_num );
		}
	}

	/**
	 * Get notification triggers
	 */
	public static function get_notification_triggers() {
		$triggers = array();
		$args = array(
	 		'public' => true,
		);

		$post_types = get_post_types( $args, 'objects');

		$triggers = array ();
		unset($post_types['attachment']);

		foreach($post_types as $post){
			$post_slug = $post->name;
			$triggers['WordPress'][] = array(
			 	'id'			=> 'new_'.$post->name,
				'label' 		=> 'New '.$post->labels->singular_name.' is published',
				'description'	=> 'Trigger notification when a new '.$post->name.' is published.',
				'merge_tags' 	=> Notifier_Notification_Merge_Tags::get_merge_tags( Notifier_Notification_Merge_Tags::get_post_merge_tag_types( $post->labels->singular_name ) ),
				'recipient_fields'	=> Notifier_Notification_Merge_Tags::get_recipient_fields( Notifier_Notification_Merge_Tags::get_post_recipient_types( $post->labels->singular_name ) ),
				'action'		=> array (
					'hook'		=> 'publish_'.$post->name,
					'args_num'	=> 3,
					'callback' 	=> function ( $post_id, $post, $old_status ) use ($post_slug) {
						if ( 'publish' !== $old_status ) {
							$args = array (
								'object_type' 	=> $post_slug,
								'object_id'		=> $post->ID
							);
							self::send_trigger_request('new_'.$post_slug, $args);
						}
					}
				),
			);
		}

		$triggers['WordPress'][] = array(
		 	'id'			=> 'new_comment',
			'label' 		=> 'New Comment is added',
			'description'	=> 'Trigger notification when a new comment is added.',
			'merge_tags' 	=> Notifier_Notification_Merge_Tags::get_merge_tags( Notifier_Notification_Merge_Tags::get_comment_merge_tag_types() ),
			'recipient_fields'	=> array(),
			'action'		=> array (
				'hook'		=> 'comment_post',
				'args_num'	=> 3,
				'callback' 	=> function ( $comment_id, $comment_approved, $commentdata ) {
					if ( 'spam' != $comment_approved) {
						$args = array (
							'object_type' 	=> 'comment',
							'object_id'		=> $comment_id
						);
						self::send_trigger_request('new_comment', $args);
					}
				}
			)
		);

		$triggers['WordPress'][] = array(
		 	'id'			=> 'new_user',
			'label' 		=> 'New User is registered',
			'description'	=> 'Trigger notification when a new user is created.',
			'merge_tags' 	=> Notifier_Notification_Merge_Tags::get_merge_tags( Notifier_Notification_Merge_Tags::get_user_merge_tag_types() ),
			'recipient_fields'	=> Notifier_Notification_Merge_Tags::get_recipient_fields( Notifier_Notification_Merge_Tags::get_user_recipient_types() ),
			'action'		=> array (
				'hook'		=> 'user_register',
				'callback' 	=> function ( $user_id ) {
					$args = array (
						'object_type' 	=> 'user',
						'object_id'		=> $user_id
					);
					self::send_trigger_request('new_user', $args);
				},
				'priority'	=> 999
			)
		);

		$triggers['WordPress'][] = array(
		 	'id'			=> 'new_attachment',
			'label' 		=> 'New Media is uploded',
			'description'	=> 'Trigger notification when a new attachement is uploded.',
			'merge_tags' 	=> Notifier_Notification_Merge_Tags::get_merge_tags( Notifier_Notification_Merge_Tags::get_attachment_merge_tag_types() ),
			'recipient_fields'	=> Notifier_Notification_Merge_Tags::get_recipient_fields( Notifier_Notification_Merge_Tags::get_attachment_recipient_types() ),
			'action'		=> array (
				'hook'		=> 'add_attachment',
				'callback' 	=> function ( $attachement_id ) {
					$args = array (
						'object_type' 	=> 'attachment',
						'object_id'		=> $attachement_id
					);
					self::send_trigger_request('new_attachment', $args);
				}
			)
		);

		$triggers = apply_filters('notifier_notification_triggers', $triggers);

		// Add site key to trigger IDs
		$site_key = self::get_notification_trigger_site_key();

		foreach ($triggers as $trigs_key => $trigs) {
			foreach($trigs as $trig_key => $trig){
				$triggers[$trigs_key][$trig_key]['id'] = $site_key . $trig['id'];
			}
		}

		return $triggers;
	}

	/**
	 * Get notification trigger site key
	 */
	public static function get_notification_trigger_site_key(){
		$site_key = get_option(NOTIFIER_PREFIX . 'site_key');
		if(!$site_key){
			$site_key = notifier_generate_random_key(5);
			update_option(NOTIFIER_PREFIX . 'site_key', $site_key, true);
		}
		$site_key = 'wp_' . $site_key . '_';
		return $site_key;
	}

	/**
	 * Get notification trigger
	 */
	public static function get_notification_trigger($trigger) {
		$trigger = self::get_trigger_id_with_site_key($trigger);
		$found_trigger = array();
		$main_triggers = self::get_notification_triggers();
		foreach ($main_triggers as $key => $triggers) {
			foreach($triggers as $the_trigger){
				if($the_trigger['id'] == $trigger){
					$found_trigger = $the_trigger;
					break 2;
				}
			}
		}
		return $found_trigger;
	}

	/**
	 * Get enabled notification triggers
	 */
	public static function get_enabled_notification_triggers() {
		$enabled_triggers = array();
		$enabled_post_ids = get_posts( array (
			'post_type' 	=> 'wa_notifier_trigger',
			'post_status' 	=> 'publish',
			'numberposts' 	=> -1,
			'fields' 		=> 'ids',
			'meta_query' => array(
				array(
					'key' => NOTIFIER_PREFIX . 'trigger_enabled',
					'value' => 'yes',
					'compare' => '='
				)
			)
		) );

		if(!empty($enabled_post_ids)){
			foreach($enabled_post_ids as $enabled_post_id){
			 	$trigger_name = get_post_meta($enabled_post_id, NOTIFIER_PREFIX . 'trigger', true);
				$enabled_triggers[] = self::get_notification_trigger($trigger_name);
			}
		}

		return $enabled_triggers;
	}

	/**
	 * Check whether current trigger is in use
	 */
	public static function is_trigger_in_use($trigger, $excluded_ids = array()) {
		$in_use = false;
		$in_use_post_id = get_posts( array (
			'post_type' 	=> 'wa_notifier_trigger',
			'post_status' 	=> 'publish',
			'numberposts' 	=> -1,
			'fields' 		=> 'ids',
			'meta_query' => array(
				array(
					'key' => NOTIFIER_PREFIX . 'trigger',
					'value' => $trigger,
					'compare' => '='
				)
			),
			'post__not_in'	=> $excluded_ids
		) );

		if(!empty($in_use_post_id)){
			$in_use = $in_use_post_id[0];
		}

		return $in_use;
	}

	/**
	 * Send triggered notifications.
	 *
	 * Runs both paths simultaneously during migration:
	 * - v3 path: dispatches via Action Scheduler when the integration is connected.
	 * - Legacy path: fires via old API key until migration is complete.
	 *
	 * @param string $trigger      Trigger ID (with site key prefix).
	 * @param array  $context_args Context arguments passed to payload builders.
	 */
	public static function send_trigger_request( $trigger, $context_args ) {
		if ( empty( $context_args ) ) {
			return false;
		}

		$slug = self::get_integration_slug_for_trigger( $trigger );

		error_log( sprintf( 'Notifier [send_trigger_request] trigger=%s slug=%s', $trigger, $slug ) );

		// If the Woo plugin is active it owns all WooCommerce triggers — skip them here
		// so they are not double-fired. The Woo plugin registers its own action hooks.
		if ( 'woocommerce' === $slug && class_exists( 'WANOWC_Notification_Triggers' ) ) {
			error_log( 'Notifier [send_trigger_request] skipped — handed off to Woo plugin' );
			return false;
		}

		// v3 path: dispatch via Action Scheduler to SaaS integration endpoint.
		// Payload is built inside the async job to keep the main request fast.
		$is_connected = $slug && class_exists( 'Notifier_Connection' ) && Notifier_Connection::is_connected( $slug );
		error_log( sprintf( 'Notifier [send_trigger_request] is_connected=%s', $is_connected ? 'yes' : 'no' ) );
		if ( $is_connected ) {
			Notifier_Connection::dispatch_trigger( $slug, self::get_trigger_id_without_site_key( $trigger ), $context_args );
		}

		// Legacy path: fire via old API key until migration is complete.
		$migration_complete = class_exists( 'Notifier_Migration' ) && Notifier_Migration::is_migration_complete();
		error_log( sprintf( 'Notifier [send_trigger_request] migration_complete=%s', $migration_complete ? 'yes' : 'no' ) );
		if ( ! $migration_complete ) {
			self::send_legacy_trigger_request( $trigger, $context_args );
		}
	}

	/**
	 * Fire a trigger via the legacy /api/v1/fire_notification endpoint.
	 * Used during the transition period before migration is complete.
	 *
	 * @param string $trigger      Trigger ID (with site key prefix).
	 * @param array  $context_args Context arguments.
	 */
	private static function send_legacy_trigger_request( $trigger, $context_args ) {
		$api_key = trim( get_option( 'notifier_api_key', '' ) );
		if ( empty( $api_key ) ) {
			error_log( 'Notifier [send_legacy_trigger_request] skipped — no API key' );
			return;
		}

		// Send trigger in v2 format (wp_{key}_{slug}) so Phase 1 SaaS can rewrite and match notifications.
		$trigger = self::get_trigger_id_with_site_key( $trigger );

		error_log( sprintf( 'Notifier [send_legacy_trigger_request] trigger=%s', $trigger ) );

		// Look up the v2 CPT post for this trigger to get the user-selected fields.
		// This mirrors exactly what v2 sent — only the fields the user configured.
		$cpt_post = get_posts( array(
			'post_type'   => 'wa_notifier_trigger',
			'post_status' => 'publish',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_query'  => array(
				array(
					'key'   => NOTIFIER_PREFIX . 'trigger',
					'value' => $trigger,
				),
			),
		) );

		// If no CPT post exists for this trigger, the user never configured it in v2 — don't send.
		if ( empty( $cpt_post ) ) {
			error_log( sprintf( 'Notifier [send_legacy_trigger_request] skipped — no CPT post for trigger=%s', $trigger ) );
			return;
		}

		$post_id                  = $cpt_post[0];
		$enabled_merge_tags       = get_post_meta( $post_id, NOTIFIER_PREFIX . 'data_fields', true );
		$enabled_recipient_fields = get_post_meta( $post_id, NOTIFIER_PREFIX . 'recipient_fields', true );
		$enabled_merge_tags       = is_array( $enabled_merge_tags ) ? $enabled_merge_tags : array();
		$enabled_recipient_fields = is_array( $enabled_recipient_fields ) ? $enabled_recipient_fields : array();

		// Resolve only the selected field values.
		$merge_tags_data  = array();
		$recipient_fields = array();
		foreach ( $enabled_merge_tags as $tag_id ) {
			$merge_tags_data[ $tag_id ] = Notifier_Notification_Merge_Tags::get_trigger_merge_tag_value( $tag_id, $context_args );
		}
		foreach ( $enabled_recipient_fields as $field_id ) {
			$recipient_fields[ $field_id ] = Notifier_Notification_Merge_Tags::get_trigger_merge_tag_value( $field_id, $context_args );
		}

		error_log( sprintf( 'Notifier [send_legacy_trigger_request] recipient_fields before=%s', wp_json_encode( $recipient_fields ) ) );
		foreach ( $recipient_fields as $key => $value ) {
			$recipient_fields[ $key ] = notifier_maybe_add_default_country_code( notifier_sanitize_phone_number( (string) $value ) );
		}
		error_log( sprintf( 'Notifier [send_legacy_trigger_request] recipient_fields after=%s', wp_json_encode( $recipient_fields ) ) );

		$body = wp_json_encode( array(
			'source'           => 'wp',
			'trigger'          => $trigger,
			'merge_tags_data'  => $merge_tags_data,
			'recipient_fields' => $recipient_fields,
			'site_url'         => site_url(),
		) );

		error_log( 'Notifier: ' . $body );

		$url = untrailingslashit( NOTIFIER_APP_BASE_URL ) . '/' . NOTIFIER_APP_API_PATH . '/fire_notification?key=' . rawurlencode( $api_key );

		wp_remote_post( $url, array(
			'headers'   => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'      => $body,
			'timeout'   => 30,
			'sslverify' => true,
		) );
	}

	/**
	 * Determine the integration slug for a given trigger ID.
	 *
	 * @param string $trigger Trigger ID (with or without site key prefix).
	 * @return string|null Integration slug or null if not determinable.
	 */
	public static function get_integration_slug_for_trigger( $trigger ) {
		$base = self::get_trigger_id_without_site_key( $trigger );

		// woocommerce_cart_abandonment_recovery falls through to the woocommerce_ prefix check below.
		if ( strpos( $base, 'woo_' ) === 0 || strpos( $base, 'woocommerce_' ) === 0 ) {
			return 'woocommerce';
		}
		if ( strpos( $base, 'gravityforms_' ) === 0 ) {
			return 'gravityforms';
		}
		if ( strpos( $base, 'cf7_' ) === 0 ) {
			return 'cf7';
		}
		if ( strpos( $base, 'wpf_' ) === 0 || strpos( $base, 'wpforms_' ) === 0 ) {
			return 'wpforms';
		}
		if ( strpos( $base, 'ninjaforms_' ) === 0 ) {
			return 'ninjaforms';
		}
		if ( strpos( $base, 'formidableforms_' ) === 0 || strpos( $base, 'formidable_' ) === 0 ) {
			return 'formidable';
		}
		if ( strpos( $base, 'fluentforms_' ) === 0 ) {
			return 'fluentforms';
		}
		if ( strpos( $base, 'forminator_' ) === 0 ) {
			return 'forminator';
		}
		if ( strpos( $base, 'sureforms_' ) === 0 ) {
			return 'sureforms';
		}
		if ( strpos( $base, 'wsform_' ) === 0 ) {
			return 'wsform';
		}
		// WordPress core triggers (new_post, new_user, new_comment, new_attachment, etc.)
		if ( in_array( $base, array( 'new_comment', 'new_user', 'new_attachment' ), true )
			|| strpos( $base, 'new_' ) === 0 ) {
			return 'wordpress';
		}

		return null;
	}

	/**
	 * Enable / disable trigger
	 */
	public static function notifier_change_trigger_status(){
		// Security check: Verify user has manage_options capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => 'You do not have permission to perform this action.'
			) );
		}

		// Verify nonce for additional security
		if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'notifier_change_trigger_status' ) ) {
			wp_send_json_error( array(
				'message' => 'Security check failed.'
			) );
		}

		$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
		$enabled = isset($_POST['enabled']) ? sanitize_text_field($_POST['enabled']) : 'no';
		
		if('wa_notifier_trigger' != get_post_type($post_id)){
			wp_send_json( array(
				'error' => true,
				'message'  => 'Invalid post type.'
			) );
		}
		
		update_post_meta( $post_id, NOTIFIER_PREFIX . 'trigger_enabled' , $enabled);
		wp_send_json( array(
			'error' => false,
			'message'  => 'Updated'
		) );
	}

	/**
	 * Fetch trigger fields for a speicific trigger
	 */
	public static function notifier_fetch_trigger_fields(){
		// Security check: Verify user has manage_options capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(
				'message' => 'You do not have permission to perform this action.'
			) );
		}

		// Verify nonce for additional security
		if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'notifier_fetch_trigger_fields' ) ) {
			wp_send_json_error( array(
				'message' => 'Security check failed.'
			) );
		}

		$post_id =  isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
		$trigger =  isset($_POST['trigger']) ? sanitize_text_field($_POST['trigger']) : '';

		$data_fields = get_post_meta( $post_id, NOTIFIER_PREFIX . 'data_fields', true);
		$recipient_fields = get_post_meta( $post_id, NOTIFIER_PREFIX . 'recipient_fields', true);

		// Backward compatibility for Woo recipient fields not starting with woo_order_
		if(!empty($recipient_fields)){
			if ( strpos($trigger, 'woo_order') !== false){
				foreach($recipient_fields as $key => $recipient_field){
					if (in_array($recipient_field, array('billing_phone', 'shipping_phone'))){
						$recipient_fields[$key] = 'woo_order_' . $recipient_field;
					}
				}
			}
		}

		$merge_tags = Notifier_Notification_Merge_Tags::get_trigger_merge_tags($trigger);
		$recipient_tags = Notifier_Notification_Merge_Tags::get_trigger_recipient_fields($trigger);

		ob_start();
		echo '<div class="trigger-fields-wrap"><div class="d-flex justify-content-between"><label class="form-label w-auto">Data fields</label><div class="small"><a href="#" class="notifier-select-all-checkboxes">select all</a> / <a href="#" class="notifier-unselect-all-checkboxes">unselect all</a></div></div>';

		notifier_wp_select( array(
			'id'                => NOTIFIER_PREFIX . 'data_fields',
            'name'              => NOTIFIER_PREFIX . 'data_fields[]',
			'value'             => $data_fields,
			'label'             => '',
			'description'       => '',
			'placeholder'		=> 'Start typing here...',
			'options'           => $merge_tags,
			'show_wrapper'		=> false,
			'custom_attributes'	=> array('multiple' => 'multiple')
        ) );

		echo '<span class="description">Select the data fields that will be sent to WANotifier.com when the trigger happens. These fields will be available to map with message template variables like <code>{{1}}</code>, <code>{{2}}</code> etc when you <a href="https://app.wanotifier.com/notifications/add/" target="_blank">create a Notification</a>.</span></div>';

		if(!empty($recipient_tags)){
			echo '<div class="trigger-fields-wrap"><div class="d-flex justify-content-between"><label class="form-label w-auto">Recipient fields</label><div class="small"><a href="#" class="notifier-select-all-checkboxes">select all</a> / <a href="#" class="notifier-unselect-all-checkboxes">unselect all</a></div></div>';

			notifier_wp_select( array(
				'id'                => NOTIFIER_PREFIX . 'recipient_fields',
	            'name'              => NOTIFIER_PREFIX . 'recipient_fields[]',
				'value'             => $recipient_fields,
				'label'             => '',
				'description'       => '',
				'placeholder'		=> 'Start typing here...',
				'options'           => $recipient_tags,
				'show_wrapper'		=> false,
				'custom_attributes'	=> array('multiple' => 'multiple')
	        ) );

			echo '<span class="description">Select the recipient fields that will be sent to WANotifier.com when this trigger happens. These fields will be available under <b>Recipients</b> section when you create a Notification. Note that the selected recipient fields <b>must return a phone number</b> with a country code (e.g. +919876543210) or the message wll not be sent.</span></div>';
		}

		$html = ob_get_clean();

		wp_send_json( array(
			'status' 	=> 'success',
			'html'		=> $html
		) );
	}

	/**
	 * Get post trigger ID
	 */
	public static function get_post_trigger_id($post_id){
		$site_key = self::get_notification_trigger_site_key();
		$selected_trigger = get_post_meta( $post_id, NOTIFIER_PREFIX . 'trigger', true);
		if($selected_trigger && strpos($selected_trigger, $site_key) === false ){
			$selected_trigger = $site_key . $selected_trigger;
		}
		return $selected_trigger;
	}

	/**
	 * Get trigger ID with site key
	 */
	public static function get_trigger_id_with_site_key($trigger_id){
		$site_key = self::get_notification_trigger_site_key();
		if( strpos($trigger_id, $site_key) === false ){
			$trigger_id = $site_key . $trigger_id;
		}
		return $trigger_id;
	}

	/**
	 * Get trigger ID without site key
	 */
	public static function get_trigger_id_without_site_key($trigger_id){
		$site_key = self::get_notification_trigger_site_key();
		$trigger_id = str_replace($site_key, '', $trigger_id);
		return $trigger_id;
	}

}
