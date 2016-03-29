<?php

require_once(plugin_dir_path(dirname(__FILE__)).'classes/common.php');
require_once(plugin_dir_path(dirname(__FILE__)).'classes/google_plus.php');
@define('WP_POST_REVISIONS', 3);

class SM2WP_GooglePlus {
    public static $slug = 'sm2wp-googleplus';
    protected static $instance = null;

    private function __construct() {
      add_action( 'wpmu_new_blog', array($this, 'activate_new_site'));
      add_filter('get_avatar', array($this, 'avatar_replace'), 10, 5);
      if (!get_option('gfw_ignore_canonical', false)) {
        add_action('wp_head', array($this, 'canonical_add'));
        remove_action('wp_head', 'rel_canonical');
      }

      add_action('gfw_import', array('SM2WP_GooglePlus', 'run'));

    }


    public static function run() {
        $c = $u = $i = 0;
        update_option('gfw_imported_comments', 0);
        foreach (get_option('gfw_profiles', array()) as $id => $profile) {
            if ($profile['author'] && $profile['author'] != '-1') {
                $a = SM2WP_GooglePlus_Library::create_from_array($profile);
                $r = $a->get_posts();
                $c += $r[0];
                $u += $r[1];
                $i += $r[2];
            }
        }
        update_option('gfw_imported_new', $c);
        update_option('gfw_imported_updated', $u);
        update_option('gfw_imported_ignored', $i);

    }

	public function canonical_add()
	{
	    global $post;

	    if (is_single()) {
	        $url = get_post_meta($post->ID, '_gfw_url', true);
	        if ($url)
	            echo "<link rel='canonical' href='$url' />\n";
	        else
	            rel_canonical();
	    }
	}


	public function avatar_replace($avatar, $comment, $size, $default, $alt)
	{
		if (is_object($comment)) {
			if ($comment->comment_author_IP == 'Google+' && $comment->comment_author_email)
				$avatar = "<img alt='$comment->comment_author' src='$comment->comment_author_email' class='avatar avatar-{$size} photo avatar-default' height='{$size}' width='{$size}' />";
			return $avatar;
		}
	}

    public static function get_instance() {
        if (null == self::$instance) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	public static function activate($network_wide) {

		if (function_exists('is_multisite') && is_multisite()) {
			if ($network_wide) {
				$blog_ids = self::get_blog_ids();

				foreach ($blog_ids as $blog_id) {
					switch_to_blog($blog_id);
					self::single_activate();
				}
				restore_current_blog();

			} else {
				self::single_activate();
			}

		} else {
			self::single_activate();
		}

	}

	public static function deactivate( $network_wide ) {

		if (function_exists('is_multisite') && is_multisite()) {
			if ($network_wide) {
				$blog_ids = self::get_blog_ids();
				foreach ($blog_ids as $blog_id) {
					switch_to_blog( $blog_id );
					self::single_deactivate();
				}
				restore_current_blog();
			} else {
				self::single_deactivate();
			}
		} else {
			self::single_deactivate();
		}

	}

	public function activate_new_site($blog_id) {
		if (1 !== did_action('wpmu_new_blog')) {
			return;
		}

		switch_to_blog($blog_id);
		self::single_activate();
		restore_current_blog();
	}

	private static function get_blog_ids() {
		global $wpdb;
		$sql = "SELECT blog_id FROM $wpdb->blogs
			    WHERE archived = '0' AND spam = '0'
			    AND deleted = '0'";
		return $wpdb->get_col($sql);
	}

	private static function single_activate() {
		@wp_clear_scheduled_hook('gfw_import');
    @update_option('gfw_comments', false);
    $schedule = get_option('gfw_schedule');
    if (!in_array($schedule, array('hourly', 'daily', 'twicedaily'))) {
      $schedule = 'hourly';
      update_option('gfw_schedule', 'hourly');
    }
		@wp_schedule_event(time()+30, $schedule, 'gfw_import');
	}

	private static function single_deactivate() {
		@wp_clear_scheduled_hook('gfw_import');
	}
}
