<?php
/**
 * Admin View: Tools
 *
 * @package Notifier
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$dates = Notifier_Tools::get_logs_date_lists();
?>
<div class="wrap notifier">
    <div class="notifier-wrapper">
		<!--Woocommerce export customer -->
			<?php if (class_exists('WooCommerce') && in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) { ?>
				<form method="POST" id="notifier_tools_form" class="notifier-tools-form" action="" enctype="multipart/form-data">
					<div class="notifier-tool-wrap">
						<div class="notifier-tool-details">
							<h3 class="notifier-tool-name">Export WooCommerce Customers</h3>
							<p class="notifier-tool-description">Export all your WooCommerce customers in CSV format to import them in WANotifier.</p>
						</div>
						<div class="notifier-tool-action">
							<input name="export_customer" type="submit" class="button button-large" value="Export">
							<?php wp_nonce_field( NOTIFIER_NAME . '-tools-export-customers' ); ?>
						</div>
					</div>
				</form>
			<?php } else { ?>
				<div class="notice notice-error is-dismissible">
					<p>WooCommerce plugin is not installed or is not active. Please install or activate WooCommerce to export customers.</p>
				</div>
			<?php } ?>
		<!--End of Woocommerce export customer -->

		<!--Fetch activity log and show from date-->
			<?php if ('yes' === get_option('notifier_enable_activity_log')){ ?>
				<div class="notifier-tool-activity-wrap">
					<div class="notifier-tool-inner-wrap">
						<div class="notifier-tool-details">
							<h3 class="notifier-tool-name">Activity Logs</h3>
							<p class="notifier-tool-description">View activity logs for your triggers to debug errors / troubleshoot triggers.</p>
						</div>
						<div class="notifier-tool-action">
							<select name="notifier_activity_date" id="notifier_activity_date">
								<option value="">Select date</option>
								<?php foreach ($dates as $date){ ?>
									<option value="<?php echo esc_attr($date); ?>"><?php echo esc_html($date); ?></option>
								<?php } ?>
							</select>
						</div>
					</div>
					<div class="activity-log-preview-wrap"><?php if (empty($dates)){ echo "No logs found...";} ?></div>
				</div>
			<?php } ?>
		<!--End of Fetch activity log and show from date -->
    </div>
</div>
