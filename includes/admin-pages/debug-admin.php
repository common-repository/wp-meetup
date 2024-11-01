<?php

// Exit if accessed directly
if (! defined('ABSPATH')) {
	exit;
}

class WPMeetupDebugAdmin extends WPMeetupAdminPage {

	/**
	 * @var WPMeetup
	 */
	var $core;

	public function __construct($core) {
		$this->core = $core;
		add_submenu_page(
			'wp_meetup_settings',
			'Debug Information',
			'Debug Information',
			'administrator',
			'wp_meetup_debug',
			array($this, 'create_page')
		);
	}

	public function display_page() {
		$options = $this->core->options->get_option();

		$this->render_postbox_open('Option Settings');
		?>
		<table>
			<tr>
				<td>Link Color:</td>
				<td><?php echo ($options['link_color'] ? 'White' : 'Set by Theme'); ?></td>
			</tr>
			<tr>
				<td>Link Direction:</td>
				<td><?php echo ($options['link_redirect'] ? 'Meetup.com' : 'WordPress Post'); ?></td>
			</tr>
			<tr>
				<td>Include on Homepage:</td>
				<td><?php echo ($options['include_homepage'] ? 'On Homepage' : 'Not on Homepage'); ?></td>
			</tr>
			<tr>
				<td>Include Venue:</td>
				<td><?php echo ($options['venue'] ? 'Venue included' : 'Not Included'); ?></td>
			</tr>
			<tr>
				<td>Support The Developer:</td>
				<td><?php echo ($options['support'] ? 'Yeah you do. You are awesome!' : 'We wish you would. =['); ?></td>
			</tr>

			<tr>
				<td>Calendar Legend:</td>
				<td><?php echo ($options['legend'] ? 'Displayed' : 'Not Displayed'); ?></td>
			</tr>
			<tr>
				<td>Single Calendar Legend:</td>
				<td><?php echo ($options['single_legend'] ? 'Displayed' : 'Not Displayed'); ?></td>
			</tr>
			<tr>
				<td>Link Text:</td>
				<td><?php echo $options['link_name'] ?></td>
			</tr>
			<tr>
				<td>Custom Post Type Slug:</td>
				<td><?php echo $options['wpm_pt'] ?></td>
			</tr>
			<tr>
				<td>Legend Title:</td>
				<td><?php echo $options['legend_title'] ?></td>
			</tr>
			<tr>
				<td>Single Legend Title:</td>
				<td><?php echo $options['single_legend_title'] ?></td>
			</tr>


		</table>
		<?php

		$this->render_postbox_close();

		$this->render_postbox_open('Query Options');
		?>
		<table>
			<tr>
				<td>Past Months Queried:</td>
				<td><?php echo $options['past_months'] ?></td>
			</tr>

			<tr>
				<td>Future Months Queried:</td>
				<td><?php echo $options['future_months'] ?></td>
			</tr>

			<tr>
				<td>Maximum Events:</td>
				<td><?php echo $options['max_events'] . ' per group' ?></td>
			</tr>
		</table>
		<?php

		$this->render_postbox_close();


		foreach ($this->core->groups as $group) {
			$this->core->event_db->select('COUNT(*)');
			$this->core->event_db->where('group_id', $group->group_id);
			$count = $this->core->event_db->get(null, true, true);
			$this->render_postbox_open($group->group_name);
			?>
			<table>
				<tr>
					<td>Group ID:</td>
					<td><?php echo $group->group_id ?></td>
				</tr>
				<tr>
					<td>Number of Events:</td>
					<td><?php echo $count ?></td>
				</tr>
				<tr>
					<td>Color:</td>
					<td class="group<?php echo $group->group_id ?>"><?php echo $group->color ?></td>
				</tr>
			</table>
			<?php

			$this->render_postbox_close();
		}

		$this->group_color_styles();
	}

	private function group_color_styles() {
		?>
		<style>
			<?php foreach ($this->core->groups as $group): ?>
			.group<?php echo $group->group_id; ?> {
				background-color: <?php echo $group->color; ?>;
				width: 5px;
				height: 5px;
			}

			<?php endforeach; ?>
		</style>
		<?php

	}
}
