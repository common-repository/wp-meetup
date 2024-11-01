<?php

// Exit if accessed directly
if (! defined('ABSPATH')) {
	exit;
}

class WPMeetupOptions {

	var $options_name;

	var $defaults;

	/**
	 * @var MIXED STRING/BOOL
	 */
	var $updated = false;

	/**
	 * @var WPMeetup
	 */
	var $core;

	var $old_pt_slug;
	var $new_pt_slug;

	/**
	 *
	 * @param WPMeetup $core
	 */
	public function __construct($core) {
		$this->core = $core;
		if (isset($_POST['update'])) {
			$this->updated = $_POST['update'];
		}
		$this->options_name = $core->options_name;
		$this->defaults     = array(
			'key'                 => '',
			'version'             => '',
			'wpm_pt'              => __('events'),
			'link_name'           => __('View On Meetup.com'),
			'support'             => false,
			'link_color'          => false,
			'link_redirect'       => false,
			'include_homepage'    => false,
			'display_map'         => false,
			'past_months'         => 1,
			'future_months'       => 3,
			'max_events'          => 100,
			'legend'              => false,
			'legend_title'        => __('Groups:'),
			'single_legend_title' => '',
			'venue'               => false,
			'queue_prompt'        => time() + 259200,
			'install_count'       => 3,
			'auto_delete'         => false,
			'delete_old'          => false,
			'single_legend'       => false,
		);
		if ($this->get_option() == false) {
			$this->set_to_defaults();
		}
	}

	public function set_to_defaults() {
		delete_option($this->options_name);
		foreach ($this->defaults as $key => $value) {
			$this->update_option($key, $value);
		}
	}

	public function update_options() {
		if (isset($_POST['update']) && $_POST['update'] === 'wpm-update-options') {
			if (! isset($_POST['link_color'])) {
				$_POST['link_color'] = null;
			}
			if (! isset($_POST['include_homepage'])) {
				$_POST['include_homepage'] = null;
			}
			if (! isset($_POST['link_redirect'])) {
				$_POST['link_redirect'] = null;
			}
			if (! isset($_POST['support'])) {
				$_POST['support'] = null;
			}
			if (! isset($_POST['legend'])) {
				$_POST['legend'] = null;
			}
			if (! isset($_POST['venue'])) {
				$_POST['venue'] = null;
			}
			if (! isset($_POST['auto_delete'])) {
				$_POST['auto_delete'] = null;
			}
			if (! isset($_POST['delete_old'])) {
				$_POST['delete_old'] = null;
			}
			if (! isset($_POST['single_legend'])) {
				$_POST['single_legend'] = null;
			}
			$current_settings       = $this->get_option();
			$clean_current_settings = array();
			foreach ($current_settings as $k => $val) {
				if ($k != null) {
					$clean_current_settings[$k] = $val;
				}
			}
			$this->defaults = array_merge($this->defaults, $clean_current_settings);
			$update         = array_merge($this->defaults, $_POST);
			$data           = array();
			foreach ($update as $key => $value) {
				if ($key != 'update' && $key != null) {
					if ($value == 'checked') {
						$data[$key] = $value;
					} else {
						$data[$key] = $value;
					}

				}
			}

			// was the post type slug updated?
			if (isset($clean_current_settings['wpm_pt']) && isset($data['wpm_pt']) && $clean_current_settings['wpm_pt'] != $data['wpm_pt']) {
				add_action('plugins_loaded', array(&$this, 'apply_new_post_slug'));
			}

			$this->update_option($data);
			$_POST['update'] = null;
			$this->updated   = 'wpm-update-options';
		} else if (isset($_POST['update']) && ($_POST['update'] === 'wpm-update-support' || $_POST['update'] === 'wpm-update-support-prompt')) {
			$current_settings = $this->get_option();
			$this->defaults   = array_merge($this->defaults, $current_settings);
			$update           = array_merge($this->defaults, $_POST);
			$data             = array();
			foreach ($update as $key => $value) {
				if ($key != 'update' && $key != null) {
					$data[$key] = $value;

				}
			}
			$this->update_option($data);
			$_POST['update'] = null;
			$this->updated   = 'wpm-update-support';
		} else if (isset($_POST['update']) && $_POST['update'] === 'wpm-update-prompt') {
			$current_settings = $this->get_option();
			$this->defaults   = array_merge($this->defaults, $current_settings);
			$update           = array_merge($this->defaults, $_POST);
			$data             = array();
			foreach ($update as $key => $value) {
				if ($key != 'update' && $key != null) {
					$data[$key] = $value;

				}
			}
			$this->update_option($data);
			$_POST['update'] = null;
			$this->updated   = 'wpm-update-prompt';
		} else if (isset($_POST['update']) && $_POST['update'] === 'wpm-update-events') {
			$_POST['update'] = null;
			$this->updated   = 'wpm-update-events';
			add_action('init', array(&$this, 'button_force_update'));
		} else if (isset($_POST['update']) && $_POST['update'] === 'wpm-update-event-deletion' && ! empty($_POST['events_to_delete'])) {
			$_POST['update'] = null;
			foreach ($_POST['events_to_delete'] as $event_id) {
				$event_id = intval($event_id);
				if (! empty($event_id)) {
					$this->core->event_db->delete($event_id);
				}
			}
		} else if (isset($_POST['update']) && ($_POST['update'] === 'wpm-update-key')) {
			$current_settings = $this->get_option();
			$this->defaults   = array_merge($this->defaults, $current_settings);
			$update           = array_merge($this->defaults, $_POST);
			$data             = array();
			foreach ($update as $key => $value) {
				if ($key != 'update' && $key != null) {
					$data[$key] = $value;
				}
			}
			$this->update_option($data);
			$_POST['update'] = null;
			$this->updated   = 'wpm-update-key';
		}
	}

