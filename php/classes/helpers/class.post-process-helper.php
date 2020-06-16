<?php

namespace Coyote\Helpers;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use Coyote\DB;
use Coyote\Helpers\ContentHelper;
use Coyote\Helpers\PostHelper;
use Coyote\Logger;

class PostProcessHelper {

    public static function restore_images() {
        $post_ids = DB::get_edited_post_ids();
        $amount = count($post_ids);

        Logger::log("Restoring images in {$amount} posts");

        foreach ($post_ids as $post_id) {
            if (PostHelper::is_locked($post_id)) {
                Logger::log("{$post_id} is locked for editing, skipping restore");
                continue;
            }

            try {
                PostHelper::lock($post_id);
                self::process($post_id);
            } catch (Exception $error) {
                Logger::log("Error restoring images in post {$post_id}: " . $error->get_error_message());
            } finally {
                PostHelper::unlock($post_id);
            }
        }
    }

    public static function process($post_id) {
        Logger::log("Restoring images in post {$post_id}");

        $post = get_post($post_id);

        if (!$post) {
            throw new Exception("Unable to get post {$post_id}");
        }

        $resources = DB::get_resources_for_post($post_id);
        $helper = new ContentHelper($post->post_content);

        foreach ($resources as $resource) {
            $helper->restore_resource($resource->coyote_resource_id, $resource->original_description);
        }

        $post->post_content = $helper->get_content();

        wp_update_post($post);
        wp_save_post_revision($post_id);
    }
}

