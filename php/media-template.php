<?php

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

add_action('wp_enqueue_media', function() {
    if (!remove_action( 'admin_footer', 'wp_print_media_templates')) {
        error_log("remove_action fail");
    }
    add_action('admin_footer', 'my_print_media_templates');
} );

function my_print_media_templates() {
    $replacements = array(
        '/aria-describedby="alt-text-description"\s+\/>/' => 'aria-describedby="alt-text-description" readonly />',
        '/<p class="description" id="alt-text-description">.*?<\/p>/' => '<p class="description" id="alt-text-description">' . __("Managed by Coyote", 'coyote') . '</p>',
    );

    // start output buffering
    ob_start();

    wp_print_media_templates();

    // run regexes on buffered output
    echo preg_replace(array_keys($replacements), array_values($replacements), ob_get_clean());
}
