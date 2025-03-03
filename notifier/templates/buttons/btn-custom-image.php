<?php
/**
 * Button View: Click to Chat Custom image Button.
 *
 * @package Notifier
 */

$whatsapp_number = get_option('notifier_ctc_whatsapp_number');
$ctc_greeting_text = get_option('notifier_ctc_greeting_text');
$ctc_greeting_text = wp_encode_emoji($ctc_greeting_text);
$url = 'https://wa.me/'.urlencode($whatsapp_number).'?text='.urlencode($ctc_greeting_text);

$ctc_custom_button_image_url  = get_option('notifier_ctc_custom_button_image_url');
if(!empty($ctc_custom_button_image_url)){
	?>
	<div class="notifier-click-to-chat-btn notifier-click-to-chat-style-custom">
		<a href="<?php echo esc_url($url); ?>" target="_blank">
			<img src="<?php echo $ctc_custom_button_image_url; ?>" alt="Whatsapp">
		</a>
	</div>
	<?php
}
