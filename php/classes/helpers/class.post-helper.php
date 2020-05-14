<?php

namespace Coyote\Helpers;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

class PostHelper {
    public static function is_locked(int $post_id) {
        return wp_check_post_lock($post_id) !== false;
    }

    public static function lock(int $post_id) {
        return wp_set_post_lock($post_id);
    }

    public static function unlock(int $post_id) {
        // inferred from https://developer.wordpress.org/reference/functions/wp_ajax_wp_remove_post_lock/
        return delete_post_meta($post_id, '_edit_lock');
    }
}

