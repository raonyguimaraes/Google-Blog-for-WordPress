<?php
/**
 * Plugin Name:     Social Media 2 WordPress for Google+ / Minimal
 * Plugin URI:      http://sm2wp.com
 * Description:     Import your Google+ Posts to your WordPress Blog
 * Version:         1.2.0
 * Author:       	  Daniel Treadwell
 * Author URI:      http://minimali.se
 * License:         GPL-2.0+
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.txt
 */

if (!defined( 'WPINC' )) {
	die;
}

require_once(plugin_dir_path( __FILE__ ).'classes/sm2wp_googleplus.php');

register_activation_hook(__FILE__, array('SM2WP_GooglePlus', 'activate'));
register_deactivation_hook(__FILE__, array('SM2WP_GooglePlus', 'deactivate'));

add_action('plugins_loaded', array('SM2WP_GooglePlus', 'get_instance'));

if (is_admin()) {
	require_once(plugin_dir_path(__FILE__).'classes/sm2wp_googleplus_admin.php');
	add_action('plugins_loaded', array('SM2WP_GooglePlus_Admin', 'get_instance'));
}
