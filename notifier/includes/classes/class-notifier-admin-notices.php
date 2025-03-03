<?php
/**
 * Admin notices class
 *
 * @package    Wa_Notifier
 */
class Notifier_Admin_Notices {

	public $notices = array();

	/**
	 * Notifier Constructor.
	 */
	public function __construct( $notices, $transient = false, $user_id = 0 ) {
		if ( $transient ) {
            if(0 == $user_id){
                $user_id = get_current_user_id();
            }
			set_transient( 'notifier_notice_' . $user_id, $notices, 60 );
		} else {
			$this->notices = $notices;
			add_action('admin_notices', array( $this, 'show_normal_notices'));
		}
	}

	/**
	 * Init
	 */
	public static function init() {
		if(!is_admin()){
			return;
		}
		$user_id = get_current_user_id();
		$transient_notices = get_transient( 'notifier_notice_' . $user_id );
		if ( isset($transient_notices) ) {
			add_action('admin_notices', array( __CLASS__, 'show_transient_notices'));
		}
	}

	/**
	 * Show normal notices
	 */
	public function show_normal_notices() {
		if ( empty($this->notices) ) {
			return;
		}
		$notices = $this->notices;
		self::show_notices($notices);
	}

	/**
	 * Show transient notices
	 */
	public static function show_transient_notices() {
		$user_id = get_current_user_id();
		$transient_notices = get_transient( 'notifier_notice_' . $user_id );
		if ( $transient_notices ) {
			$notices = $transient_notices;
			delete_transient( 'notifier_notice_' . $user_id );
			self::show_notices($notices);
		}
	}

	/**
	 * Show notices
	 */
	public static function show_notices( $notices ) {
		if ( empty($notices) ) {
			return;
		}

		foreach ( $notices as $notice ) {
			?>
				<div class="notifier-notice notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
					<p><?php echo wp_kses_post($notice['message']); ?></p>
				</div>
			<?php
		}
	}

}
