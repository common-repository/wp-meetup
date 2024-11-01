<?php

// Exit if accessed directly
if (! defined('ABSPATH')) {
	exit;
}

class WPMeetupGroupsAdmin extends WPMeetupAdminPage {

	/**
	 * @var WPMeetup
	 */
	var $core;

	/**
	 * @var WPMeetupAPI
	 */
	var $api;

	/**
	 * @var WPMeetupGroupsDB
	 */
	var $db;

	/**
	 * @param WPMeetup $core
	 */
	public function __construct($core) {
		$this->core = $core;
		$this->db   = $core->group_db;
		$this->api  = $core->api;
		add_submenu_page(
			'wp_meetup_settings',
			'Groups',
			'Groups',
			'administrator',
			'wp_meetup_groups',
			array($this, 'create_page')
		);
	}

	public function display_page() {
		$this->update_colors_and_groups();
		$this->add_groups();
		$groups = $this->db->get();
		if (empty($groups)) {
			echo '<div class="nm-error">No groups have been added yet</div>';
		} else {
			$this->display_groups($groups);
		}
		echo '<hr>';
		$this->render_add_group();
	}

	/**
	 * Adds groups to db
	 */
	private function add_groups() {
		if (isset($_POST['update']) && $_POST['update'] == 'wpm-update-groups') {
			$groups      = $_POST['groups'];
			$group_array = explode(',', $groups);
			foreach ($group_array as $group) {
				$group = $this->parse_group_url_to_name($group);
				$this->add_group($group);
			}
			$_POST['update'] = null;
			$this->core->trigger->execute_update();
		}
	}


	/**
	 * Extract a group name from its Meetup.com URL.
	 *
	 * IMPORTANT: the algorithm here should be maintained concurrently with the JS function, `wpmeetup_parseGroupUrl()`()
	 *
	 * @since 2.3.0
	 *
	 * @param string $group_url
	 *
	 * @return string
	 */
	private function parse_group_url_to_name($group_url) {
		$patterns = array(
			'/^.*https?:\/\/(www\.)?meetup\.com\/?/', // Remove `https://www.meetup.com/` (and small variations thereof)
			'/\?.+$/', // Remove any query params
			'/\#.+$/', // Remove any `#`s
		);
		// Replace with empty strings
		$replacements = array(
			'',
			'',
			'',
			'',
		);
		return str_replace(array('\\', '/'), array('', ''), preg_replace($patterns, $replacements, trim($group_url)));
	}

	/**
	 * Adds an individual group to the DB
	 *
	 * @since 2.3.0 Updated for OAuth2
	 * @since 1.0.0
	 *
	 * @param string $group_name
	 */
	private function add_group($group_name) {
		if (empty($group_name)) {
			echo '<div class="notice notice-error"><h3>Error: Could not add group</h3><p>No group name given. Check that your group URL is in the correct format.</p></div>';
			return;
		}
		$group = $this->api->get_group($group_name);
		if (empty($group)) {
			printf('<div class="notice notice-error"><h3>Error: Could not find a group whose slug is <code>%s</code></h3><p>Please enter a correct group urlname. If you think this is in error, you can contact us.</p></div>', esc_html($group_name));
			return;
		}

		$group_data = array(
			'group_name' => $group->name,
			'group_slug' => $group->urlname,
			'group_id'   => $group->id,
		);
		$this->db->select('id');
		$this->db->where($group_data);
		$id = $this->db->get();
		if (empty($id)) {
			$id = null;
		} else {
			$id = $id[0];
			$id = $id->id;
		}
		$group_data['color'] = '#656565';
		$this->db->save($group_data, $id);
	}


	/**
	 * Retrieves the updates from $_POST and filters to individual updates
	 */
	private function update_colors_and_groups() {
		if (isset($_POST['update']) && $_POST['update'] == 'wpm-update-color') {
			foreach ($_POST as $key => $value) {
				if ($key != 'update') {
					$this->update_individual_entry($key, $value);
				}
			}
		}
	}

