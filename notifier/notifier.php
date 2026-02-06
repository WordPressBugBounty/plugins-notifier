<?php
/**
 * Plugin Name: Send Notifications from Woocommerce, Form Plugins and More!
 * Plugin URI: https://wordpress.org/plugins/notifier/
 * Description: WhatsApp API integration to send WhatsApp notifications from Woocommerce, Contact Form 7, Gravity Forms, WPForms & more.
 * Version: 2.7.12
 * Author: WANotifier
 * Author URI: https://wanotifier.com
 * Text Domain: notifier
 * Requires at least: 5.7
 * Requires PHP: 7.4
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Define constants
 */
if ( ! defined( 'NOTIFIER_FILE' ) ) {
	define( 'NOTIFIER_FILE', __FILE__ );
}

if ( ! defined( 'NOTIFIER_PATH' ) ) {
    define('NOTIFIER_PATH', plugin_dir_path( __FILE__ ));
}

/**
 * Load the core plugin file.
 */
require NOTIFIER_PATH . 'includes/class-notifier.php';

/**
 * Begin execution of the plugin.
 *
 * @since   0.1
 */
function run_notifier() {
	$plugin = Notifier::get_instance();
}
run_notifier();
