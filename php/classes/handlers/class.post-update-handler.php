<?php

/**
 * Update image alt text in a post
 * @category class
 * @package Coyote\Handlers\PostUpdateHandler
 * @since 1.0
 */

namespace Coyote\Handlers;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use Coyote\Logger;
use Coyote\Helpers\ContentHelper;
use Coyote\CoyoteResource;
use Exception;

class PostUpdateHandler {

    public static function run(array $data, array $postArr) {
        $post_id = $postArr['ID'];

        if ($postArr['post_type'] == 'revision') {
            Logger::log("Post update of {$post_id} is for revision, skipping");
            return $data;
        }

        try {
            self::process(wp_unslash($data['post_content']), $post_id);
        } catch (Exception $error) {
            Logger::log("Error processing post update for {$post_id}: " . $error->getMessage());
        }

        return $data;
    }

    public static function process($content, $post_id) {
        Logger::log("Processing update on post " . $post_id);

        $permalink = get_permalink($post_id);
        $helper = new ContentHelper($content);

        // attachments with already have been handled by the media manager
        // so those don't need any processing here
        $images = array_map(function ($image) use ($permalink) {
            $image['host_uri'] = $permalink;
            return $image;
        }, $helper->get_images());

        CoyoteResource::resources_from_images($images);
    }

}