	public function button_force_update() {
		$this->core->trigger->execute_update();
	}

	public function apply_new_post_slug() {
		$this->new_pt_slug = $this->get_option('wpm_pt');

		// get post ids from events db
		$this->core->event_db->select('wp_post_id');
		$id_array = $this->core->event_db->get();

		// for each post, run....
		foreach ($id_array as $id) {
			$id = $id->wp_post_id;
			set_post_type($id, $this->new_pt_slug);
		}
		add_action('init', array(&$this, 'nm_rewrite_rules'));
	}

	public function nm_rewrite_rules() {
		flush_rewrite_rules();
	}


	/**
	 * Gets an option for an array'd wp_options,
	 * accounting for if the wp_option itself does not exist,
	 * or if the option within the option
	 *
	 * @since  Version 1.0.0
	 *
	 * @param  string $opt_name
	 *
	 * @return mixed (or FALSE on fail)
	 */
	public function get_option($opt_name = '') {
		$options = get_option($this->options_name);

		// maybe return the whole options array?
		if ($opt_name == '') {
			return $options;
		}

		// are the options already set at all?
		if ($options == false) {
			return $options;
		}

		// the options are set, let's see if the specific one exists
		if (! isset($options[$opt_name])) {
			return false;
		}

		// the options are set, that specific option exists. return it
		return $options[$opt_name];
	}

	/**
	 * Wrapper to update wp_options. allows for function overriding
	 * (using an array instead of 'key, value') and allows for
	 * multiple options to be stored in one name option array without
	 * overriding previous options.
	 *
	 * @since  Version 1.0.0
	 *
	 * @param  string $opt_name
	 * @param  mixed  $opt_val
	 */
	public function update_option($opt_name, $opt_val = '') {
		// ----- allow a function override where we just use a key/val array
		if (is_array($opt_name) && $opt_val == '') {
			foreach ($opt_name as $real_opt_name => $real_opt_value) {
				$this->update_option($real_opt_name, $real_opt_value);
			}
		} else {
			$current_options = $this->get_option(); // get all the stored options

			// ----- make sure we at least start with blank options
			if ($current_options == false) {
				$current_options = array();
			}

			// ----- now save using the wordpress function
			$new_option = array($opt_name => $opt_val);
			update_option($this->options_name, array_merge($current_options, $new_option));
		}
	}

	/**
	 * Given an option that is an array, either update or add
	 * a value (or data) to that option and save it
	 *
	 * @since  Version 1.0.0
	 *
	 * @param  string $opt_name
	 * @param  mixed  $key_or_val
	 * @param  mixed  $value
	 */
	public function append_to_option($opt_name, $key_or_val, $value = null, $merge_values = true) {
		$key     = '';
		$val     = '';
		$results = $this->get_option($opt_name);

		// ----- always use at least an empty array!
		if (! $results) {
			$results = array();
		}

		// ----- allow function override, to use automatic array indexing
		if ($value === null) {
			$val = $key_or_val;

			// if value is not in array, then add it.
			if (! in_array($val, $results)) {
				$results[] = $val;
			}
		} else {
			$key = $key_or_val;
			$val = $value;

			// ----- should we append the array value to an existing array?
			if ($merge_values && isset($results[$key]) && is_array($results[$key]) && is_array($val)) {
				$results[$key] = array_merge($results[$key], $val);
			} else {
				// ----- don't care if key'd value exists. we override it anyway
				$results[$key] = $val;
			}
		}

		// use our internal function to update the option data!
		$this->update_option($opt_name, $results);
	}

	public function update_messages() {
		if ($this->updated == 'wpm-update-options') {
			echo '<div class="updated">The options have been successfully updated.</div>';
			$this->updated = false;
		} else if ($this->updated == 'wpm-update-support') {
			echo '<div class="updated">Thank you for supporting the development team! We really appreciate how awesome you are.</div>';
			$this->updated = false;
		} else if ($this->updated == 'wpm-update-groups') {
			// echo '<div class="updated">Groups have been added.</div>'; // @since 2.3.0 Deprecated. This is now taken care of in `groups-admin.php`
			$this->updated = false;
		} else if ($this->updated == 'wpm-update-color') {
			echo '<div class="updated">Colors have been saved and the check groups have been deleted.</div>';
			$this->updated = false;
		} else if ($this->updated == 'wpm-update-events') {
			echo '<div class="updated">Events have been updated.</div>';
			$this->updated = false;
		} else if ($this->updated == 'wpm-update-event-deletion') {
			echo '<div class="updated">Specified inactive events have been deleted.</div>';
			$this->updated = false;
		} else if ($this->updated == 'wpm-update-key') {
			echo '<div class="updated">API key has been updated.</div>';
			$this->updated = false;
		}
	}


	/**
	 * Check if the OAuth2 access token is stored.
	 *
	 * @since 2.3.1
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	public function has_access_token() {
		$access_token = $this->get_option('oauth2_access_token');
		return (! empty($access_token));
	}


	/**
	 * Get the OAuth2 access token.
	 *
	 * @since 2.3.0
	 *
	 * @return string
	 */
	public function get_access_token() {
		$access_token = $this->get_option('oauth2_access_token');
		if (empty($access_token)) {
			return '';
		}
		return $access_token;
	}
}
