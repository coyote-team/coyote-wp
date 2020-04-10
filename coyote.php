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
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

DEFINE('COYOTE_IMAGE_TABLE_NAME', $wpdb->prefix . '_coyote_image_resource');
DEFINE('COYOTE_JOIN_TABLE_NAME', $wpdb->prefix . '_coyote_resource_post_jt');
DEFINE('COYOTE_VERSION', '0.0.1');

DEFINE('WP_DEBUG', true);

require_once 'php/classes/class.plugin.php';

use Coyote\Plugin;

global $coyote_plugin;
$coyote_plugin = new Plugin(__FILE__, COYOTE_VERSION);
