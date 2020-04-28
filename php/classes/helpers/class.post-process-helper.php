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

    public static function processExistingPost($post) {
        if (wp_check_post_lock($post->ID)) {
            Logger::log("Post {$post->ID} is locked for editing.");
            return;
        }

        $result = wp_update_post($post, true);

        if (is_wp_error($result)) {
            Logger::log("Couldn't process post {$post->ID}: {$result->get_error_message()}");
        } else {
            Logger::log("Processed post {$post->ID}.");
        }
    }

    public static function processPostContent(string $postContent, string $postID) {
        Logger::log("Processing update on post " . $postID);

        $helper = new ContentHelper($postContent);
        $images = $helper->get_images_with_attributes();
        $resources = array();

        foreach ($images as $image) {
            if ($image["data-coyote-id"] !== null) {
                // already linked
                continue;
            }

            $resource = new ImageResource($image);

            // The retrieval or creation of the coyote resource was successful
            if ($resource->coyote_resource_id !== null) {
                $alt = $resource->coyote_description !== null ? $resource->coyote_description : "";
                $element = $helper->set_coyote_id_and_alt($image["element"], $resource->coyote_resource_id, $alt);
                array_push($resources, $resource);
            }
        }

        if (!$helper->content_is_modified) {
            Logger::log("No modifications made, done.");
            return (object) array(
                "content" => $helper->get_content(),
                "modified" => false
            );
        }

        //if the post update succeeded, then associate the resources with the post
        DB::associate_resources_with_post($resources, $postID);

        return (object) array(
            "content" => $helper->get_content(),
            "modified" => true
        );
    }

}
