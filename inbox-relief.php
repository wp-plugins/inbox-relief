<?php
/*
Plugin Name: Inbox Relief
Plugin URI: http://www.trevorfitzgerald.com/projects/inbox-relief/
Description: Email batcher that takes all the emails that would normally get sent out individually, compiles them into just one email, then sends it out once a day.  A daily digest, if you will.
Author: Trevor Fitzgerald
Version: 0.2
Author URI: http://www.trevorfitzgerald.com/
*/

class inbox_relief {

	function start() {
		add_filter('wp_mail', array(&$this, 'cache_email'));
		add_action('ir_send_hook', array(&$this, 'cron_send'));

		if ( is_admin() ) {
			add_action('admin_menu', array(&$this, 'options_menu'));
			register_activation_hook( basename(__FILE__), array(&$this, 'activate') );
			register_deactivation_hook( basename(__FILE__), array(&$this, 'deactivate') );
		}
	}

	function activate() {
		add_option('ir_mail_count', 0);
		add_option('ir_mail_cache', '', '', 'no');
		if ( !wp_next_scheduled('ir_send_hook') )
			wp_schedule_event( mktime(0,0,0), 'daily', 'ir_send_hook' );
	}

	function deactivate() {
		// send out any emails that might still be cached
		$this->send_mail();
		// hide our tracks
		delete_option('ir_mail_count');
		delete_option('ir_mail_cache');
		wp_clear_scheduled_hook('ir_send_hook');
	}

	function cache_email($mail) {
		extract($mail);
		// only do this for the admin emails, and make sure we don't get caught in a vicious cycle
		if ( !strpos($subject, "Daily Emails") && !preg_match("/password/i", $subject) && $to == get_option('admin_email') ) {
			$ir_mail_count = get_option('ir_mail_count');
			(int) $ir_mail_count;
			$ir_mail_count++;

			$ir_mail_cache = get_option('ir_mail_cache');
			$ir_mail_cache .= "\n\n\n" . "-------------------- Message #" . $ir_mail_count . " --------------------" . "\n\n" . $message;

			update_option('ir_mail_cache', $ir_mail_cache);
			update_option('ir_mail_count', $ir_mail_count);

			$to = '';
			$subject = '';
			$message = '';
			$headers = '';
		}

		$mail = compact('to', 'subject', 'message', 'headers');
		return $mail;
	}

	function cron_send() {
		$this->send_mail();
	}

	function send_mail() {
		$ir_mail_count = get_option('ir_mail_count');
		(int) $ir_mail_count;
		// no need to continue if there's nothing there
		if ( $ir_mail_count == 0) return;
		$to = get_option('admin_email');
		$subject = get_bloginfo('name') . " Daily Emails for " . gmdate(get_option('date_format'), current_time('timestamp'));
		if ($ir_mail_count == 1)
			$ir_mail_count = "is 1 new message";
		else
			$ir_mail_count = "are " . $ir_mail_count . " new messages";
		$message = "There " . $ir_mail_count . " regarding your blog, " . get_bloginfo('name') . ", in this email.\n\n\n";
		$message .= get_option('ir_mail_cache');
		$message .= "\n\n\n" . "------------------ End of Messages ------------------";

		wp_mail($to, $subject, $message);

		update_option('ir_mail_cache', '');
		update_option('ir_mail_count', 0);
	}

	function ir_suspend() {
		if ( wp_next_scheduled('ir_send_hook') ) {
			echo '<div id="message" class="updated fade"><p>Administrative emails have been temporarily suspended.</p></div>';
			wp_clear_scheduled_hook('ir_send_hook');
		} else {
			echo '<div id="message" class="updated fade"><p>Administrative emails have been re-enabled.</p></div>';
			wp_schedule_event( mktime(0,0,0), 'daily', 'ir_send_hook' );
		}
	}

	function options_menu() {
		if (function_exists('add_options_page')) {
			add_options_page('Inbox Relief', 'Inbox Relief', 8, basename(__FILE__), array(&$this, 'options_page'));
		}
	}

	function options_page() {
		if ( function_exists('current_user_can') && current_user_can('manage_options') ) {
			if (isset($_POST['ir_send_mail'])) {
				$ir_mail_count = get_option('ir_mail_count');
				(int) $ir_mail_count;
				if ( $ir_mail_count > 0) {
					$this->send_mail();
					echo '<div id="message" class="updated fade"><p>WordPress has just sent the email to <strong>' . get_option('admin_email') . '</strong>.</p></div>';
				} else
					echo '<div id="message" class="error fade"><p>WordPress did not send the email because there are no new messages.</p></div>';
			} elseif ( isset($_POST['ir_suspend']) ) {
					$this->ir_suspend();
			}
				echo '<div class="wrap">';
				echo '<h2>Inbox Relief Plugin</h2>';
				echo '<p>This plugin will take all the emails that would normally get sent out individually, compiles them into just one email, then sends it out once a day.  A daily digest, if you will.  It includes anything sent to the administrator\'s email address (i.e. new comments, comments awaiting moderation, new user registrations, etc.).</p><p>There are currently <strong>' . get_option('ir_mail_count') . '</strong> messages queued for sending.</p>';
				//if ( $ir_mail_count > 0) //only show if there's something to send?
					echo '<p>If you would like, you can have those emails sent right now.</p>
						<form action="#" method="post">
						<p><input name="ir_send_mail" type="submit" value="Send Now!" /></p></form>';

				echo '<h2>Suspend/Re-Enable Emails</h2>';
				echo '<form action="#" method="post">';
				if ( wp_next_scheduled('ir_send_hook') ) {
					echo '<p>You can temporarily suspend further administrative emails. When suspended, emails will be held in the queue until manually released.</p>';
					echo '<p><input name="ir_suspend" type="submit" value="Suspend" /></p>';
				} else {
					echo '<p>Admninistrative emails are currently suspended. You can re-enable them now.</p>';
					echo '<p><input name="ir_suspend" type="submit" value="Re-Enable" /></p>';
				}

				echo '</form></div>';
		} else {
			echo '<div class="wrap"><p>Sorry, you are not allowed to access this page.</p></div>';
		}
	}

}

$inbox_relief = new inbox_relief();
$inbox_relief->start();

?>
