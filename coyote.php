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
define('COYOTE_VERSION', '0.0.1');
define('COYOTE_HOOK_MEDIA_SCREENS', ['post', 'page', 'upload', 'ch_events']);

/**
 * @param string $path
 * @return string
 */
function coyote_asset_url(string $path): string
{
    return plugin_dir_url(__FILE__) . DIRECTORY_SEPARATOR . 'asset' . DIRECTORY_SEPARATOR . $path;
}


(new WordPressPlugin(COYOTE_PLUGIN_FILE));
