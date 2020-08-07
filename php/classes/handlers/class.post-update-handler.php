<?php

namespace Coyote\Handlers;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use Coyote\Batching;
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

        if (Batching::is_processing($post_id)) {
            Logger::log("Firing PostUpdateHandler while processing existing post {$post_id}, skipping");
            return $data;
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
        $images = $helper->get_images();

        ImageResource::resources_from_images($images);

        return $content;
    }

}
