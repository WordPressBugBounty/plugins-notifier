<?php
/**
 * Tools page class
 *
 * @package    Wa_Notifier
 */
class Notifier_Tools {

	/**
	 * Init
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__ , 'setup_admin_page') );
        add_action( 'admin_init', array( __CLASS__ , 'export_customers') );
        add_action( 'wp_ajax_fetch_activity_logs_by_date', array(__CLASS__, 'fetch_activity_logs_by_date'));
	}

	/**
	 * Add settings page to men
	 */
	public static function setup_admin_page () {
		if (!Notifier::is_api_active()){
			return;
		}

		add_submenu_page( NOTIFIER_NAME, 'Tools', 'Tools', 'manage_options', NOTIFIER_NAME . '-tools', array( __CLASS__, 'output' ) );
	}

	/**
	 * Check if on tools page
	 */
	public static function is_tools_page() {
		$current_page = isset($_GET['page']) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		return strpos($current_page, NOTIFIER_NAME . '-tools') !== false;
	}

	/**
	 * Output
	 */
	public static function output() {
        include_once NOTIFIER_PATH . '/views/admin-tools.php';
	}

    /**
     * Export WooCommerce Customers
     */
    public static function export_customers() {
        if ( ! self::is_tools_page() ) {
            return;
        }

        if ( ! class_exists('WooCommerce') || ! in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) ) {
            return '<div class="notice notice-error is-dismissible"><p>WooCommerce plugin is not installed or is not active. Please install or activate WooCommerce to export customers.</p></div>';
        }
        
        if ( ! isset( $_POST['export_customer'] ) ) {
            return;
        }
  
        //phpcs:ignore
        if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], NOTIFIER_NAME . '-tools-export-customers' ) ) {
            return;
        }
        
        // Get the current domain name
        $domain_name = $_SERVER['HTTP_HOST'];
    
        // Generate a unique filename with a timestamp
        $filename = $domain_name . '-woocommerce-customers-' . current_time( 'timestamp' ) . '.csv';
    
        // Set the appropriate headers for a CSV file download
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
    
        // Open a file handle for writing
        $file_handle = fopen( 'php://output', 'w' );
        
        global $wpdb;

        // Query WooCommerce customers
        $customers = $wpdb->get_results("
            SELECT u.ID, u.user_email
            FROM {$wpdb->users} u
            WHERE EXISTS (
                SELECT 1
                FROM {$wpdb->usermeta} um
                WHERE u.ID = um.user_id
                AND um.meta_key = 'wp_capabilities'
            )
            ORDER BY u.ID ASC
        ");

        // Prepare CSV data
        $csv_data = array();
        $csv_data[] = array(
            'First Name',
            'Last Name',
            'WhatsApp Number',
            'Status',
            'List Name',
            'Tags',
            'billing_address_1',
            'billing_address_2',
            'billing_city',
            'billing_state',
            'billing_country',
        );
    
        foreach ( $customers as $customer ) {
            $billing_phone = get_user_meta( $customer->ID, 'billing_phone', true );
            if(!$billing_phone || trim($billing_phone) == ''){
            	continue;
            }

			$country_code = get_user_meta( $customer->ID, 'billing_country', true );
            $state_code = get_user_meta( $customer->ID, 'billing_state', true );

			$country = '';
			$state = '';

            if($country_code && $country_code != ''){
				$country = WC()->countries->get_countries()[$country_code];
				if($state_code && $state_code != ''){
					$state = WC()->countries->get_states($country_code)[$state_code];
				}
            }

            $csv_data[] = array(
                get_user_meta( $customer->ID, 'billing_first_name', true ),
                get_user_meta( $customer->ID, 'billing_last_name', true ),
                str_replace('+','',Notifier_Woocommerce::get_formatted_phone_number($billing_phone, $country_code)),
                'subscribed',
                'WooCommerce Customers',
                '',
                get_user_meta( $customer->ID, 'billing_address_1', true ) ?: '',
                get_user_meta( $customer->ID, 'billing_address_2', true ) ?: '',
                get_user_meta( $customer->ID, 'billing_city', true ) ?: '',
                $state,
                $country,
            );
        }
    
        // Generate CSV file
        foreach ( $csv_data as $row ) {
            fputcsv( $file_handle, $row );
        }
        fclose( $file_handle );
        exit();
    }
   
    /**
     * Insert New log into table wanotifier_activity_log
     */    
    public static function insert_activity_log( $type = 'debug', $message = '' ) {
        if ('yes' === get_option('notifier_enable_activity_log')) {
            global $wpdb;
            $table_name = $wpdb->prefix . NOTIFIER_ACTIVITY_TABLE_NAME;
            $timestamp = current_time('mysql');
    
            $data = array(
                'timestamp' => $timestamp,
                'message' => $message,
                'type' => $type,
            );
        
            $format = array('%s', '%s', '%s');
            $wpdb->insert($table_name, $data, $format);
        }
    }

    /**
     * Fetch all dates info for current user
     */
    public static function get_logs_date_lists() {
        global $wpdb;
        $table_name = $wpdb->prefix . NOTIFIER_ACTIVITY_TABLE_NAME;
        $dates = $wpdb->get_results("SELECT DISTINCT DATE(timestamp) as log_date FROM `$table_name` ORDER BY timestamp DESC");

        $final_dates = [];
        foreach ($dates as $date) {
            $final_dates[] = notifier_convert_date_from_utc($date->log_date,'Y-m-d');
        }

        return $final_dates;
    }

    /**
     * Fetch all activity info for current user by date
     */
    public static function fetch_activity_logs_by_date() {
        // Security check: Verify user has manage_options capability
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => 'You do not have permission to perform this action.'
            ) );
        }

        // Verify nonce for additional security
        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'notifier_fetch_activity_logs' ) ) {
            wp_send_json_error( array(
                'message' => 'Security check failed.'
            ) );
        }

        $activity_date = isset($_POST['notifier_activity_date']) ? sanitize_text_field($_POST['notifier_activity_date']) : '';

        global $wpdb;
        $table_name = $wpdb->prefix . NOTIFIER_ACTIVITY_TABLE_NAME;

        $logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM `$table_name` WHERE DATE(timestamp) = %s ORDER BY timestamp DESC",
            $activity_date
        ));

        $logs_preview_htm = '<div class="activity-logs">';
        $logs_preview_htm .= '<table>';
        $logs_preview_htm .= '<tbody>';
        if (!empty($logs)){
            foreach ($logs as $log){
                $logs_preview_htm .= '<tr class="activity-record">';
                $logs_preview_htm .= '<td><strong>'.esc_html($log->timestamp).'</strong> </td>'; 
                $logs_preview_htm .= '<td>'.esc_html($log->message).'</td>';
                $logs_preview_htm .= '</tr>';
            }
        } else {
            $logs_preview_htm .= '<tr class="no-records-found"> <td colspan="2">No logs found...</td></tr>';
        }
        $logs_preview_htm .= '</tbody>';
        $logs_preview_htm .= '</table>';
        $logs_preview_htm .= '</div>';

		wp_send_json( array(
			'preview'  => $logs_preview_htm
		) );

    }
    
}
