<?php
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
		add_action( 'add_meta_boxes', array( __CLASS__, 'create_meta_box' ) );
		add_action( 'post_submitbox_minor_actions', array( __CLASS__ , 'add_submitbox_meta' ) );
		add_action( 'save_post_wa_notifier_trigger', array(__CLASS__, 'save_meta'), 10, 2 );
		add_filter( 'bulk_actions-wa_notifier_trigger', array( __CLASS__, 'remove_bulk_actions' ) );
		add_filter( 'post_row_actions', array(__CLASS__, 'remove_quick_edit') , 10, 2);
		add_filter( 'gettext', array(__CLASS__, 'change_texts') , 10, 2 );
		add_filter( 'post_updated_messages', array(__CLASS__, 'update_save_messages') );
		add_filter( 'enter_title_here', array(__CLASS__, 'change_title_text') );
		add_action( 'wp_ajax_notifier_change_trigger_status', array(__CLASS__, 'notifier_change_trigger_status'));
		add_action( 'wp_ajax_notifier_fetch_trigger_fields', array(__CLASS__, 'notifier_fetch_trigger_fields'));
		add_filter( 'manage_wa_notifier_trigger_posts_columns', array( __CLASS__ , 'add_columns' ) );
		add_action( 'manage_wa_notifier_trigger_posts_custom_column', array( __CLASS__ , 'add_column_content' ) , 10, 2 );
		add_action( 'notifier_send_trigger_request', array( __CLASS__, 'send_scheduled_trigger_request' ), 10, 2 );
	}

	/**
	 * Register custom post type
	 */
	public static function register_cpt () {
		if (!Notifier::is_api_active()){
			return;
		}

		notifier_register_post_type('wa_notifier_trigger', 'Trigger', 'Triggers');
	}

	/**
	 * Add page to admin menu
	 */
	public static function setup_admin_page () {
		if (!Notifier::is_api_active()){
			return;
		}

		add_submenu_page( NOTIFIER_NAME, 'Trigger', 'Triggers', 'manage_options', 'edit.php?post_type=wa_notifier_trigger' );
		add_submenu_page( NOTIFIER_NAME, 'Add trigger', 'Add trigger', 'manage_options', 'post-new.php?post_type=wa_notifier_trigger' );
	}

	/**
	 * Create meta boxes
	 */
	public static function create_meta_box () {
		add_meta_box(
	        NOTIFIER_NAME . '-trigger-data',
	        'Trigger Settings',
	        'Notifier_Notification_Triggers::output',
	        'wa_notifier_trigger'
	    );

	    remove_meta_box( 'submitdiv', 'wa_notifier_trigger', 'side' );
    	add_meta_box( 'submitdiv', 'Save Trigger', 'post_submit_meta_box', 'wa_notifier_trigger', 'side', 'high' );
	}

	/**
	 * Add message template meta data in submit box
	 */
	public static function add_submitbox_meta () {
		if ( 'wa_notifier_trigger' != get_post_type() ) {
			return;
		}
		global $post_id;

		if(!isset($post_id)){
			return;
		}

		$enabled = get_post_meta( $post_id, NOTIFIER_PREFIX . 'trigger_enabled' , true);
		$checked = '';
		if('yes' == $enabled) {
			$checked = 'checked="checked"';
		}
		echo '<div class="notifier-trigger-status d-flex justify-content-between align-items-center">';
		echo '<b>Enable:</b>';
		echo '<div class="notifier-toggle-switch"><input class="notifier-enable-trigger" type="checkbox" '.$checked.' data-post-id="'.$post_id.'"></div>';
		echo '</div>';
	}

	/**
	 * Output meta box
	 */
	public static function output () {
		include_once NOTIFIER_PATH . 'views/admin-triggers.php';
	}

	/**
	 * Save meta
	 */
	public static function save_meta( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( 'trash' == get_post_status( $post_id ) || 'auto-draft' == get_post_status( $post_id ) ){
			return;
		}

		$trigger = isset($_POST[NOTIFIER_PREFIX . 'trigger']) ? sanitize_text_field( wp_unslash( $_POST[NOTIFIER_PREFIX . 'trigger'] ) ) : '';
		$in_use_post_id = self::is_trigger_in_use($trigger, array($post_id));
		if($in_use_post_id){
			$notices[] = array(
				'message' => '<b>ERROR: Trigger not Saved.</b> The selected trigger is already in use with another trigger: <a href="'. admin_url( 'post.php?post='.$in_use_post_id.'&action=edit' ) .'">' . get_the_title( $in_use_post_id ) . '</a>. Please select a different trigger or delete that trigger to save this one.',
				'type' => 'error'
			);
			new Notifier_Admin_Notices($notices, true);
			return;
		}

		$trigger_data = array();

		foreach ($_POST as $key => $data) {
			if (strpos($key, NOTIFIER_PREFIX) !== false) {
				if(is_array($data)) {
					$trigger_data[$key] = notifier_sanitize_array($data);
				}
				else{
					$trigger_data[$key] = sanitize_text_field( wp_unslash ($data) );
				}
			    update_post_meta( $post_id, $key, $trigger_data[$key]);
			}
		}

		// Delete data fields meta if not added in current request
		if(!isset($trigger_data[NOTIFIER_PREFIX . 'data_fields'])){
			delete_post_meta( $post_id, NOTIFIER_PREFIX . 'data_fields');
		}

		// Delete recipient fields meta if not added in current request
		if(!isset($trigger_data[NOTIFIER_PREFIX . 'recipient_fields'])){
			delete_post_meta( $post_id, NOTIFIER_PREFIX . 'recipient_fields');
		}

		$triggers_array = self::build_final_triggers_array();

		$params = array(
			'site_url'	=> site_url(),
			'source'	=> 'wp',
			'triggers'	=> $triggers_array
    	);

		$response = Notifier::send_api_request( 'update_triggers', $params, 'POST' );

		if($response->error){
			$notices[] = array(
				'message' => $response->message,
				'type' => 'error'
			);
			new Notifier_Admin_Notices($notices, true);
		}
		else{
			$notices[] = array(
				'message' => 'Trigger details saved and synced with your WANotifier.com account successfully!',
				'type' => 'success'
			);
			new Notifier_Admin_Notices($notices, true);
		}
	}

	/**
	 * Build final triggers array
	 */
	public static function build_final_triggers_array(){
		$final_triggers = array();
		$trigger_post_ids = get_posts(array (
			'post_type' 	=> 'wa_notifier_trigger',
			'post_status' 	=> 'publish',
			'numberposts' 	=> -1,
			'fields' 		=> 'ids'
		));

		if(! empty($trigger_post_ids)){
			foreach($trigger_post_ids as $trigger_post_id){
				$trigger_name = get_post_meta($trigger_post_id, NOTIFIER_PREFIX . 'trigger', true);
				$data_fields = get_post_meta($trigger_post_id, NOTIFIER_PREFIX . 'data_fields', true);
				$recipient_fields = get_post_meta($trigger_post_id, NOTIFIER_PREFIX . 'recipient_fields', true);
				$trigger_name = self::get_trigger_id_with_site_key($trigger_name);
				$final_triggers[] = array(
					'id'				=> $trigger_name,
					'data_fields'		=> isset($data_fields) ? $data_fields : array(),
					'recipient_fields'	=> isset($recipient_fields) ? $recipient_fields : array()
				);
			}
		}

		$triggers_array = array();

		$all_triggers = self::get_notification_triggers();
		foreach ($all_triggers as $key => $triggers) {
			foreach ($triggers as $trigger){
				foreach ($final_triggers as $final_trigger){
					if($trigger['id'] != $final_trigger['id']){
						continue;
					}

					unset($trigger['action']);

					$final_trigger_merge_tags = array();
					if(!empty($trigger['merge_tags'])){
						foreach($trigger['merge_tags'] as $merge_tags_group => $merge_tags){
							foreach($merge_tags as $merge_tag){
								if( !empty($final_trigger['data_fields']) && in_array($merge_tag['id'], $final_trigger['data_fields']) ){
									$final_trigger_merge_tags[$merge_tags_group][] = array(
										'id'			=> $merge_tag['id'],
										'label'			=> $merge_tag['label'],
										'preview_value'	=> isset($merge_tag['preview_value']) ? $merge_tag['preview_value'] : '',
										'return_type'	=> isset($merge_tag['return_type']) ? $merge_tag['return_type'] : ''
									);
								}
							}
						}
					}
					$trigger['merge_tags'] = $final_trigger_merge_tags;

					$final_trigger_recipient_fields = array();
					if(!empty($trigger['recipient_fields'])){
						foreach($trigger['recipient_fields'] as $recipient_tags_group => $recipient_tags){
							foreach($recipient_tags as $recipient_tag){
								if( ! empty($final_trigger['recipient_fields']) && in_array($recipient_tag['id'], $final_trigger['recipient_fields']) ){
									$final_trigger_recipient_fields[$recipient_tags_group][] = array(
										'id'		=> $recipient_tag['id'],
										'label'		=> $recipient_tag['label']
									);
								}
							}
						}
					}
					$trigger['recipient_fields'] = $final_trigger_recipient_fields;

					$triggers_array[$key][] = $trigger;
				}
			}
		}

		return $triggers_array;
	}

	/**
	 * Update save action messages
	 */
	public static function update_save_messages ($messages) {
		$messages['wa_notifier_trigger'][1] = '';
	    $messages['wa_notifier_trigger'][6] = '';
		return $messages;
	}

	/**
	 * Remove inline edit from Bulk Edit
	 */
	public static function remove_bulk_actions( $actions ){
        unset( $actions['inline'] );
        return $actions;
    }

    /**
	 * Remove inline Quick Edit
	 */
    public static function remove_quick_edit( $actions, $post ) {
    	if ('wa_notifier_trigger' == $post->post_type){
	     	unset($actions['inline hide-if-no-js']);
	    }
    	return $actions;
	}

	/**
	 * Change text of buttons and links
	 */
	public static function change_texts( $translation, $text ) {
		if ( 'wa_notifier_trigger' == get_post_type() ) {
			if ( $text == 'Update' ) {
				return 'Update';
			}
			elseif ($text == 'Publish') {
				return 'Save';
			}
		}
		return $translation;
	}

	/**
	 * Change add title here text
	 */
	public static function change_title_text( $title ){
		$screen = get_current_screen();
		if  ( 'wa_notifier_trigger' == $screen->post_type ) {
		  $title = 'Enter a trigger name';
		}
		return $title;
	}

	/**
	 * Add columns to list page
	 */
	public static function add_columns ($columns) {
		$date_col = $columns['date'];
		unset($columns['date']);
		$columns['trigger'] = 'Trigger';
		$columns['trigger_enabled'] = 'Status';
		$columns['date'] = $date_col;
		return $columns;
	}

	/**
	 * Add column content
	 */
	public static function add_column_content ( $column, $post_id ) {
		$group_name = '';
		$name = '';
		if ( 'trigger' === $column ) {
			$trigger = self::get_post_trigger_id( $post_id );
			$main_triggers = self::get_notification_triggers();
			foreach ($main_triggers as $key => $triggers) {
				foreach($triggers as $the_trigger){
					if($the_trigger['id'] == $trigger){
						$name = $the_trigger['label'];
						$group_name = $key;
						break 2;
					}
				}
			}
			echo '<small class="text-muted">' . $group_name . ' »</small><br>' . $name;
		}
		if ( 'trigger_enabled' === $column ) {
		    $enabled = get_post_meta( $post_id, NOTIFIER_PREFIX . 'trigger_enabled' , true);
			$checked = '';
			if('yes' == $enabled) {
				$checked = 'checked="checked"';
			}
			echo '<div class="notifier-toggle-switch"><input class="notifier-enable-trigger" type="checkbox" '.$checked.' data-post-id="'.$post_id.'"></div>';
		}
	}

	/**
	 * Setup triggers action hooks
	 */
	public static function setup_triggers_action_hooks() {
		$enabled_triggers = self::get_enabled_notification_triggers();
		foreach ($enabled_triggers as $trigger) {
			$hook 		= isset($trigger['action']['hook']) ? $trigger['action']['hook'] : '';
			$callback 	= isset($trigger['action']['callback']) ? $trigger['action']['callback'] : '';
			$priority 	= isset($trigger['action']['priority']) ? $trigger['action']['priority'] : 10;
			$args_num 	= isset($trigger['action']['args_num']) ? $trigger['action']['args_num'] : 1;
			if ('' == $hook || '' == $callback) {
				continue;
			}
			else{
				add_action($hook, $callback, $priority, $args_num);
			}
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
				'merge_tags' 	=> Notifier_Notification_Merge_Tags::get_merge_tags( array($post->labels->singular_name, $post->labels->singular_name . ' Custom Meta') ),
				'recipient_fields'	=> Notifier_Notification_Merge_Tags::get_recipient_fields( array($post->labels->singular_name . ' Custom Meta') ),
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
			'merge_tags' 	=> Notifier_Notification_Merge_Tags::get_merge_tags( array('Comment') ),
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
			'merge_tags' 	=> Notifier_Notification_Merge_Tags::get_merge_tags( array('User', 'User Custom Meta') ),
			'recipient_fields'	=> Notifier_Notification_Merge_Tags::get_recipient_fields( array('User Custom Meta') ),
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
			'merge_tags' 	=> Notifier_Notification_Merge_Tags::get_merge_tags( array('Attachment', 'Attachment Custom Meta') ),
			'recipient_fields'	=> Notifier_Notification_Merge_Tags::get_recipient_fields( array('Attachment Custom Meta') ),
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
	 * Get notification triggers for dropdown
	 */
	public static function get_notification_triggers_dropdown() {
		$main_triggers = self::get_notification_triggers();
		$dropdown_triggers = array('' => 'Select trigger');
		foreach ($main_triggers as $key => $triggers) {
			$dropdown_triggers[$key] = wp_list_pluck($triggers, 'label', 'id');
		}
		return $dropdown_triggers;
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
	 * Get notification trigger display name
	 */
	public static function get_notification_trigger_display_name($trigger){
		$main_triggers = self::get_notification_triggers();
		foreach ($main_triggers as $key => $triggers) {
			foreach($triggers as $the_trigger){
				if($the_trigger['id'] == $trigger){
					$name = $the_trigger['label'];
					break 2;
				}
			}
		}
		return $name;
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
	 * Get trigger post meta
	 */
	public static function get_trigger_post_meta($trigger, $meta_key) {
		$meta_value = '';
		$trigger_ids = get_posts( array (
			'post_type' 	=> 'wa_notifier_trigger',
			'post_status' 	=> 'publish',
			'numberposts' 	=> -1,
			'fields' 		=> 'ids',
			'meta_query' => array(
				'relation'	=> 'OR',
				array(
					'key' => NOTIFIER_PREFIX . 'trigger',
					'value' => $trigger,
					'compare' => '='
				),
				array( // Backward compatibility
					'key' => NOTIFIER_PREFIX . 'trigger',
					'value' => self::get_trigger_id_without_site_key($trigger),
					'compare' => '='
				)
			),
		) );

		if(!empty($trigger_ids)){
			$meta_value = get_post_meta($trigger_ids[0], $meta_key, true);
		}

		return $meta_value;
	}

	/**
	 * Send triggered notifications
	 */
	public static function send_trigger_request($trigger, $context_args) {
		if(empty($context_args)){
			return false;
		}

		if('yes' === get_option('notifier_enable_async_triggers')){
			$option_name = 'notifier_'.notifier_generate_random_key(10);
			update_option( $option_name, $context_args );

			as_enqueue_async_action(
				'notifier_send_trigger_request',
				array('trigger' => $trigger, 'option_name' => $option_name ),
				'notifier'
			);
		}else {
			self::notifier_send_trigger_request($trigger, $context_args);
		}
	}

	/**
	 * Send scheduled trigger request

	 */
	public static function send_scheduled_trigger_request($trigger, $option_name) {
		if (is_array($option_name)){ // For backward compatibilty before 2.4.0
			$context_args = $option_name;
		}
		else {
			$context_args = get_option($option_name);
		}

		self::notifier_send_trigger_request($trigger, $context_args);
	}

	/**
	 * Send async trigger request
	 */
	public static function notifier_send_trigger_request($trigger, $context_args){
		$trigger_old = $trigger;
		$trigger = self::get_trigger_id_with_site_key($trigger);

		$merge_tags = self::get_trigger_post_meta($trigger, NOTIFIER_PREFIX . 'data_fields');
		$recipient_fields = self::get_trigger_post_meta($trigger, NOTIFIER_PREFIX . 'recipient_fields');

		$data = array();
		$recipient_data = array();
		Notifier_Tools::insert_activity_log('debug','Triggering '. $trigger.' with arguments: '.json_encode($context_args));

		if(!empty($merge_tags)){
			foreach($merge_tags as $tag){
				$data[$tag] = Notifier_Notification_Merge_Tags::get_trigger_merge_tag_value($tag, $context_args);
			}
		}

		if(!empty($recipient_fields)){
			foreach($recipient_fields as $field){
				$recipient_data[$field] = Notifier_Notification_Merge_Tags::get_trigger_recipient_field_value($field, $context_args);
			}
		}

		$params = array(
			'site_url'			=> site_url(),
			'source'			=> 'wp',
			'trigger'			=> $trigger,
			'trigger_old'		=> $trigger_old,
			'merge_tags_data'	=> $data,
			'recipient_fields'	=> $recipient_data
    	);

		Notifier_Tools::insert_activity_log('debug','Sending API request for ' . $trigger . '. Request params: '.json_encode($params));		
		$response = Notifier::send_api_request( 'fire_notification', $params, 'POST' );
		Notifier_Tools::insert_activity_log('debug','API response for '. $trigger . ': ' . $response->data);

		if($response->error){
			error_log($response->message);
			Notifier_Tools::insert_activity_log('debug', 'API response for '. $trigger . ': ' . $response->message);
		}
	}

	/**
	 * Enable / disable trigger
	 */
	public static function notifier_change_trigger_status(){
		$post_id = isset($_POST['post_id']) ? $_POST['post_id'] : 0;
		$enabled = isset($_POST['enabled']) ? $_POST['enabled'] : 'no';
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
		$post_id =  isset($_POST['post_id']) ? $_POST['post_id'] : 0;
		$trigger =  isset($_POST['trigger']) ? $_POST['trigger'] : '';

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
