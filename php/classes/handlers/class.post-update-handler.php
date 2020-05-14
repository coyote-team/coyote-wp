<?php

namespace Coyote\Handlers;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use Coyote\BatchPostProcessorState;
use Coyote\Logger;
use Coyote\DB;
use Coyote\Helpers\ContentHelper;
use Coyote\ImageResource;

class PostUpdateHandler {

    public static function run(array $data, array $postArr) {
        $post_id = $postArr['ID'];

        if ($postArr['post_type'] == 'revision') {
            Logger::log("Post update of {$post_id} is for revision, skipping");
            return $data;
        }

        if (BatchPostProcessorState::exists() && $state = BatchPostProcessorState::load()) {
            if ($state->current_post_id() === $post_id) {
                Logger::log("Firing PostUpdateHandler while processing existing post {$post_id}, skipping");
                return $data;
            }
        }

        try {
            $processed = self::process(wp_unslash($data['post_content']), $post_id);
            $data['post_content'] = wp_slash($processed);
        } catch (Exception $error) {
            Logger::log("Error processing post update for {$post_id}: " . $error->get_error_message());
        }

        return $data;
    }

    public static function process($content, $post_id) {
        Logger::log("Processing update on post " . $post_id);

        $helper = new ContentHelper($content);
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
                array_push($associated, $resource->coyote_resource_id);
            }
        }

        if (!$helper->content_is_modified) {
            Logger::log("No modifications made, done.");
            return $helper->get_content();
        }

        //if the post update succeeded, then associate the resources with the post
        DB::associate_resource_ids_with_post($associated, $post_id);

        return $helper->get_content();
    }

}
