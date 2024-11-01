<?php

// Exit if accessed directly
if (! defined('ABSPATH')) {
	exit;
}

class WPMeetupOptionsAdmin extends WPMeetupAdminPage {

	/**
	 * @var WPMeetup
	 */
	var $core;

	public function __construct($core) {
		$this->core = $core;
		add_submenu_page(
			'wp_meetup_settings',
			'Options',
			'Options',
			'administrator',
			'wp_meetup_options',
			array($this, 'create_page')
		);
	}

	public function display_page() {
		$options = $this->core->options->get_option();
		?>
		<form action="" method="post">
			<input type="hidden" name="update" value="wpm-update-options">

			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">General settings</th>
						<td>
							<fieldset>
								<label><input type="checkbox" name="link_color" value="checked" <?php echo $options['link_color'] ?> /> Force links in calendar to be white</label>
								<br>
								<label><input type="checkbox" name="include_homepage" value="checked" <?php echo $options['include_homepage'] ?> /> Show events on homepage</label>
								<br>
								<label><input type="checkbox" name="support" value="checked"<?php echo $options['support'] ?> /> Support the development team</label>
								<br>
								<label><input type="checkbox" name="venue" value="checked" <?php echo $options['venue'] ?> /> Show venue address on event posts</label>
								<br>
								<label><input type="checkbox" name="link_redirect" value="checked" <?php echo $options['link_redirect'] ?> /> Route calendar and widget links to the meetup.com event page instead of the WordPress event post</label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row">Custom post type slug</th>
						<td><input type="text" name="wpm_pt" value="<?php echo $options['wpm_pt'] ?>"/></td>
					</tr>
					<tr>
						<th scope="row">Link name for event posts</th>
						<td><input type="text" name="link_name" class="regular-text" value="<?php echo $options['link_name'] ?>"/></td>
					</tr>
				</tbody>
			</table>

			<h2 class="title">Calendar Legend</h2>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">Legend settings</th>
						<td>
							<fieldset>
								<label><input type="checkbox" name="legend" value="checked" <?php echo $options['legend'] ?> /> Display legend</label>
								<br/>
								<label><input type="checkbox" name="single_legend" value="checked" <?php echo $options['single_legend'] ?> /> Show legend even if there is only one group</label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="legend_title">Legend title</label></th>
						<td><input type="text" name="legend_title" value="<?php echo $options['legend_title'] ?>"/></td>
					</tr>
					<tr>
						<th scope="row"><label for="single_legend_title">Legend title (when there is only one group)</label></th>
						<td><input type="text" name="single_legend_title" value="<?php echo $options['single_legend_title'] ?>"/></td>
					</tr>
				</tbody>
			</table>

			<h2 class="title">Events</h2>
			<p>WP Meetup will only query events within a certain time period. You may extend this time period by changing the number of months queried in the past and in the future. You may also limit the number of events pulled per group. Setting these values too high can be detrimental to your website's performance.</p>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row"><label for="past_months">Past months queried</label></th>
						<td><input type="number" name="past_months" value="<?php echo $options['past_months'] ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="future_months">Future months queried</label></th>
						<td><input type="number" name="future_months" value="<?php echo $options['future_months'] ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="max_events">Max events queried per group</label></th>
						<td><input type="number" name="max_events" value="<?php echo $options['max_events'] ?>"></td>
					</tr>
					<tr>
						<td class="td-full" colspan="2">
							<fieldset>
								<label><input type="checkbox" name="auto_delete" value="checked" <?php echo $options['auto_delete'] ?> /> Automatically delete inactive events</label>
								<br>
								<label><input type="checkbox" name="delete_old" value="checked" <?php echo $options['delete_old'] ?> /> Mark events outside query range as inactive</label>
								<p class="description">This will also hide inactive events from calendars and archives</p>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<input type="submit" name="submit" class="button button-primary" value="Update Options">
			</p>
		</form>
		<?php

	}
}
