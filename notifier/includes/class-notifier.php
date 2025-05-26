<?php
/**
 * The core plugin class.
 *
 * @package    Notifier
 */
class Notifier {
	/**
	 * The single instance of the class.
	 */
	protected static $_instance = null;

	/**
	 * Notifier Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Define Constants.
	 */
	private function define_constants() {
		$this->define( 'NOTIFIER_VERSION', '2.7.6' );
		$this->define( 'NOTIFIER_NAME', 'notifier' );
		$this->define( 'NOTIFIER_PREFIX', 'notifier_' );
		$this->define( 'NOTIFIER_URL', trailingslashit( plugins_url( '', dirname(__FILE__) ) ) );
		$this->define( 'NOTIFIER_APP_API_URL', 'https://app.wanotifier.com/api/v1/' );
		$this->define( 'NOTIFIER_ACTIVITY_TABLE_NAME', 'notifier_activity_log' );
		$this->define( 'NOTIFIER_CART_ABANDONMENT_TABLE', 'notifier_cart_abandonment' );
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param string      $name  Constant name.
	 * @param string|bool $value Constant value.
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * Main Notifier Instance.
	 *
	 * @return Notifier instance.
	 */
	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 */
	private function includes() {
		// Functions
		require_once NOTIFIER_PATH . 'includes/functions/functions-notifier-helpers.php';
		require_once NOTIFIER_PATH . 'includes/functions/functions-notifier-meta-box-fields.php';
		require_once NOTIFIER_PATH . 'libraries/action-scheduler/action-scheduler.php';

		// Classes
		require_once NOTIFIER_PATH . 'includes/classes/class-notifier-admin-notices.php';
		require_once NOTIFIER_PATH . 'includes/classes/class-notifier-dashboard.php';
		require_once NOTIFIER_PATH . 'includes/classes/class-notifier-notification-merge-tags.php';
		require_once NOTIFIER_PATH . 'includes/classes/class-notifier-notification-triggers.php';
		require_once NOTIFIER_PATH . 'includes/classes/class-notifier-settings.php';
		require_once NOTIFIER_PATH . 'includes/classes/class-notifier-frontend.php';
		require_once NOTIFIER_PATH . 'includes/classes/class-notifier-tools.php';
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		register_activation_hook ( NOTIFIER_FILE, array( $this, 'activate') );
		register_deactivation_hook ( NOTIFIER_FILE, array( $this, 'deactivate') );

		add_action( 'after_setup_theme', array( 'Notifier_Admin_Notices', 'init' ) );
		add_action( 'after_setup_theme', array( 'Notifier_Dashboard', 'init' ) );
		add_action( 'after_setup_theme', array( 'Notifier_Notification_Merge_Tags', 'init' ) );
		add_action( 'after_setup_theme', array( 'Notifier_Notification_Triggers', 'init' ) );
		add_action( 'after_setup_theme', array( 'Notifier_Settings', 'init' ) );
		add_action( 'after_setup_theme', array( 'Notifier_Frontend', 'init' ) );
		add_action( 'admin_enqueue_scripts', array( $this , 'admin_scripts') );
		add_action( 'wp_enqueue_scripts', array( $this , 'frontend_scripts') );
		add_action( 'in_admin_header', array( $this , 'embed_page_header' ) );

		add_action( 'after_setup_theme', array( $this, 'maybe_include_integrations' ) );
		add_action( 'after_setup_theme', array( 'Notifier_Tools', 'init' ) );

		add_action( 'wp_loaded', array( $this, 'setup_activity_log' ) );
		add_action( 'notifier_clean_old_logs', array( $this, 'notifier_delete_old_activity_logs' ) );
		
		add_action( 'wp_loaded', array( $this, 'create_cart_abandonment_table' ) );
	}

	/**
	 * Setup during plugin activation
	 */
	public function activate() {
		$notifier_plugin_data = get_option('notifier_meta', array());
		if (isset($notifier_plugin_data['as_clear_log']) && $notifier_plugin_data['as_clear_log'] === 'yes') {
			unset($notifier_plugin_data['as_clear_log']);
			update_option('notifier_meta', $notifier_plugin_data);
		}
	}

	/**
	 * Setup during plugin deactivation
	 */
	public function deactivate() {
		as_unschedule_action('notifier_check_cart_abandonment');
		as_unschedule_action('notifier_clean_old_logs');
		$notifier_plugin_data = get_option('notifier_meta', array());
		$notifier_plugin_data['as_clear_log'] = 'yes';
		update_option('notifier_meta', $notifier_plugin_data);
	}

    /**
     * Check if the plugin was updated and run the upgrade routine if necessary.
     */
	public function setup_activity_log() {
		$notifier_plugin_data = get_option('notifier_meta', array());
		if (!isset($notifier_plugin_data['activity_log_table']) && $notifier_plugin_data['activity_log_table'] !== 'yes') {
			self::create_notifier_activity_log_table();
		}

		$args = array();
		$as_clear_log = isset($notifier_plugin_data['as_clear_log']) ? $notifier_plugin_data['as_clear_log'] : 'no';
		if ($as_clear_log !== 'yes') {
			if (false === as_next_scheduled_action('notifier_clean_old_logs')) {
				as_schedule_recurring_action(time(), DAY_IN_SECONDS, 'notifier_clean_old_logs', $args);
			}
		}
	}

	/**
	 * Check if plugin's page
	 */
	public static function is_notifier_page() {
		$current_screen = get_current_screen();
		if ( strpos($current_screen->id, NOTIFIER_NAME) !== false) {
			return true;
		}

		return false;
	}

	/**
	 * Add admin scripts and styles
	 */
	public function admin_scripts () {
		if (!self::is_notifier_page()) {
			return;
		}

    	// Select2
    	wp_enqueue_script(
    		NOTIFIER_NAME . '-select2-js',
    		NOTIFIER_URL . 'assets/js/select2.min.js',
    		array('jquery'),
    		NOTIFIER_VERSION,
    		true
    	);

    	// Admin JS file
    	wp_enqueue_script(
    		NOTIFIER_NAME . '-admin-js',
    		NOTIFIER_URL . 'assets/js/admin.js',
    		array('jquery'),
    		NOTIFIER_VERSION,
    		true
    	);
    	wp_localize_script(
    		NOTIFIER_NAME . '-admin-js',
    		'notifierObj',
    		apply_filters( 'notifier_js_variables', array('ajaxurl' => admin_url( 'admin-ajax.php' ) ) )
    	);

    	// Styles
	    wp_enqueue_style(
	    	NOTIFIER_NAME . '-admin-css',
	    	NOTIFIER_URL . 'assets/css/admin.css',
	    	null,
	    	NOTIFIER_VERSION
	    );
	}

	/**
	 * Add frontend scripts and styles
	 */
	public function frontend_scripts () {
		if('yes' === get_option('notifier_ctc_enable')){
	    	// Styles
		    wp_enqueue_style(
		    	NOTIFIER_NAME . '-frontend-css',
		    	NOTIFIER_URL . 'assets/css/frontend.css',
		    	null,
		    	NOTIFIER_VERSION
		    );
		}

    	if ('yes' === get_option('notifier_enable_abandonment_cart_tracking', 'no') && class_exists('WooCommerce') && is_checkout() ) {
	    	wp_enqueue_script(
	    		NOTIFIER_NAME . '-js',
	    		NOTIFIER_URL . 'assets/js/script.js',
	    		array('jquery'),
	    		NOTIFIER_VERSION,
	    		true
	    	);

	    	wp_localize_script(
	    		NOTIFIER_NAME . '-js',
	    		'notifierFrontObj',
	    		array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'security' => wp_create_nonce('notifier_save_cart_abandonment_data'),
				)
	    	);
	    }
	}

