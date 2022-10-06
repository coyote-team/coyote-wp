<?php

namespace Coyote;

use Coyote\ContentHelper\Image;
use Coyote\Model\ResourceModel;
use Coyote\Payload\CreateResourcePayload;
use Coyote\Payload\CreateResourcesPayload;
use Coyote\Traits\Logger;
use WP_Post;

if (!defined('WPINC')) {
    exit;
}

class BatchImportHelper
{
    use Logger;

    public static function clearBatchJob(): void
    {
        delete_transient('coyote_batch_job');
        delete_transient('coyote_batch_offset');
    }

    public static function setBatchJob($id, $type): void
    {
        set_transient('coyote_batch_job', ['id' => $id, 'type' => $type]);
    }

    public static function getBatchJob(): ?array
    {
        $stored = get_transient('coyote_batch_job');
        if ($stored === false) {
            return null;
        }

        return $stored;
    }

    public static function getProcessBatch($size): array
    {
        $postTypes = PluginConfiguration::getProcessedPostTypes();
        $postStatuses = ['inherit', 'publish'];

        if (PluginConfiguration::isProcessingUnpublishedPosts()) {
            $postStatuses = array_merge($postStatuses, ['pending', 'draft', 'private']);
        }

        $offset = get_transient('coyote_batch_offset');

        $response = [];

        if ($offset === false) {
            $offset = 0;

            $totalPosts = array_reduce($postTypes, function ($carry, $type) use ($postStatuses) {
                $counts = wp_count_posts($type);

                foreach ($postStatuses as $status) {
                    if (property_exists($counts, $status)) {
                        $carry += $counts->$status;
                    }
                }

                return $carry;
            }, 0);

            $response['total'] = $totalPosts;
        }

        $batch = get_posts(array(
            'order' => 'ASC',
            'order_by' => 'ID',
            'offset' => $offset,
            'numberposts' => $size,
            'post_type' => $postTypes,
            'post_status' => $postStatuses,
            'post_parent' => null,
        ));

        $resources = self::createResources($batch, PluginConfiguration::isNotProcessingUnpublishedPosts());

        $response['size'] = count($batch);
        $response['resources'] = count($resources);

        if (count($batch) === 0) {
            // no more posts
            delete_transient('coyote_batch_offset');
        } else {
            set_transient('coyote_batch_offset', $offset + count($batch));
        }

        return $response;
    }

    private static function addAttachmentResourceToPayload(
        CreateResourcesPayload $payload,
        int                    $resourceGroupId,
        bool                   $skipUnpublishedParentPost,
        WP_Post                $post
    ): CreateResourcesPayload {
        // attachment with mime type image, get alt and caption differently
        $alt = get_post_meta($post->ID, '_wp_attachment_image_alt', true);

        if ($post->post_status === 'inherit' && $post->post_parent) {
            // child of a page
            $parentPost = get_post($post->post_parent);

            // only process images in published posts
            if ($parentPost && $parentPost->post_status !== 'publish' && $skipUnpublishedParentPost) {
                return $payload;
            }

            $host_uri = get_permalink($parentPost);
        } else {
            $host_uri = get_permalink($post);
        }

        $attachmentUrl = WordpressHelper::getAttachmentURL($post->ID);

        if (is_null($attachmentUrl)) {
            return $payload;
        }

        $image = new WordPressImage(
            new Image($attachmentUrl, $alt, '')
        );
        $image->setHostUri($host_uri);
        $image->setCaption($post->post_excerpt);

        $payload->addResource(new CreateResourcePayload(
            $image->getCaption() ?? $image->getUrl(),
            $image->getUrl(),
            $resourceGroupId,
            $host_uri
        ));

        return $payload;
    }

    private static function postIsImageAttachment(WP_Post $post): bool
    {
        return $post->post_type === 'attachment' && strpos($post->post_mime_type, 'image/') === 0;
    }

    /** @return ResourceModel[] */
    private static function createResources($posts, $skipUnpublishedParentPost): array
    {
        $resourceGroupId = PluginConfiguration::getApiResourceGroupId();
        $payload = new CreateResourcesPayload();

        foreach ($posts as $post) {
            if (self::postIsImageAttachment($post)) {
                $payload = self::addAttachmentResourceToPayload(
                    $payload,
                    $resourceGroupId,
                    $skipUnpublishedParentPost,
                    $post
                );
                continue;
            }

            $helper = new ContentHelper($post->post_content);
            $images = $helper->getImages();
            $hostURI = get_permalink($post);

            foreach ($images as $contentImage) {
                $image = new WordPressImage($contentImage);
                $image->setHostUri($hostURI);
                $payload->addResource(new CreateResourcePayload(
                    $image->getCaption() ?? $image->getUrl(),
                    $image->getUrl(),
                    $resourceGroupId,
                    $hostURI
                ));
            }
        }

        if (count($payload->resources) === 0) {
            return [];
        }

        $results = WordPressCoyoteApiClient::createResources($payload);

        if (is_null($results)) {
            self::logWarning('Null response while creating resources', ['payload', $payload]);
            return [];
        }

        return $results;
    }
}
