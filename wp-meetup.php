<?php

/*
 * Plugin Name: WP Meetup
 * Plugin URI: https://nuancedmedia.com/wordpress-meetup-plugin/
 * Description: Pulls events from Meetup.com onto your blog to be displayed in a calendar, list, or widgets.
 * Version: 2.3.1
 * Author: Nuanced Media
 * Author URI: http://nuancedmedia.com/
 *
 * Copyright 2019 Nuanced Media
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

// Exit if accessed directly
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Just in case
 */
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

// Require Library
if (! class_exists('ApiInteraction')) {
	require_once('library/api-interaction.php');
}
if (! class_exists('NMCustomPost')) {
	require_once('library/custom-post.php');
}
require_once('library/helper-functions.php');
if (! class_exists('NMDB')) {
	require_once('library/nmdb.php');
}
if (! class_exists('NMWidget')) {
	require_once('library/nm-widget.php');
}

// Require Short Codes
require_once('includes/shortcodes/calendar.php');
require_once('includes/shortcodes/event-list.php');

// Require Admin Pages
require_once('includes/admin-pages/admin-page.php');
require_once('includes/admin-pages/main-admin.php');
require_once('includes/admin-pages/options-admin.php');
require_once('includes/admin-pages/groups-admin.php');
require_once('includes/admin-pages/events-admin.php');
require_once('includes/admin-pages/debug-admin.php');
require_once('includes/admin-pages/oauth2-admin.php');

// Require DBs
require_once('includes/db/events-db.php');
require_once('includes/db/groups-db.php');
require_once('includes/db/posts-db.php');

// Require Includes
require_once('includes/api.php');
require_once('includes/factory.php');
require_once('includes/pt.php');

// Require Widgets
require_once('includes/widgets/calendar-widget.php');
require_once('includes/widgets/event-list-widget.php');

// Require Views
require_once('includes/views/event-view.php');


// Require Main
require_once('trigger.php');
require_once('wpm-admin.php');
require_once('wpm-options.php');
require_once('backwards-compatibility.php');

/**
 * Class WP_Meetup
 */
class WPMeetup {

	/**
	 * @var WPMeetupOptions
	 */
	var $options;

	/**
	 * @var WPMeetupPostType
	 */
	var $pt;

	/**
	 * @var WPMeetupEventsDB
	 */
	var $event_db;

	/**
	 * @var WPMeetupGroupsDB;
	 */
	var $group_db;

	/**
	 * @var WPMeetupPostsDB
	 */
	var $post_db;

	/**
	 * @var WPMeetupAdmin
	 */
	var $admin;

	/**
	 * @var WPMeetupAPI
	 */
	var $api;

	/**
	 * @var string
	 */
	var $post_type;

	/**
	 * @var string
	 */
	var $options_name = 'wp_meetup_options';

	/**
	 * @var array
	 */
	var $events;

	/**
	 * @var array
	 */
	var $groups;

	/**
	 * @var WPMeetupTrigger
	 */
	var $trigger;

	/**
	 * @var WPMeetupFactory
	 */
	var $factory;

	/**
	 * @global string $wpm_core
	 */

