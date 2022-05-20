<?php

/**
 * Update image alt text in a post
 * @category class
 * @package Coyote\Handlers\PostUpdateHandler
 * @since 1.0
 */

namespace Coyote\Handlers;

use Coyote\Traits\Logger;
use Coyote\ContentHelper;
use Coyote\Payload\CreateResourcePayload;
use Coyote\Payload\CreateResourcesPayload;
use Coyote\PluginConfiguration;
use Coyote\WordPressCoyoteApiClient;
use Coyote\WordPressImage;
use Exception;

class PostUpdateHandler
{
    use Logger;

    public static function run(array $data, array $postArr): array
    {
        $post_id = $postArr['ID'];

        if ($postArr['post_type'] === 'revision') {
            self::logDebug("Post update of $post_id is for revision, skipping");
            return $data;
        }

        try {
            self::process(wp_unslash($data['post_content']), $post_id);
        } catch (Exception $error) {
            self::logDebug("Error processing post update for $post_id: " . $error->getMessage());
        }

        return $data;
    }

    private static function process(string $content, string $post_id): void
    {
        self::logDebug("Processing update on post " . $post_id);

        $permalink = get_permalink($post_id);
        $helper = new ContentHelper($content);

        $images = $helper->getImages();

        if (count($images) === 0) {
            return;
        }

        // attachments will already have been handled by the media manager
        // so those don't need any processing here
        $images = array_map(function (ContentHelper\Image $image) use ($permalink) {
            $image = new WordPressImage($image);
            $image->setHostUri($permalink);
            return $image;
        }, $images);

        $payload = new CreateResourcesPayload();

        foreach ($images as $image) {
            $payload->addResource(new CreateResourcePayload(
                $image->getCaption() ?? $image->getUrl(),
                $image->getUrl(),
                PluginConfiguration::getApiResourceGroupId(),
                $image->getHostUri(),
            ));
        }

        WordPressCoyoteApiClient::createResources($payload);
    }
}
