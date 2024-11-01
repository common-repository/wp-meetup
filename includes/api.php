<?php

// Exit if accessed directly
if (! defined('ABSPATH')) {
	exit;
}

class WPMeetupAPI extends ApiInteraction {

	/**
	 * @var WPMeetup
	 */
	var $core;

	/** @var array[] $last_errors Array with keys array(code, message, url, action) */
	private static $last_errors = array();

	/**
	 * @since 2.3.0
	 *
	 * @return array[] Array with keys array(code, message, url, action)
	 */
	public static function _get_last_errors() {
		return self::$last_errors;
	}

	/**
	 * Store an error we just encountered, so we can retrieve and display it later.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $error_code The error code we received
	 * @param string $message    The error message we received
	 * @param string $url        The URL we queried
	 * @param string $action     The action we were trying to take
	 */
	public static function record_error($error_code = -1, $message = '', $url = '', $action = '') {
		self::$last_errors[] = array('code' => $error_code, 'message' => $message, 'url' => $url, 'action' => $action);
	}



	/**
	 * @param WPMeetup $core
	 */
	public function __construct($core) {
		$this->core = $core;
	}


	/**
	 * Retrieves a group's details for a given group name from Meetup.com's API.
	 *
	 * @since 2.3.0
	 *
	 * @param string $group_name
	 *
	 * @return object[]
	 */
	public function get_group($group_name) {
		$access_token = $this->core->options->get_access_token();
		if (empty($access_token)) {
			return array();
		}

		$query_url = sprintf('https://api.meetup.com/%s/?access_token=%s', $group_name, $access_token);
		return $this->remote_get($query_url, array());
	}


	/**
	 * Retrieves events for a given group from Meetup.com's API.
	 *
	 * @since 2.3.0
	 *
	 * @param string $group_name
	 *
	 * @return object[]
	 */
	public function get_events($group_name) {
		$access_token = $this->core->options->get_access_token();
		if (empty($access_token)) {
			return array();
		}
		$past       = $this->core->options->get_option('past_months');
		$future     = $this->core->options->get_option('future_months');
		$max        = $this->core->options->get_option('max_events');

		/*
		 * Dates:
		 *     - Use the first of the month for the past date
		 *     - Use the negative-first day of the month after the future month to get the last day of the future month
		 *     - `date('c', [...])` gives the ISO 8601 format of the date, which is what Meetup wants
		 */

		$no_earlier_than = date('c', mktime(0, 0, 0, date('m') - $past, 1, date('Y')));
		$no_later_than = date('c', mktime(23, 59, 59, date('m') + $future + 1, -1, date('Y')));

		// Meetup insists on having the timestamps be zero'd out
		$no_earlier_than = preg_replace('/T.*/', 'T00:00:00.000', $no_earlier_than);
		$no_later_than = preg_replace('/T.*/', 'T00:00:00.000', $no_later_than);

		$query_url = sprintf('https://api.meetup.com/%s/events/', urlencode($group_name));
		$params = array(
			'access_token'    => $access_token,
			'no_earlier_than' => $no_earlier_than,
			'no_later_than'   => $no_later_than,
			'scroll'          => 'future_or_past',
		);
		$params_query = '';
		foreach ($params as $k => $v) {
			if ($params_query !== '') {
				$params_query .= '&';
			}
			$params_query .= $k . '=' . urlencode($v);
		}
		$query_url .= '?' . $params_query;

		return $this->remote_get($query_url, array());
	}


	/**
	 * Gets information about the logged-in user
	 *
	 * @since 2.3.0
	 *
	 * @return object|null
	 */
	public function get_member_self() {
		$access_token = $this->core->options->get_access_token();
		if (empty($access_token)) {
			return null;
		}
		$query_url = sprintf('https://api.meetup.com/members/self/?access_token=%s', $access_token);
		return $this->remote_get($query_url, null);
	}


	/**
	 * Perform a GET request for a given URL.
	 *
	 * Errors are stored in @see WPMeetupAPI::$last_errors.
	 *
	 * @since 2.3.0
	 *
	 * @param string     $url
	 * @param mixed|null $value_on_error
	 *
	 * @return array|mixed|object|string|null
	 */
	public function remote_get($url, $value_on_error = null) {
		return $this->remote_query('get', $url, $value_on_error);
	}


	/**
	 * Perform a GET request for a given URL. This does not support POST'ing an array of data to the URL, because
	 * Meetup.com's API allows data to be posted via query parameters.
	 *
	 * Errors are stored in @see WPMeetupAPI::$last_errors.
	 *
	 * @since 2.3.0
	 *
	 * @param string     $url
	 * @param mixed|null $value_on_error
	 *
	 * @return array|mixed|object|string|null
	 */
	public function remote_post($url, $value_on_error = null) {
		return $this->remote_query('post', $url, $value_on_error);
	}


