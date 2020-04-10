<?php
/**
 * @package Coyote
 * @version 0.0.1
 */

/*
Plugin Name: Coyote
Plugin URI: http://wordpress.org/plugins/coyote/
Description: Integrate with a Coyote API to obtain media text descriptions.
Author: Prime Access Consulting | Job van Achterberg
Version: 0.0.1
Author URI: https://www.pac.bz
 */

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

error_log('Loading Coyote plugin');

global $wpdb;

DEFINE('WP_DEBUG', true);
DEFINE('WP_DEBUG_LOG', true);

define('COYOTE_PLUGIN_PATH', plugin_dir_path( __FILE__ ));

DEFINE('COYOTE_IMAGE_TABLE_NAME', $wpdb->prefix . '_coyote_image_resource');
DEFINE('COYOTE_JOIN_TABLE_NAME', $wpdb->prefix . '_coyote_resource_post_jt');
DEFINE('COYOTE_VERSION', '0.0.1');

function coyote_plugin_file(string $path) {
    return COYOTE_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . $path;
}

require_once coyote_plugin_file('classes/class.plugin.php');

use Coyote\Plugin;

global $coyote_plugin;
$coyote_plugin = new Plugin(__FILE__, COYOTE_VERSION);
