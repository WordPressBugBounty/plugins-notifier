<?php
/**
 * Admin View: Dashboard
 *
 * @package Notifier
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$api_key = get_option(NOTIFIER_PREFIX . 'api_key');
?>
<div class="wrap notifier">
	<div class="notifier-wrapper">
		<?php if (!Notifier::is_api_active()) : ?>
			<div class="dashboard-boxes">
				<div class="col w-70">
					<div class="dashboard-box">
						<div class="dashboard-box-head">
							<h3>Let's get started 🚀</h3>
						</div>
						<div class="dashboard-box-body">
							<p><a href="https://wanotifier.com/" target="_blank">WANotifier</a> is a free SaaS tool that allows you to send bulk WhatsApp marketing and transactional messages to your customers using the <b>WhatsApp's official Cloud API</b>.</p>
							<p>This plugin is an <b>extension to our SaaS platform</b> that allows you to <b>trigger WhatsApp message notifications</b> from your WordPress website. To use this plugin you'll need to <b>create an account with us</b> and connect it with this plugin.</p>
							<p>Following are the instructions to do the setup.</p>
							<ol>
								<li>Create a <b>FREE</b> <a href="https://app.wanotifier.com/create-account/" target="_blank">WANotifier</a> account.</li>
								<li>Go through the on-boarding steps to setup your phone number with our tool and <b>WhatsApp API</b>.</li>
								<li>After setup is done, you'll land on the <b>Dashboard</b> page. From there, go to the <a href="https://app.wanotifier.com/settings/api/" target="_blank">Settings > API</a> page and scroll to the bottom to get your <b>WANotifier.com API Key</b>.</li>
								<li>Copy and paste that key in the text box below and click on <b>Save and Validate</b> to continue.</li>
							</ol>
							<hr>
							<p><b>Need help?</b> Just <a href="https://wanotifier.com/support/" target="_blank">get in touch</a> with us and we'll help you setup your account and this plugin for FREE.</p>
							<hr style="margin-bottom: 20px;">
							<form method="POST" action="" enctype="multipart/form-data">
								<input type="text" name="notifier_api_key" id="wa-notifier-api-key" placeholder="Enter your API key here" value="<?php echo esc_attr($api_key); ?>" />
								<button name="webhook_validation" class="button-primary" type="submit" value="">Submit and validate</button>
		            			<?php wp_nonce_field( NOTIFIER_NAME . '-webhook-validation' ); ?>
							</form>
						</div>
					</div>
				</div>
				<div class="col w-30">
					<div class="dashboard-box">
						<div class="dashboard-box-head">
							<h3>Customer testimonials ❤️</h3>
						</div>
						<div class="dashboard-box-body">
							⭐⭐⭐⭐⭐
							<em><p>I have been <strong>using WANotifier for the past 1 month</strong> for my eCommerce store. I am able to add/import/manage contacts, create message templates, segment users, connect with third party APIs, reply to messages all within the same dashboard. The UI is <strong>simple and friendly</strong>. The instructions are clear. <strong>Definitely recommended</strong>!</p></em>
							<p><strong>Gopi Kanna</strong><br>
							Co-founder, DomainCoasters.com</p>
							<hr>
							⭐⭐⭐⭐⭐
							<em><p>We have been using WANotifier for past two months. <strong>Nice product</strong>! A quick and automated response brings in a feeling of completeness to online purchase experience for our customers. We have processed multiple orders after installing this utility and <strong>it just works perfectly every time</strong>.</p></em>
							<p><strong>Avinash Pendse</strong>
							<br>Founder, GrahakShahi.com</p>
							<hr>
							⭐⭐⭐⭐⭐
							<em><p>Very good plugin for <b>WhatsApp Woocommerce integration for free</b>. Easily work without any issues. Simple to use.Tutorials also found on YouTube. Thanks to the WANotifier team.</p></em>
							<strong>hashimsamnan</strong>
							<br>Woocommerce user
						</div>
					</div>
				</div>
			</div>
		<?php
			else :
				$available_triggers = Notifier_Notification_Triggers::get_notification_triggers();
				$enabled_triggers = get_option('notifier_enabled_triggers');
				$enabled_triggers = (!empty($enabled_triggers)) ? $enabled_triggers : array();
		?>
			<div class="dashboard-boxes">
				<div class="col w-70">
					<div class="dashboard-box">
						<div class="dashboard-box-head">
							<h3>How to use this plugin? 🤔</h3>
						</div>
						<div class="dashboard-box-body how-to">
							<img class="notifier-overview-image" src="<?php echo NOTIFIER_URL; ?>assets/images/notifier-plugin-overview.svg">
							<p>Follow the instructions below to setup your <b>Triggers</b> that'll fire WhatsApp notification messages:</p>
							<p><b>STEP 1 - Create a Trigger on this site</b></p>
							<ul>
								<li><a href="<?php echo admin_url('post-new.php?post_type=wa_notifier_trigger'); ?>" target="_blank">Click here</a> to add a new Trigger.</li>
								<li>Select a <b>Trigger</b> from the dropdown when you want the notification to be fired.</li>
								<li>Enable the <b>Data fields</b> and <b>Recipient fields</b> that you want to send to WANotifier when notification is fired.</li>
								<li>Then save this trigger and <b>Enable</b> it.</li>
							</ul>
							<p><b>STEP 2 - Create a Message Template on WANotifier</b></p>
							<ul>
								<li>Now go to the WANotifier portal and <a href="https://app.wanotifier.com/templates/add/" target="_blank">create a new Message Template</a>. </li>
								<li>You can either keep the template simple or add variable placeholders like <code>{{1}}</code>, <code>{{2}}</code> and so on. We will map these variables with <b>Data fields</b> in the next step below.</li>
							</ul>
							<p><b>STEP 3 - Create a Notification on WANotifier</b></p>
							<ul>
								<li>Now <a href="https://app.wanotifier.com/notifications/add/" target="_blank">create a new Notification</a> and select <b>Notification Type</b> as <b>Transactional</b>.</li>
								<li>Then from the <b>Trigger</b> dropdown, select the trigger that you created in Step 1.</li>
								<li>Add <b>Recipients</b> to whom you want to send this notification. Select the <b>Recipient fields</b> that you enabled above or select custom contacts.</li>
								<li>Select the <b>Message Template</b> you created in Step 2.</li>
								<li>If you created this message template with variables (<code>{{1}}</code>, <code>{{2}}</code> and so on), you will see option to map these variables with the <b>Data fields</b> you enabled during Step 1.</li>
								<li>Click on the <b>Save</b> button to save this notification.</li>
							</ul>
							<p>That's it. Your notification will be sent each time it gets triggered from this website!</p>
							<p><em><b>Disclaimer:</b> By enabling the triggers you accept and agree that the associated data and recipient fields will be sent to our server for use with the message template when sending notifications. We do not store any data other than what is required for sending a notification.</em></p>
						</div>
					</div>
				</div>
				<div class="col w-30">
					<div class="dashboard-box">
						<div class="dashboard-box-head">
							<h3>Want more control? 💪</h3>
						</div>
						<div class="dashboard-box-body">
							<p>This plugin provides you with simple triggers with a basic set of data fields to trigger notifications. If want more control over triggering your notifications, we suggest using <b>Webhooks</b> instead.</p>
							<p>With webhooks you can <b>add Contacts</b> and <b>trigger Notifications</b> from WordPress using action hooks or using 3rd party sites like Zapier, Pabbly, IFTTT etc!</p>
							<p><a href="https://app.wanotifier.com/contacts/import/" target="_blank">Click here</a> to view the webhook URL to <b>add contacts</b>. To trigger notification using webhook, <a href="https://app.wanotifier.com/notifications/add/" target="_blank">create a new Notification</a> and select <b>Trigger</b> option as <b>Via a Webhook URL</b>.</p>
						</div>
					</div>
					<div class="dashboard-box">
						<div class="dashboard-box-head">
							<h3>Quick links 🔗</h3>
						</div>
						<div class="dashboard-box-body">
							<ul>
								<li><a href="https://app.wanotifier.com/template/" target="_blank">Message Templates</a> - Create / edit message templates.</li>
								<li><a href="https://app.wanotifier.com/contacts/" target="_blank">Contacts</a> - Manage your contacts.</li>
								<li><a href="https://app.wanotifier.com/notifications/" target="_blank">Notifications</a> - See the notification delivery stats and much more.</li>
								<li><a href="https://app.wanotifier.com/inbox/" target="_blank">Inbox</a> - See customer replies to your notifications and message them back.</li>
							</ul>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>

	</div>
</div>
