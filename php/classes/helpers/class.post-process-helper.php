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

    public static function processExistingPost($post, $images, $api_resources) {
        if (wp_check_post_lock($post->ID)) {
            Logger::log("Post {$post->ID} is locked for editing.");
            return;
        }

        $resources = array_reduce($images, function($carry, $image) use ($api_resources) {
            if ($image["data-coyote-id"] !== null) {
                return $carry;
            }

            $api_resource = array_key_exists($image['src'], $api_resources)
                ? $api_resources[$image['src']]
                : null
            ;

            $post_resource = new ImageResource($image, $api_resource);

            array_push($carry, $post_resource);

            return $carry;
        }, array());

        $helper = new ContentHelper($post->post_content);
        $associated = array();

        foreach ($resources as $resource) {
            // The retrieval or creation of the coyote resource was successful
            if ($resource->coyote_resource_id !== null) {
                $alt = $resource->coyote_description !== null ? $resource->coyote_description : "";
                $helper->set_coyote_id_and_alt($resource->image["element"], $resource->coyote_resource_id, $alt);
                array_push($associated, $resource);
            }
        }

        if (!$helper->content_is_modified) {
            Logger::log("No modifications made, done.");
            return;
        }

        $post->post_content = $helper->get_content();
        $result = wp_update_post($post, true);

        if (is_wp_error($result)) {
            Logger::log("Couldn't process post {$post->ID}: {$result->get_error_message()}");
        } else {
            Logger::log("Processed post {$post->ID}.");
            //if the post update succeeded, then associate the resources with the post
            DB::associate_resources_with_post($associated, $post->ID);
        }
    }

    public static function processPostContent(string $postContent, string $postID) {
        Logger::log("Processing update on post " . $postID);

        $helper = new ContentHelper($postContent);
        $images = $helper->get_images_with_attributes();

        $resources = ImageResource::map($images);
        $associated = array();

        foreach ($resources as $resource) {
            if ($resource->image["data-coyote-id"] !== null) {
                //already linked
                continue;
            }

            // The retrieval or creation of the coyote resource was successful
            if ($resource->coyote_resource_id !== null) {
                $alt = $resource->coyote_description !== null ? $resource->coyote_description : "";
                $helper->set_coyote_id_and_alt($resource->image["element"], $resource->coyote_resource_id, $alt);
                array_push($associated, $resource);
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
        DB::associate_resources_with_post($associated, $postID);

        return (object) array(
            "content" => $helper->get_content(),
            "modified" => true
        );
    }

}
