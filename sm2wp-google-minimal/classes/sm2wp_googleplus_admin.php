<?php

@define('WP_POST_REVISIONS', 3);

class SM2WP_GooglePlus_Admin {

	protected static $instance = null;

	private function __construct() {

//		$plugin = SM2WP_GooglePlus::get_instance();
		$this->slug = SM2WP_GooglePlus::$slug;

		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'exec_on_admin' ) );
		add_action( 'admin_notices', array( $this, 'notice_for_admin' ) );

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

	public static function get_instance() {
        if ( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	public function add_plugin_admin_menu() {
		add_options_page(__( 'SM2WP / Google+ Settings', $this->slug ),
		                 __( 'SM2WP / Google+', $this->slug ),
			             'manage_options',
			             $this->slug,
			             array( $this, 'show_admin' )
		);

	}

	public function show_admin() {
        require_once(plugin_dir_path(dirname(__FILE__)).'views/admin.php' );
		// include_once( 'views/admin.php' );
	}

	public function notice_for_admin() {
		foreach (get_option('gfw_info', array()) as $info) {
?>
			<div class="updated">
				<p><b style='margin-right:20px;'>SM2WP / Google+</b><?php _e( $info ); ?></p>
			</div>
<?php
		}

		foreach (get_option('gfw_errors', array()) as $error) {
		?>
			<div class="error">
				<p><b style='margin-right:20px;'>SM2WP / Google+</b><?php _e( $error ); ?></p>
			</div>
		<?php
		}
		update_option('gfw_info', array());
		update_option('gfw_errors', array());
	}

	public function exec_on_admin() {
		require_once( plugin_dir_path(dirname(__FILE__)).'classes/google_plus.php' );

		if (@$_GET['page'] == $this->slug) {
			if (key_exists('access_token', $_GET)) {
				$p = new SM2WP_GooglePlus_Library($_GET['id'], $_GET['access_token'], $_GET['network_id']);
				if ($p->update_profile())
 				{
					if ($p->save_profile()) {
						log_info('Successfully added new profile for "'.$p->name.'"');
					}
					else {
						log_info('Unable to add profile as it already exists.');
					}
				} else {
					log_info('Unable to add profile as it could not be retrieved.');
				}
				wp_redirect( admin_url( "options-general.php?page=$_GET[page]" ) );
				exit();

			} else if (key_exists('del', $_GET)) {
				if (SM2WP_GooglePlus_Library::find_profile_by_id($_GET['del'])) {
					SM2WP_GooglePlus_Library::delete_profile_by_id($_GET['del']);
					log_info('Profile was deleted as requested.');
					wp_redirect( admin_url( "options-general.php?page=$_GET[page]" ) );
					exit();
				}
			} else if (key_exists('run', $_GET)) {
				// wp_schedule_single_event(time()+10, 'gfw_import');
				$this->run();
				wp_redirect( admin_url( "options-general.php?page=$_GET[page]" ) );
				exit();
			}

		}

		register_setting('gfw_profiles', 'gfw_profiles');
    register_setting('gfw_api', 'gfw_api_key', array($this, 'api_key_resolution_changed'));
		register_setting('gfw_import_settings', 'gfw_history');
		register_setting('gfw_import_settings', 'gfw_comments');
		register_setting('gfw_import_settings', 'gfw_overwrite');
		register_setting('gfw_import_settings', 'gfw_import_trashed');
		register_setting('gfw_import_settings', 'gfw_remove_hashtags');
		register_setting('gfw_import_settings', 'gfw_featured_images');
		register_setting('gfw_import_settings', 'gfw_max_resolution', array($this, 'max_resolution_changed'));
		register_setting('gfw_import_settings', 'gfw_import_tags');
		register_setting('gfw_import_settings', 'gfw_ignore_tags');
		register_setting('gfw_import_settings', 'gfw_ignore_canonical');
		register_setting('gfw_import_settings', 'gfw_schedule', array($this, 'schedule_changed'));
		register_setting('gfw_defaults', 'gfw_post_status');
		register_setting('gfw_defaults', 'gfw_post_categories', array($this, 'post_categories_changed'));
		register_setting('gfw_defaults', 'gfw_post_tags');
		register_setting('gfw_template', 'gfw_template');

	}

    public function api_key_resolution_changed($api_key) {
      if (!trim($api_key)) {
        if (get_option('gfw_history') > 100) update_option('gfw_history', 100);
        if (get_option('gfw_comments')) update_option('gfw_comments', false);
      }
      return trim($api_key);
    }

    public function max_resolution_changed($max_resolution) {
        if (!trim($max_resolution)) $max_resolution = '1024';
        return $max_resolution;
    }

    public function post_categories_changed($categories) {
        if (!is_array($categories)) $categories = array($categories);
        return array_filter($categories);
    }

    public function schedule_changed($schedule) {
        if ($schedule && ($schedule != get_option('gfw_schedule'))) {
            wp_clear_scheduled_hook('gfw_import');
            wp_schedule_event(time()+30, $schedule, 'gfw_import');
        }
        return $schedule;
    }

}
