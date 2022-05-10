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

use Coyote\ContentHelper\Image;
use Coyote\Logger;
use Coyote\ContentHelper;
use Coyote\Payload\CreateResourcePayload;
use Coyote\Payload\CreateResourcesPayload;
use Coyote\WordPressCoyoteApiClient;
use Coyote\WordPressImage;
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

        // attachments will already have been handled by the media manager
        // so those don't need any processing here
        /** @var WordPressImage[] $images */
        $images = array_map(function (ContentHelper\Image $image) use ($permalink) {
            $image = new WordPressImage($image);
            return ['caption' => $image->getCaption(),
                'src' => $image->getUrl(),
                'host_uri' => $permalink,
                'alt' => $image->getAlt()];
        }, $helper->getImages());

        $payload = new CreateResourcesPayload();
        foreach ($images as $image) {
            $payload->addResource(new CreateResourcePayload(
                $image->getCaption() ?? $image->getUrl(),
                $image->getUrl(),
                // TODO add Resource Group ID -- PluginConfiguration::getResourceGroupID
                null,
                $image->getHostUri()
            ));
        }

        WordPressCoyoteApiClient::createResources($payload);
    }

}