	/**
	 * Performs a query (a GET or POST) on a given URL. Parses through and decodes JSON, records errors, and so on.
	 *
	 * Errors are stored in @see WPMeetupAPI::$last_errors.
	 *
	 * @since 2.3.0
	 *
	 * @param string     $query_type Either "get" or "post"
	 * @param string     $url
	 * @param mixed|null $value_on_error
	 *
	 * @return array|mixed|object|string|null
	 */
	private function remote_query($query_type, $url, $value_on_error = null) {
		// Query type must be either `get` or `post`
		if ($query_type !== 'get' && $query_type !== 'post') {
			return $value_on_error;
		}

		try {
			$response = null;
			if ($query_type == 'get') {
				$response = wp_remote_get($url);
			} else if ($query_type == 'post') {
				$response = wp_remote_post($url);
			}

			if (is_wp_error($response)) {
				/** @var WP_Error $response */
				WPMeetupAPI::record_error($response->get_error_code(), $response->get_error_message(), $url, 'WP_Error returned when attempting a remote get.');
				return $value_on_error;
			}

			$response_code         = wp_remote_retrieve_response_code($response);
			$response_message      = wp_remote_retrieve_response_message($response);
			$response_body         = wp_remote_retrieve_body($response);
			$response_body_decoded = (empty($response_body) ? '' : json_decode($response_body));

			if ($response_body === '[]') {
				return array();
			}

			// If there's trouble decoding the response, record that error (in addition to any other errors we encounter).
			if (json_last_error() != JSON_ERROR_NONE) {
				WPMeetupAPI::record_error(json_last_error(), json_last_error_msg(), $url, 'Could not decode response\'s JSON. Response body:<br><pre>' . esc_html($response_body) . '</pre>');
			}

			// Response code of 200 indicates the request was hunky-dory. Anything else indicates an error.
			if ($response_code != 200) {
				WPMeetupAPI::record_error($response_code, $response_message, $url, 'Received an error code as response to a remote get.');

				if (! empty($response_body_decoded)) {
					$r = (array) $response_body_decoded;
					if (! empty($r['errors'])) {
						foreach ($r['errors'] as $e) {
							WPMeetupAPI::record_error($e->code, $e->message, $url, 'Meetup.com API error details');
						}
					}
				}

				return $value_on_error;
			}

			// Deliver the results (or the $value_on_error, if the JSON decoding failed)
			if (json_last_error() != JSON_ERROR_NONE) {
				return $value_on_error;
			} else {
				// Return either the decoded or the original body
				return (empty($response_body_decoded) ? $response_body : $response_body_decoded);
			}
		} catch (Exception $exception) {
			WPMeetupAPI::record_error($exception->getCode(), $exception->getMessage(), $url, 'PHP Exception encountered at some point during a remote get request.');
			return $value_on_error;
		}
	}



	/**
	 * @deprecated 2.3.0
	 */
	public function get_results($parameters = array(), $category = 'events', $type = 'get') {
		$access_token = $this->core->options->get_access_token();
		if (empty($access_token)) {
			return array();
		}

		if (is_string($parameters)) {
			$past       = $this->core->options->get_option('past_months');
			$future     = $this->core->options->get_option('future_months');
			$max        = $this->core->options->get_option('max_events');
			$parameters = array(
				'group_urlname' => $parameters,
				'time'          => '-' . $past . 'm,' . $future . 'm',
				'page'          => $max,
			);
		}

		if ($category === 'events') {
			/**
			 * Set defaults and merge parameters
			 */
			$defaults = array(
				'status'        => 'past,upcoming',
				'time'          => '-1m,3m',
				'page'          => '100',
				'group_urlname' => '',
			);
			$settings = array_merge($defaults, $parameters);
			$url      = 'https://api.meetup.com/2/events.json?access_token=' . $access_token;

			foreach ($settings as $key => $value) {
				$url .= '&' . $key . '=' . $value;
			}
		} else if ($category == 'groups') {
			/**
			 * Set defaults and merge parameters
			 */
			$defaults = array(
				'group_urlname' => '',
			);
			$settings = array_merge($defaults, $parameters);

			$url = 'https://api.meetup.com/2/groups.json?access_token=' . $access_token;

			foreach ($settings as $key => $value) {
				$url .= '&' . $key . '=' . $value;
			}
		}
		$body = $this->remote_get($url);
		$body = json_decode($body);

		return $body;
	}
}