	/**
	 * Updates each row of the DB as needed
	 *
	 * @param STRING $key
	 * @param STRING $value
	 */
	private function update_individual_entry($key, $value) {
		$key = explode('-', $key);
		if ($key[0] == 'color') {
			$data = array(
				'color' => $value,
			);
			$this->db->save($data, $key[1]);
		}
		if ($key[0] == 'delete') {
			$this->db->select('group_id');
			$this->db->where(array('id' => $key[1]));
			$group_ids = $this->db->get();
			foreach ($group_ids as $id_object) {
				$group_id = $id_object->group_id;
				$this->core->event_db->select('id');
				$this->core->event_db->where(array('group_id' => $group_id));
				$ids = $this->core->event_db->get();
				foreach ($ids as $id) {
					$this->core->event_db->delete($id->id);

				}
			}
			$this->db->delete($key[1]);
		}
	}

	/**
	 * Outputs the form used to add groups
	 *
	 * @since 2.3.0 Added an inline JavaScript preview of the parsed URL
	 * @since 1.0.0
	 */
	private function render_add_group() {
		?>
		<form action="" method="post">
			<input type="hidden" name="update" value="wpm-update-groups">
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row" valign="top"><label for="groups">Add a new group</label></th>
						<td>
							<input type="text" name="groups" id="new-group-url" class="regular-text" value="" placeholder="https://www.meetup.com/MyMeetupGroup/" required>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							&nbsp;
							<span id="group_name_double_checker" style="display: none;">Make sure that we've parsed your group name correctly: <code id="new_parsed_group_name"></code></span>
						</td>
					</tr>
				</tbody>
			</table>
			<p class="submit">
				<input type="submit" name="submit" class="button" value="Add Group">
			</p>
		</form>
		<script>
			jQuery(function() {
				/**
				 * Parse a URL and get the group name at its core.
				 *
				 * IMPORTANT: the algorithm here should be maintained concurrently with @see WPMeetupGroupsAdmin::parse_group_url_to_name()
				 *
				 * @since 2.3.0
				 */
				function wpmeetup_parseGroupUrl(url) {
					url = url.trim()
					         .replace(/^.*https?:\/\/(www\.)?meetup\.com\/?/, '') // Remove `https://www.meetup.com/` (and small variations thereof)
					         .replace(/\?.+$/, '') // Remove any query params
					         .replace(/\#.+$/, '') // Remove any `#`s
					         .replace(/[\/\\]/, '') // Remove any `/`s or `\`s
					;
					return url;
				}

				/**
				 * Provide an inline constantly-updated parsing of the group URL
				 *
				 * @since 2.3.0
				 *
				 * @param {string} url
				 */
				function wpmeetup_renderParsedGroupUrl(url) {
					url = url.trim();
					if (! url || url.length == 0) {
						// Input is empty. Hide the helper message.
						jQuery('#group_name_double_checker').hide();
						jQuery('#new_parsed_group_name').html('');
					} else {
						// Parse the URL and show the helper message
						jQuery('#group_name_double_checker').show();
						jQuery('#new_parsed_group_name').html(wpmeetup_parseGroupUrl(url));
					}
				}

				var newGroupUrlInput = jQuery('#new-group-url');

				newGroupUrlInput.on('keyup', function() {
					wpmeetup_renderParsedGroupUrl(newGroupUrlInput.val());
				});
				newGroupUrlInput.on('change', function() {
					wpmeetup_renderParsedGroupUrl(newGroupUrlInput.val());
				});
			});
		</script>
		<?php

	}

	/**
	 * Renders the table and form display of the groups
	 *
	 * @param object[] $groups
	 */
	private function display_groups($groups) {
		?>
		<form method="post" action="">
			<input type="hidden" name="update" value="wpm-update-color">

			<table class="widefat striped fixed">
				<thead>
					<tr>
						<th>Name</th>
						<th>Slug</th>
						<th>ID</th>
						<th>Color</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($groups as $group): ?>
					<tr>
						<td><?php echo $group->group_name; ?></td>
						<td><?php echo $group->group_slug; ?></td>
						<td><?php echo $group->group_id; ?></td>
						<td><input type="color" name="color-<?php echo $group->id ?>" value="<?php echo $group->color ?>"></td>
						<td><label><input type="checkbox" name="delete-<?php echo $group->id ?>" value="checked"> Delete</label></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="submit">
				<input type="submit" name="submit" class="button button-primary" value="Update Groups">
			</p>
		</form>
		<?php
	}
}
