<?php
/**
 * Admin View: React Application Shell
 *
 * Single mount point for the React SPA. Used by all admin pages
 * (Integrations, Chat Button, Settings, Logs, Migration).
 *
 * @package Notifier
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_page      = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : NOTIFIER_NAME;
$logs_enabled      = ( 'yes' === get_option( 'notifier_enable_activity_log', 'no' ) );
$migration_pending = Notifier_Migration::has_legacy_triggers() && ! Notifier_Migration::is_migration_complete();
?>
<div class="wrap notifier">
    <div id="notifier-admin-root"
         data-page="<?php echo esc_attr( $current_page ); ?>"
         data-rest-url="<?php echo esc_url( rest_url( 'notifier/v1/' ) ); ?>"
         data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
         data-logs-enabled="<?php echo $logs_enabled ? '1' : '0'; ?>"
         data-migration-pending="<?php echo $migration_pending ? '1' : '0'; ?>"
         data-plugin-url="<?php echo esc_url( NOTIFIER_URL ); ?>"
         data-plugin-version="<?php echo esc_attr( NOTIFIER_VERSION ); ?>"
         data-site-url="<?php echo esc_attr( parse_url( home_url(), PHP_URL_HOST ) ); ?>"
    ></div>
</div>
