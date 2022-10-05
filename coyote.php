<?php
/*
Plugin Name: Coyote
Description: Integrate with a Coyote API to obtain media text descriptions.
Plugin URI: http://wordpress.org/plugins/coyote/
Author: Prime Access Consulting
Version: 2.0
Author URI: https://www.pac.bz
 */

use Coyote\WordPressPlugin;

if (!defined('WPINC')) {
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
define('COYOTE_HOOK_MEDIA_SCREENS', ['post', 'page', 'upload', 'ch_events']);
define('COYOTE_TRANSLATION_REL_PATH', dirname(plugin_basename(__FILE__)) . '/languages');

/**
 * @param string $path
 * @return string
 */
function coyote_asset_url(string $path)
{
    return plugin_dir_url(__FILE__) . DIRECTORY_SEPARATOR . 'asset' . DIRECTORY_SEPARATOR . $path;
}

/**
 * @param int $attachment_id
 * @return string
 */
function coyote_attachment_url($attachment_id)
{
    $url = wp_get_attachment_url($attachment_id);

    $parts = wp_parse_url($url);

    if ($parts === false) {
        return false;
    }

    return '//' . $parts['host'] . esc_url($parts['path']);
}

(new WordPressPlugin(COYOTE_PLUGIN_FILE));