	/**
	 * Set up a div for the header embed to render into.
	 * The initial contents here are meant as a place loader for when the PHP page initialy loads.
	 */
	public static function embed_page_header() {
		if (!self::is_notifier_page()) {
			return;
		}
		$current_screen = get_current_screen();
		$cpt = ( '' !== $current_screen->post_type) ? $current_screen->post_type : '';
		$tax = ( '' !== $current_screen->taxonomy) ? $current_screen->taxonomy : '';
		?>
		<div id="notifier-admin-header" data-post-type="<?php echo esc_attr($cpt); ?>">
			<div class="notifier-admin-header-content">
				<div class="header-page-title w-30 d-flex align-content-center">
					<a class="header-logo" href="admin.php?page=notifier"><img src="<?php echo NOTIFIER_URL; ?>assets/images/favicon.svg"></a>
					<h2><?php echo esc_attr(get_admin_page_title()); ?></h2>
				</div>
				<div class="header-action-links w-30 d-flex justify-content-end">
					<span class="review-us-link">Review us: <a href="https://wordpress.org/support/plugin/notifier/reviews/#new-post" target="_blank">⭐⭐⭐⭐⭐</a></span>
					<span class="header-version">Version: <?php echo esc_html(NOTIFIER_VERSION); ?></span>
					<a href="https://wanotifier.com/support/" target="_blank">Help</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * For sending API requests
	 */
	public static function send_api_request ( $endpoint, $args, $method = 'POST', $headers = array() ) {
		$api_key = get_option('notifier_api_key');
		$api_key = trim($api_key);
		if('' == $api_key) {
			return false;
		}

		if(empty($headers)){
			$headers = array(
				'Content-Type' => 'application/json; charset=utf-8'
			);
		}

		$request_url = NOTIFIER_APP_API_URL . $endpoint . '?key=' . esc_attr($api_key);
		$request_args = array(
		    'method' 	=> $method,
		    'headers' 	=> $headers,
		    'timeout'   => 120,
		    'body' 		=> json_encode($args),
		    'sslverify'	=> false
	    );

		$response = wp_remote_request( $request_url, $request_args );

		$response_body = wp_remote_retrieve_body( $response );

		if ( is_wp_error( $response ) ) {
			error_log( $response->get_error_message() );
			return false;
		} else {
			$response_body = wp_remote_retrieve_body( $response );
			return json_decode($response_body);
		}
	}

	/**
	 * Load Woocommerce class if it's present is activated
	 */
	public static function maybe_include_integrations () {
		// Woocommerce
		if ( class_exists( 'WooCommerce' ) ) {
			require_once NOTIFIER_PATH . 'includes/classes/integrations/class-notifier-woocommerce.php';
			Notifier_Woocommerce::init();
		}
		if ( ! class_exists( 'WC_Session' ) ) {
		    include_once( WP_PLUGIN_DIR . '/woocommerce/includes/abstracts/abstract-wc-session.php' );
		}

		// WooCommerce Cart Abandonment Recovery by CartFlows
		if ( class_exists( 'CARTFLOWS_CA_Loader' ) && defined('CARTFLOWS_CA_VER') && version_compare(CARTFLOWS_CA_VER, '1.2.25', '>=')) {
			require_once NOTIFIER_PATH . 'includes/classes/integrations/class-notifier-wcar.php';
			Notifier_WCAR::init();
		}

		// Gravity Forms
		if ( class_exists( 'GFCommon' ) ) {
			require_once NOTIFIER_PATH . 'includes/classes/integrations/class-notifier-gravityforms.php';
			Notifier_GravityForms::init();
		}

		// Contact Form 7
		if ( class_exists( 'WPCF7' ) ) {
			require_once NOTIFIER_PATH . 'includes/classes/integrations/class-notifier-cf7.php';
			Notifier_ContactForm7::init();
		}

		// WPForms
		if ( class_exists( 'WPForms' ) ) {
			require_once NOTIFIER_PATH . 'includes/classes/integrations/class-notifier-wpforms.php';
			Notifier_WPForms::init();
		}

		// Ninja Forms
		if ( class_exists( 'Ninja_Forms' ) ) {
			require_once NOTIFIER_PATH . 'includes/classes/integrations/class-notifier-ninjaforms.php';
			Notifier_NinjaForms::init();
		}

		//FrmForm
		if ( class_exists( 'FrmForm' ) ) {
			require_once NOTIFIER_PATH . 'includes/classes/integrations/class-notifier-formidable.php';
			Notifier_Formidable::init();
		}

		//WPFluentForm
		if ( function_exists( 'fluentFormApi' ) ) {
			require_once NOTIFIER_PATH . 'includes/classes/integrations/class-notifier-fluentforms.php';
			Notifier_FluentForms::init();
		}
	}

	/**
	 * Is connection to WANotifier.com active?
	 */
	public static function is_api_active () {
		$activated = get_option(NOTIFIER_PREFIX . 'api_activated');
		if('yes' == $activated) {
			return true;
		}
		else{
			return false;
		}
	}

	/**
     * Create New db table-- wanotifier_activity_log
     */
	public static function create_notifier_activity_log_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . NOTIFIER_ACTIVITY_TABLE_NAME;
	
		$charset_collate = $wpdb->get_charset_collate();
	
		// Include IF NOT EXISTS clause in the CREATE TABLE query
		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			log_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			timestamp timestamp NOT NULL,
			message text NOT NULL,
			type varchar(16) NOT NULL,
			PRIMARY KEY  (log_id),
			INDEX idx_timestamp (timestamp)
		) $charset_collate;";
	
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		$notifier_plugin_data = get_option('notifier_meta', array());
		$notifier_plugin_data['activity_log_table'] = 'yes';
		update_option('notifier_meta', $notifier_plugin_data);		
	}

	/**
     * Delete old log from activity table according to interval
     */
	public function notifier_delete_old_activity_logs() {
		global $wpdb;
		$table_name = $wpdb->prefix . NOTIFIER_ACTIVITY_TABLE_NAME;
		$retention_time = apply_filters( 'notifier_logs_retention_time', 7 );
		$retention_time = intval($retention_time);
		$wpdb->query( $wpdb->prepare( "DELETE FROM `$table_name` WHERE timestamp <= DATE_SUB(NOW(), INTERVAL %d DAY)", $retention_time ) );
	}

	/**
     * Create New db table-- notifier_cart_abandonment
     */
	public static function create_cart_abandonment_table() {
		$notifier_plugin_data = get_option('notifier_meta', array());
		if (!isset($notifier_plugin_data['cart_abandonment_table']) && $notifier_plugin_data['cart_abandonment_table'] !== 'yes') {
			global $wpdb;
			$table_name = $wpdb->prefix . NOTIFIER_CART_ABANDONMENT_TABLE;
			$charset_collate = $wpdb->get_charset_collate();
		
			// Updated CREATE TABLE query for notifier_cart_abandonment table
			$sql = "CREATE TABLE IF NOT EXISTS $table_name (
				session_id BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
				session_key VARCHAR(16) NULL,
				phone_number VARCHAR(15) NULL,
				cart_data LONGTEXT NULL,
				cart_total DECIMAL(10,2) NULL,
				coupon_data LONGTEXT NULL,
				customer_data LONGTEXT NULL,
				order_id BIGINT(11) DEFAULT 0,
				triggered TINYINT(1) DEFAULT 0,
				created_time DATETIME NULL,
				updated_time DATETIME NULL,
				KEY session_key (session_key) -- Index for session_key
			) $charset_collate;";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );

			$notifier_plugin_data['cart_abandonment_table'] = 'yes';
			update_option('notifier_meta', $notifier_plugin_data);				
		}
	}
}
