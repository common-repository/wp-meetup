<?php

// Exit if accessed directly
if (! defined('ABSPATH')) {
	exit;
}

class WPMeetupEventsAdmin extends WPMeetupAdminPage {

	/**
	 * @var WPMeetup
	 */
	var $core;

	var $name;

	public function __construct($core) {
		$this->core = $core;
		$this->name = $this->core->post_type;
		add_submenu_page(
			'wp_meetup_settings',
			ucfirst($this->name),
			ucfirst($this->name),
			'administrator',
			'wp_meetup_events',
			array($this, 'create_page')
		);
	}

	public function display_page() {
		$this->core->event_db->where('status', 'active');
		$active_events = $this->core->event_db->get();

		$this->core->event_db->where('status', 'inactive');
		$inactive_events = $this->core->event_db->get();

		?>
		<h2 class="title">Active Events</h2>
		<table class="widefat striped fixed">
			<thead>
				<tr>
					<th>Event Name</th>
					<th>Date</th>
					<th>Group</th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($active_events)): ?>
					<tr class="no-items">
						<td colspan="3">No active events found</td>
					</tr>
				<?php else: ?>
					<?php foreach ($active_events as $event): ?>
						<tr>
							<?php $this->display_event($event); ?>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>


		<h2 class="title">Inactive Events</h2>
		<?php if ($this->core->options->get_option('auto_delete')): ?>
			<p>Inactive events are set to be automatically deleted. This setting can be changed in the WP Meetup Options page.</p>
		<?php endif; ?>
		<form action="" method="post">
			<input type="hidden" name="update" value="wpm-update-event-deletion">
			<table class="widefat striped fixed">
				<thead>
					<tr>
						<th>Event Name</th>
						<th>Date</th>
						<th>Group</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($inactive_events)): ?>
						<tr class="no-items">
							<td colspan="4">No inactive events found</td>
						</tr>
					<?php else: ?>
						<?php foreach ($inactive_events as $event): ?>
							<tr>
								<?php $this->display_event($event, true); ?>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<p class="submit">
				<input type="submit" name="submit" class="button" value="Delete Selected Events">
			</p>
		</form>
		<?php
		$this->group_color_styles();
	}


	public function display_event($event, $show_delete_column = false) {
		$event_raw = unserialize($event->event);
		$group_name = $event_raw->group->name;
		if (strlen($group_name) > 25) {
			$group_name = substr($group_name, 0, 25) . '...';
		}
		?>
		<td>
			<a href="<?php echo get_permalink($event->wp_post_id) ?>" title="WP Post ID: <?php echo esc_attr($event->wp_post_id); ?>"><?php echo substr($event_raw->name, 0, 20) ?></a>
		</td>
		<td><?php echo date('Y-m-d g:i A', $event->event_time) ?></td>
		<td><div title="Group ID: <?php echo esc_attr($event_raw->group->id); ?>"><div class="group<?php echo $event->group_id ?>"></div> <?php echo $group_name; ?></div></td>
		<?php if ($show_delete_column): ?>
			<td>
				<label><input type="checkbox" name="events_to_delete[]" value="<?php echo $event->id; ?>"> Delete</label>
			</td>
		<?php endif;
	}

	private function group_color_styles() {
		?>
		<style>
			<?php foreach ($this->core->groups as $group): ?>
			.group<?php echo $group->group_id; ?> {
				background-color: <?php echo $group->color; ?>;
				width: 0.7em;
				height: 0.7em;
				display: inline-block;
			}
			<?php endforeach; ?>
		</style>
		<?php
	}
}
