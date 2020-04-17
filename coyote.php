<?php
// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

require_once ('vendor/autoload.php');
require_once('php/media-template.php');

error_log('Loading Coyote plugin');

define('COYOTE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('COYOTE_VERSION', '0.0.1');

function coyote_plugin_file(string $path) {
    return _coyote_file('php', $path);
}

function coyote_sql_file(string $path) {
    return _coyote_file('sql', $path);
}

function coyote_asset_file(string $path) {
    return _coyote_file('asset', $path);
}

function _coyote_file(string $type, string $path) {
    return COYOTE_PLUGIN_PATH . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $path;
}

require_once coyote_plugin_file('classes/class.plugin.php');

use Coyote\Plugin;

global $coyote_plugin;
$coyote_plugin = new Plugin(COYOTE_PLUGIN_FILE, COYOTE_VERSION, is_admin());