	public function __construct() {
		global $wpm_core;

		// Create Database
		$this->event_db = new WPMeetupEventsDB();
		$this->group_db = new WPMeetupGroupsDB();
		$this->post_db  = new WPMeetupPostsDB();

		// Create - Update - Get options
		$this->options = new WPMeetupOptions($this);
		$this->options->update_options();
		$this->post_type = $this->options->get_option('wpm_pt');

		$this->api     = new WPMeetupAPI($this);
		$this->pt      = new WPMeetupPostType($this);
		$this->factory = new WPMeetupFactory($this);
		$this->trigger = new WPMeetupTrigger($this);

		new WPMeetupBackCap($this);

		// Update Version Number
		$plugin_data  = get_plugin_data($wpm_core);
		$this_version = $plugin_data['Version'];
		$this->options->update_option('version', $this_version);

		// Execute Admin
		if (is_admin()) {
			$this->admin = new WPMeetupAdmin($this);
		}

		if ($this->options->get_option('include_homepage')) {
			add_filter('pre_get_posts', array(&$this, 'include_events_in_loop'));
		}

		add_action('init', array(&$this, 'init'));
		add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'add_plugin_settings_link'));


		// Short codes
		add_shortcode('meetup-calendar', array(&$this, 'shortcode_calendar'));
		add_shortcode('wp-meetup-calendar', array(&$this, 'shortcode_calendar'));

		add_shortcode('meetup-list', array(&$this, 'shortcode_list'));
		add_shortcode('wp-meetup-list', array(&$this, 'shortcode_list'));

		// Widgets
		add_action('widgets_init', array(&$this, 'register_calendar_widget'));
		add_action('widgets_init', array(&$this, 'register_event_list_widget'));

		// Load CSS
		add_action('wp_enqueue_scripts', array(&$this, 'load_styles'), 100);

		add_action('admin_notices', array($this, 'v2_3_0_maybe_display_oauth_warning_message'));

		// Schedule a cron job to refresh the access token, if needed
		if (! wp_next_scheduled('nm_wpm_refresh_access_token_hook')) {
			wp_schedule_event(time(), 'daily', 'nm_wpm_refresh_access_token_hook');
		}
		add_action('nm_wpm_refresh_access_token_hook', array($this, 'refresh_access_token'));
	}



	public function init() {
		// Retrieve events from DB
		$this->event_db->order_by('event_time');
		$this->event_db->where('status', 'active');
		$this->events = $this->event_db->get();
		$this->groups = $this->group_db->get();
	}

	public function load_styles() {
		wp_register_style('wpm-styles', plugins_url('assets/css/wp-meetup.css', __FILE__));
		wp_enqueue_style('wpm-styles');
	}

	public function return_nm_credit() {
		$support = $this->options->get_option('support');
		if ($support) {
			$output = '<div class="credit-line">Supported By: <a href="https://nuancedmedia.com" target="_blank">';
			$output .= '<img alt="Nuanced Media" src="' . plugins_url('images/nuanced-media.png', __FILE__) . '" />';
			$output .= '</a></div>' . PHP_EOL;

			return $output;
		}

		return '';
	}

	public function include_events_in_loop($query) {
		if (is_home() && $query->is_main_query()) {
			$query->set('post_type', array('post', $this->options->get_option('wpm_pt')));
		}

		return $query;
	}

	public function add_plugin_settings_link($links) {
		$settings_link = '<a href="' . admin_url() . 'admin.php?page=wp_meetup_settings">' . __('Settings') . '</a>';
		array_unshift($links, $settings_link);

		return $links;
	}

	public function shortcode_calendar($atts, $is_widget = false) {
		$calendar = new WPMeetupCalendar($this, $atts, $is_widget);
		if ($is_widget) {
			echo $calendar->execute();
		} else {
			return $calendar->execute();
		}
	}

	public function shortcode_list($atts, $is_widget = false) {
		$event_list = new WPMeetupEventList($this, $atts, $is_widget);
		if ($is_widget) {
			echo $event_list->execute();
		} else {
			return $event_list->execute();
		}
	}

	public function register_calendar_widget() {
		register_widget('WPMeetupCalendarWidget');
	}

	public function register_event_list_widget() {
		register_widget('WPMeetupEventListWidget');
	}

	/**
	 * As of August 2019, Meetup.com's API will no longer work with API keys. This method checks to see if there is an
	 * API key option set, but no oauth2 option set. If so, display a warning to the user that the plugin will no longer
	 * work and that they need to authorize via oauth2 instead.
	 *
	 * @since 2.3.0
	 */
	public function v2_3_0_maybe_display_oauth_warning_message() {
		$api_key = $this->options->get_option('key');
		$access_token = $this->options->get_access_token();
		if (empty($api_key)) {
			return;
		} else if (! empty($access_token)) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<h2><?php _e('WP Meetup', 'wp-meetup') ?></h2>
			<h4><?php _e('You need to reconnect to Meetup.com!', 'wp-meetup') ?></h4>
			<p><?php printf(__('Meetup.com removed all support for API key-based authentication on August 15, 2019. We see that you have an API key saved, but have not yet set up Oauth2. <strong>This plugin will not function anymore unless you update your settings.</strong> Visit the <a href="%s">WP Meetup OAuth2 page</a> and follow the instructions to restore functionality.', 'wp-meetup'), admin_url('admin.php?page=wp_meetup_oauth2')); ?></p>
		</div>
		<?php
	}


	/**
	 * Runs as a cron job, primarily. Attempts to refresh Meetup.com API's access_token by using the refresh_token.
	 *
	 * @since 2.3.0
	 *
	 * @return bool The success of the refresh.
	 */
	public function refresh_access_token() {
		if ($this->options->has_access_token()) {
			$oauth2 = array(
				'key'           => $this->options->get_option('oauth2_key'),
				'secret'        => $this->options->get_option('oauth2_secret'),
				'token_expires' => $this->options->get_option('oauth2_token_expires'),
				'refresh_token' => $this->options->get_option('oauth2_refresh_token'),
			);

			if (! empty($oauth2['refresh_token']) && ! empty($oauth2['key']) && ! empty($oauth2['secret'])) {
				// See: https://www.meetup.com/meetup_api/auth/#oauth2-refresh
				$query_url = sprintf('https://secure.meetup.com/oauth2/access?client_id=%s&client_secret=%s&grant_type=refresh_token&refresh_token=%s', urlencode($oauth2['key']), urlencode($oauth2['secret']), urlencode($oauth2['refresh_token']));
				$response  = $this->api->remote_post($query_url);
				if (! empty($response)) {
					$response = (array) $response;
					if (! empty($response['access_token']) && ! empty($response['refresh_token'])) {
						// Store the new access_token
						$this->options->update_option('oauth2_access_token', $response['access_token']);
						$this->options->update_option('oauth2_token_obtained', time());
						if ($oauth2['refresh_token'] != $response['refresh_token']) {
							// The refresh_token shouldn't ever change. But just in case Meetup decides to change their mind, let's check and see if it's different.
							$this->options->update_option('oauth2_refresh_token', $response['refresh_token']);
						}
						return true;
					}
				}
			}
		}

		return false;
	}

}

global $wpm_core;
global $wp_meetup;
$wpm_core  = __FILE__;
$wp_meetup = new WPMeetup();
