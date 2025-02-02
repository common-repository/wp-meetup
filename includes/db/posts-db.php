<?php

// Exit if accessed directly
if (! defined('ABSPATH')) {
	exit;
}

class WPMeetupPostsDB extends NMDB {

    var $sqltable = 'posts';

    public function __construct() {
        global $wpdb;
        $this->sqltable = $wpdb->prefix . $this->sqltable;
    }

    function create_update_database() {}
}
