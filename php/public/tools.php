<?php

function wp_root_file($file) {
    $ds = DIRECTORY_SEPARATOR;
    $prev = '..' . $ds;
    return __DIR__ . $ds . str_repeat($prev, 5) . $file;
}

function plugin_file($file) {
    $ds = DIRECTORY_SEPARATOR;
    $prev = '..' . $ds;
    return plugin_dir_path(__FILE__) . $ds . str_repeat($prev, 2) . $file;
}

// load wordpress functions
require_once wp_root_file('wp-load.php');

$nonce = $_POST['_wpnonce'];

if (!current_user_can('manage_options') || !wp_verify_nonce($nonce, 'coyote_fields-options')) {
    wp_die(
        '<h1>' . __('You need a higher level of permission.', COYOTE_PLUGIN_NAME) . '</h1>' .
        '<p>' . __('Sorry, you are not allowed to manage options for this site.', COYOTE_PLUGIN_NAME) . '</p>',
        403
    );
}

// required for wp_check_post_lock() used by the plugin
require_once wp_root_file('wp-admin/includes/post.php');

require_once plugin_file('coyote.php');

switch ($_POST['tool']) {
    case 'process_existing_posts':
        global $coyote_plugin;

        $coyote_plugin->process_existing_posts();

	// set a flash message
	set_transient('coyote_posts_processed', true, 10);
    break;
}

wp_redirect(wp_get_referer());

exit;
