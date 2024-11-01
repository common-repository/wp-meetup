<?php

// Exit if accessed directly
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Given a string, returns whether or not the string is json encoded
 *
 * @param string $string
 *
 * @return bool
 */
if (! function_exists('nm_is_json')) {
	function nm_is_json($string) {
		return ((is_string($string) &&
		         (is_object(json_decode($string)) ||
		          is_array(json_decode($string))))) ? true : false;
	}
}


if (! function_exists('nm_shift_unix')) {
	function nm_shift_unix($shift_int, $increment, $time = null) {
		if (is_null($time)) {
			$time = time();
		}
		$date = getdate($time);

		// Increment by DAYS
		if ($increment == 'day') {
			$days  = $shift_int;
			$month = $date['mon'];
			$year  = $date['year'];
			$dim   = cal_days_in_month(CAL_GREGORIAN, $month, $year);
			if (($days + $date['mday']) > $dim) {
				$new_time   = nm_shift_unix(1, 'month', $time);
				$days       = $days - $dim;
				$newer_time = nm_shift_unix($days, 'day', $new_time);
				$date       = getdate($newer_time);
			} else if (($days + $date['mday']) <= 0) {
				$new_time   = nm_shift_unix(- 1, 'month', $time);
				$days       = $days + cal_days_in_month(CAL_GREGORIAN, $month - 1, $year);
				$newer_time = nm_shift_unix($days, 'day', $new_time);
				$date       = getdate($newer_time);
			} else {
				$date['mday'] = $date['mday'] + $days;
			}
		}

		// Increment by WEEKS
		if ($increment == 'week') {
			$new_time = nm_shift_unix($shift_int * 7, 'day', $time);
			$date     = getdate($new_time);
		}

		// Increment by MONTHS
		if ($increment == 'month') {
			$date['mon'] = $date['mon'] + $shift_int;
			if ($date['mon'] > 12) {
				$new_time    = nm_shift_unix(1, 'year', $time);
				$date        = getdate($new_time);
				$date['mon'] = $date['mon'] + $shift_int - 12;
			}
			if ($date['mon'] <= 0) {
				$new_time    = nm_shift_unix(- 1, 'year', $time);
				$date        = getdate($new_time);
				$date['mon'] = $date['mon'] + $shift_int + 12;
			}
		}

		// Increment by YEARS
		if ($increment == 'year') {
			$date['year'] = $date['year'] + $shift_int;
		}

		$return_value = mktime($date['hours'], $date['minutes'], $date['seconds'], $date['mon'], $date['mday'], $date['year']);

		return $return_value;
	}
}
