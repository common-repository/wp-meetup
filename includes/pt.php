<?php

// Exit if accessed directly
if (! defined('ABSPATH')) {
	exit;
}

class WPMeetupPostType extends NMCustomPost {

	/**
	 * @var WPMeetup
	 */
	var $core;

	/**
	 * @var STRING
	 */
	var $pt;

	/**
	 * @var WPMeetupPostsDB
	 */
	var $post_db;

	/**
	 * @param WPMeetup $core
	 */
	public function __construct($core) {
		$this->core    = $core;
		$this->pt      = $core->post_type;
		$this->post_db = $this->core->post_db;
		parent::__construct();
	}
}
