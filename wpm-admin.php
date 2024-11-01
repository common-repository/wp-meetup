<?php

// Exit if accessed directly
if (! defined('ABSPATH')) {
	exit;
}

class WPMeetupAdmin {

    /**
     * @var WPMeetup
     */
    var $core;

    /**
     * @var array
     */
    var $page_array;

    /**
     * @param WPMeetup $core
     */
    public function __construct($core) {
        $this->core = $core;
        add_action('admin_menu', array( &$this, 'add_admin_pages' ));
        add_action('admin_enqueue_scripts', array(&$this, 'load_settings_styles'), 100);
        add_action('all_admin_notices', array(&$this, 'prompt_support'));
    }

    /**
     * Creates each new admin page.
     */
    public function add_admin_pages() {
    	if ($this->core->options->has_access_token()) {
		    new WPMeetupMainAdmin($this->core);
		    new WPMeetupOptionsAdmin($this->core);
		    new WPMeetupGroupsAdmin($this->core);
		    new WPMeetupEventsAdmin($this->core);
		    new WPMeetupDebugAdmin($this->core);
		    new WPMeetupOauth2Page($this->core);
	    } else {
    		// Show only the OAuth2 page
		    new WPMeetupOauth2Page($this->core, true);
	    }
    }

    public function load_settings_styles() {
		wp_register_style('wpm-admin-styles', plugins_url('assets/css/admin-styles.css', __FILE__));
	    wp_register_script('wpm-admin-script', plugins_url('assets/js/nm-dashboard-script.js', __FILE__));
	    wp_enqueue_style('wpm-admin-styles');
	    wp_enqueue_script('wpm-admin-script');
    }

    public function prompt_support() {
        if (!$this->core->options->get_option('support') && intval($this->core->options->get_option('queue_prompt')) != 1 && intval($this->core->options->get_option('queue_prompt')) < time()) {
            ?>
                <div class="nm-support-prompt nm-error">
                    <div class="nm-support-staff-prompt-exit">
                        <form action="" method="post">
                            <input type="hidden" name="update" value="wpm-update-prompt" />
                            <input type="hidden" name="queue_prompt" value="1" />
                            <input type="hidden" name="install_count" value="<?php  echo $this->core->options->get_option('install_count') + 28; ?>" />
                            <input type="submit" title="Close this message" value="X">
                        </form>
                    </div>
                    <div class="nm-support-staff-form">
                        <form action="" method="post">
                            <input type="hidden" name="update" value="wpm-update-support-prompt" />
                            <input type="hidden" name="support" value="checked" />
                            <div class="nm-support-staff-label nm-support-staff-prompt-label">
                                <label>It has been over <?php echo $this->core->options->get_option('install_count'); ?> days, how are you liking WP Meetup? Please consider <a href="http://wordpress.org/extend/plugins/wp-meetup/" target="_blank">reviewing</a> the plugin or adding a small link to the bottom of your calendar page so we can spend more time building this plugin!</label>
                            </div>
                            <input type="submit" class="nm-support-staff-prompt-submit" value="Help Improve WP Meetup" />
                            <div class="clear"></div>
                        </form>
                    </div>
                    <div class="clear"></div>
                </div>

            <?php
        }
    }

}
