<?php
/*
Plugin Name: Coyote
Description: Integrate with a Coyote API to obtain media text descriptions.
Plugin URI: http://wordpress.org/plugins/coyote/
Author: Prime Access Consulting
Version: 1.0.0
Author URI: https://www.pac.bz
 */

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

if (PHP_MAJOR_VERSION === null || PHP_MAJOR_VERSION < 7) {
    error_log('Coyote plugin requires at least PHP 7.0');
    return;
}

global $wp_version;
if (!version_compare($wp_version, '5.0.0', '>=')) {
    error_log('Coyote plugin requires at least WordPress 5.0.0');
    return;
}

require_once('vendor/autoload.php');
require_once('php/media-template.php');

define('COYOTE_PLUGIN_NAME', 'coyote');
define('COYOTE_I18N_NS', 'coyote');
define('COYOTE_PLUGIN_FILE', __FILE__);
define('COYOTE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('COYOTE_VERSION', '0.0.1');

/**
 * @param string $path
 * @return string
 */
function coyote_plugin_file(string $path) {
    return _coyote_file('php', $path);
}

/**
 * @param string $path
 * @return string
 */
function coyote_sql_file(string $path) {
    return _coyote_file('sql', $path);
}

/**
 * @param string $path
 * @return string
 */
function coyote_asset_url(string $path) {
    return plugin_dir_url(__FILE__) . DIRECTORY_SEPARATOR . 'asset' . DIRECTORY_SEPARATOR . $path;
}

/**
 * @param string $type
 * @param string $path
 * @return string
 */
function _coyote_file(string $type, string $path) {
    return COYOTE_PLUGIN_PATH . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $path;
}

/**
 * @param int $attachment_id
 * @return string
 */
function coyote_attachment_url($attachment_id) {
    $url = wp_get_attachment_url($attachment_id);

    $parts = wp_parse_url($url);

    if ($parts) {
        return false;
    }

    return 'https://' . $parts['host'] . rawurlencode($parts['path']);
}

require_once coyote_plugin_file('classes/class.plugin.php');

use Coyote\Plugin;

global $coyote_plugin;
$coyote_plugin = new Plugin(COYOTE_PLUGIN_FILE, COYOTE_VERSION, is_admin());
