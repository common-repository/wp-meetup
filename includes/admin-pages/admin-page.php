<?php

// Exit if accessed directly
if (! defined('ABSPATH')) {
	exit;
}

class WPMeetupAdminPage {

	/** @var WPMeetup $core */
	var $core;

	var $title = '';

	public function create_page() {
		if (! current_user_can('manage_options')) {
			wp_die(__('You do not have sufficient permissions to access this page.'));
		}
		?>
		<h1>WP Meetup</h1>
		<?php
		if ($this->core->options->has_access_token()) {
			$this->render_tabs();
		}
		$this->core->options->update_messages();
		$this->render_container_open('three-fifths');
		$this->display_page();
		$this->render_container_close();
		$this->render_container_open('two-fifths');
		$this->render_sidebar();
		$this->render_container_close();

		$this->render_api_error_messages();
	}

	public function render_tabs() {
		$tabs = array(
			'wp_meetup_settings' => 'Dashboard',
			'wp_meetup_options'  => 'Options',
			'wp_meetup_groups'   => 'Groups',
			'wp_meetup_events'   => 'Events',
			'wp_meetup_debug'    => 'Debug',
			'wp_meetup_oauth2'   => 'OAuth2',
		);

		// what page did we request?
		$current_slug = '';
		if (isset($_GET['page'])) {
			$current_slug = $_GET['page'];
		}

		// render all the tabs
		$output = '';
		$output .= '<div class="tabs-container">';
		foreach ($tabs as $slug => $label) {
			$output .= '<div class="tab ' . ($slug == $current_slug ? 'active' : '') . '">';
			$output .= '<a href="' . admin_url('admin.php?page=' . $slug) . '">' . $label . '</a>';
			$output .= '</div>';
		}
		$output .= '</div>'; // end .tabs-container

		echo $output;
	}

	public function render_container_open($extra_class = '', $echo = true) {
		$output = '';
		$output .= '<div class="metabox-holder ' . $extra_class . '">';
		$output .= '  <div class="postbox-container nm-postbox-container" style="float: none;">';
		$output .= '    <div class="meta-box-sortables ui-sortable">';

		if ($echo) {
			echo $output;
		} else {
			return $output;
		}
	}

	public function render_container_close($echo = true) {
		$output = '';
		$output .= '</div>'; // end .ui-sortable
		$output .= '</div>'; // end .nm-postbox-container
		$output .= '</div>'; // end .metabox-holder

		if ($echo) {
			echo $output;
		} else {
			return $output;
		}
	}

	public function render_sidebar() {
		if (! $this->core->options->get_option('support')) {
			$this->render_postbox_open('Support the Developers');
			$this->insert_support_box();
			$this->render_postbox_close();
		}

		$this->render_postbox_open('Review Our Plugin');
		$this->insert_review_us();
		$this->render_postbox_close();

		$this->render_postbox_open('Nuanced Media');
		$this->render_nm_logos();
		$this->render_postbox_close();
	}

	public function render_postbox_open($title = '') {
		echo '<div class="postbox">';
		echo '<div class="handlediv" title="Click to toggle"><br/></div>';
		echo '<h3 class="hndle nm-hndle"><span>' . $title . '</span></h3>';
		echo '<div class="inside">';
	}

	public function insert_support_box() {
		?>
			<form method="post" action="">
				<input type="hidden" name="update" value="wpm-update-support"/>
				<p>We thank you for choosing to use our plugin! We would also appreciate it if you allowed us to put our name on the plugin we worked so hard to build. If you are okay with us having a credit line on the calendar, then please check the following and change your permission settings.</p>
				<p><label for="support"><input type="checkbox" name="support" value="checked" <?php echo $this->core->options->get_option('support'); ?> /> Support the Developers</label></p>
				<p class="submit" style="padding: 0;"><input type="submit" class="button button-primary" value="Change Permission Setting"/></p>
			</form>
		<?php
	}

	public function render_postbox_close() {
		echo '</div>'; // end .inside
		echo '</div>'; // end .postbox
	}

	function insert_review_us() {
		?>
		<div class="review-us">
			<p>Tell us your opinion of the plugin. We are continuously working to improve your experience with the Meetup Plugin and we can do that better if we know what you like and dislike. Let us know on the <a href="http://wordpress.org/support/view/plugin-reviews/wp-meetup" target="_blank">WordPress review page</a>.</p>
		</div>
		<?php
	}

	public function render_nm_logos() {
		?>
		<p>
	        <a href="https://nuancedmedia.com/" target="_blank">
	            <img src="https://nuancedmedia.com/wp-content/uploads/2014/04/nm-logo-black.png" style="width: 150px; max-width: 100%"/>
	        </a>
		</p>
		<script>(function(d, s, id) {var js, fjs = d.getElementsByTagName(s)[0];if (d.getElementById(id)) {return;}js = d.createElement(s);js.id = id;js.src = "//connect.facebook.net/en_US/all.js#xfbml=1";fjs.parentNode.insertBefore(js, fjs);}(document, 'script', 'facebook-jssdk'));</script>
		<div class="fb-like" data-href="https://www.facebook.com/NuancedMedia" data-send="false" data-layout="button_count" data-width="100" data-show-faces="true"></div>
		<?php

	}

	/**
	 * If any errors were encountered while querying the API, show them here.
	 *
	 * @since 2.3.0
	 */
	public function render_api_error_messages() {
		if (! is_admin() || ! current_user_can('manage_options')) {
			return;
		}

		$last_errors = WPMeetupAPI::_get_last_errors();
		if (! empty($last_errors)) {
			?>
			<div style="clear: both"></div>
			<div class="notice notice-error is-dismissible" id="wpm_api_errors_container">
				<h2>WP Meetup encountered <?php echo (count($last_errors) == 1 ? 'an error' : 'some errors'); ?> while querying the Meetup.com API</h2>
				<?php foreach ($last_errors as $error): ?>
					<table>
						<tbody>
							<tr>
								<th scope="row" align="left">Action Taken</th>
								<td><?php echo $error['action']; ?></td>
							</tr>
							<tr>
								<th scope="row" align="left">Queried URL</th>
								<td><code><small><?php echo esc_html($error['url']); ?></small></code></td>
							</tr>
							<tr>
								<th scope="row" align="left">Error Code</th>
								<td><?php echo $error['code']; ?></td>
							</tr>
							<tr>
								<th scope="row" align="left">Error Message</th>
								<td><?php echo $error['message']; ?></td>
							</tr>
						</tbody>
					</table>
				<?php endforeach; ?>
			</div>
			<style>
				#wpm_api_errors_container {
					position: fixed;
					box-shadow: 0 0 5px black;
					bottom: 5px;
					left: 5%;
					right: 5%;
					z-index: 10010; /* placed above most other WP admin things */
					max-height: 300px; /* fallback for `vh` unit */
					max-height: 50vh;
					overflow-y: auto;
				}
				#wpm_api_errors_container table {
					border-left: solid 2px #dc3232;
				}
				#wpm_api_errors_container table + table {
					margin-top: 8px;
				}
			</style>
			<?php
		}
	}

}
