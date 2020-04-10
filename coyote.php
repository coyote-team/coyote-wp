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

use Coyote\Plugin;

global $coyote_plugin;
$coyote_plugin = new Plugin(__FILE__, '0.0.1');
