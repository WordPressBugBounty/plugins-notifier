<?php
/**
 * Triggers CPT Meta Box
 *
 * @package WA_Notifier
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $post_id;
?>
<div class="meta-fields">
	<div class="general-fields">
		 <div>
			<?php
			$main_triggers = self::get_notification_triggers();
			foreach ($main_triggers as $key => $triggers) {
				$dropdown_triggers[$key] = wp_list_pluck($triggers, 'label', 'id');
			}
			$selected_trigger = Notifier_Notification_Triggers::get_post_trigger_id($post_id);
			?>
			<p class="form-field mb-3 notifier_trigger_field " data-conditions="">
				<label for="notifier_trigger" class="form-label">Select Trigger</label>
				<select class="form-select" style="" name="notifier_trigger" id="notifier_trigger">
					<option value="">Select trigger</option>
					<?php
						foreach ($main_triggers as $key => $triggers) {
							if(empty($triggers)){
								continue;
							}
							echo '<optgroup label="'.esc_attr($key).'">';
							foreach($triggers as $trigger){
								$selected = selected( $selected_trigger, $trigger['id'], false);
								echo '<option value="'.esc_attr($trigger['id']).'" title="'.esc_attr($trigger['description']).'" '.$selected.' >'.esc_attr($trigger['label']).'</option>';
							}
							echo '</optgroup>';
						}
					?>
				</select>
				<span class="description">Select the trigger when you want to fire the Notification on WANotifier.com.</span>
			</p>
		</div>
		<?php
		$send_to_conditions = array (
				array (
					'field'		=> NOTIFIER_PREFIX . 'trigger',
					'operator'	=> '!=',
					'value'		=> ''
				)
			);
		?>
		<div class="form-field notifier-trigger-merge-tags" data-conditions="<?php echo esc_attr ( json_encode( $send_to_conditions ) ); ?>">

		</div>
	</div>
</div>
