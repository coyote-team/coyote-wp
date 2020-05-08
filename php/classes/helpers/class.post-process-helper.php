<?php

namespace Coyote\Helpers;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

require_once coyote_plugin_file('classes/class.logger.php');
require_once coyote_plugin_file('classes/class.db.php');
require_once coyote_plugin_file('classes/class.image-resource.php');
require_once coyote_plugin_file('classes/helpers/class.content-helper.php');

use Coyote\DB;
use Coyote\Helpers\ContentHelper;
use Coyote\Logger;
use Coyote\ImageResource;

class PostProcessHelper {

    public static function restoreImages() {
        $postIds = DB::get_edited_post_ids();
        $amount = count($postIds);

        Logger::log("Restoring images in {$amount} posts");

        foreach ($postIds as $postId) {
            Logger::log("Restoring images in post {$postId}");

            $post = get_post($postId);

            if (!$post) {
                Logger::log("Unable to get post {$postId}, skipping");
                continue;
            }

            $resources = DB::get_resources_for_post($postId);
            $helper = new ContentHelper($post->post_content);

            foreach ($resources as $resource) {
                $helper->restore_resource($resource->coyote_resource_id, $resource->original_description);
            }

            $post->post_content = $helper->get_content();

            wp_update_post($post);
        }
    }
}
