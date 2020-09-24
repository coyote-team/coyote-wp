<?php

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

add_action('wp_enqueue_media', function() {
    global $coyote_plugin;

    // only patch the html when the plugin has an existing profile
    // and is not running in standalone mode
    if (!$coyote_plugin->is_configured || $coyote_plugin->is_standalone) {
        return;
    }

    $screen = get_current_screen();

    if ($screen->id === 'upload') {
        if (!remove_action( 'admin_footer', 'wp_print_media_templates')) {
            error_log("remove_action fail");
        }

        add_action('admin_footer', 'media_manager_print_media_templates');

        return;
    }

    if (!remove_action( 'admin_footer', 'wp_print_media_templates')) {
        error_log("remove_action fail");
    }

    add_action('admin_footer', 'classic_editor_print_media_templates');
} );

function classic_editor_print_media_templates() {
    global $coyote_plugin;

    echo $coyote_plugin->classic_editor_data();

    $replacements = array(
        '/aria-describedby="alt-text-description"\s+\/>/' => 'aria-describedby="alt-text-description" readonly />',
        '/<p class="description" id="alt-text-description">.*?<\/p>/' => '<p class="description" id="alt-text-description">{{{ data.model.coyoteManagementUrl() }}}</p>',
    );

    // start output buffering
    ob_start();

    wp_print_media_templates();

    // run regexes on buffered output
    echo preg_replace(array_keys($replacements), array_values($replacements), ob_get_clean());
}

function media_manager_print_media_templates() {
    $replacements = array(
        '/aria-describedby="alt-text-description"/' => 'aria-describedby="alt-text-description" readonly',
        '/<p class="description" id="alt-text-description">.*?<\/p>/' => '<p class="description" id="alt-text-description"><a href="{{{ data.coyoteManagementUrl }}}">Manage image on Coyote</a></p>',
    );

    // start output buffering
    ob_start();

    wp_print_media_templates();

    // run regexes on buffered output
    echo preg_replace(array_keys($replacements), array_values($replacements), ob_get_clean());
}
