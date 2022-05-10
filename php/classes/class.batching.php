<?php

/**
 * Coyote Batch Processing
 * @category class
 * @package Coyote\Batching
 * @since 1.0
 */

namespace Coyote;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use Coyote\ContentHelper;
use Coyote\Model\ResourceModel;
use Coyote\Payload\CreateResourcePayload;
use Coyote\Payload\CreateResourcesPayload;
use Coyote\WordPressImage;
use Coyote\ContentHelper\Image;
use Coyote\Logger;
use Coyote\CoyoteResource;
use GuzzleHttp\Promise\Create;

class Batching {

    public static function ajax_set_batch_job() {
        session_write_close();
        check_ajax_referer('coyote_ajax');

        $job_id = sanitize_text_field($_POST['job_id']);
        $job_type = sanitize_text_field($_POST['job_type']);

        self::set_batch_job($job_id, $job_type);

        echo true;

        wp_die();
    }

    public static function ajax_clear_batch_job() {
        session_write_close();
        check_ajax_referer('coyote_ajax');

        self::clear_batch_job();

        echo true;

        wp_die();
    }

    public static function clear_batch_job() {
        delete_transient('coyote_batch_job');
        delete_transient('coyote_batch_offset');
    }

    public static function set_batch_job($id, $type) {
        set_transient('coyote_batch_job', ['id' => $id, 'type' => $type]);
    }

    public static function get_batch_job() {
        return get_transient('coyote_batch_job') ?? null;
    }

    public static function load_process_batch() {
        session_write_close();

        $batch_size = intval($_GET['size']);

        if ($batch_size < 10) {
            $batch_size = 10;
        } else if ($batch_size > 200) {
            $batch_size = 200;
        }

        echo json_encode(self::_get_process_batch($batch_size));

        wp_die();
    }

    public static function _get_process_batch($size) {
        global $coyote_plugin;

        $post_types = $coyote_plugin->config['ProcessTypes'];
        $post_statuses = ['inherit', 'publish'];

        if (!$coyote_plugin->config['SkipUnpublished']) {
            $post_statuses = array_merge($post_statuses, ['pending', 'draft', 'private']);
        }

        $offset = get_transient('coyote_batch_offset');

        $response = [];

        if ($offset === false) {
            $offset = 0;

            $total_posts = array_reduce($post_types, function($carry, $type) use ($post_statuses) {
                $counts = wp_count_posts($type);

                foreach ($post_statuses as $status) {
                    if (property_exists($counts, $status)) {
                        $carry += $counts->$status;
                    }
                }

                return $carry;
            }, 0);

            $response['total'] = $total_posts;
        }

        $batch = get_posts(array(
            'order'       => 'ASC',
            'order_by'    => 'ID',
            'offset'      => $offset,
            'numberposts' => $size,
            'post_type'   => $post_types,
            'post_status' => $post_statuses,
            'post_parent' => null,
        ));

        $resources = self::create_resources($batch, $coyote_plugin->config['SkipUnpublished']);

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
        int $resourceGroupId,
        bool $skipUnpublishedParentPost,
        \WP_Post $post
    ): CreateResourcesPayload {
        // attachment with mime type image, get alt and caption differently
        $alt = get_post_meta($post->ID, '_wp_attachment_image_alt', true);

        if ($post->post_status === 'inherit' && $post->post_parent) {
            // child of a page
            $parent_post = get_post($post->post_parent);

            // only process images in published posts
            if ($parent_post && $parent_post->post_status !== 'publish' && $skipUnpublishedParentPost) {
                return $payload;
            }

            $host_uri = get_permalink($parent_post);
        } else {
            $host_uri = get_permalink($post);
        }

        $attachmentUrl = coyote_attachment_url($post->ID);

        $image = new WordPressImage(
            new Image($attachmentUrl, $alt, ''));
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

    private static function postIsImageAttachment(\WP_Post $post): bool
    {
        return $post->post_type === 'attachment' && strpos($post->post_mime_type, 'image/') === 0;
    }

    /** @return ResourceModel[]|null */
    public static function create_resources($posts, $skip_unpublished_parent_post): array {
        $resourceGroupId = PluginConfiguration::getApiResourceGroupId();
        $payload = new CreateResourcesPayload();

        foreach ($posts as $post) {
            if (self::postIsImageAttachment($post)) {
                $payload = self::addAttachmentResourceToPayload(
                    $payload,
                    $resourceGroupId,
                    $skip_unpublished_parent_post,
                    $post
                );
                continue;
            }

            $helper = new ContentHelper($post->post_content);
            $images = $helper->getImages();
            $host_uri = get_permalink($post);

            foreach ($images as $contentImage) {
                $image = new WordPressImage($contentImage);
                $image->setHostUri($host_uri);
                $payload->addResource(new CreateResourcePayload(
                    $image->getCaption() ?? $image->getUrl(),
                    $image->getUrl(),
                    $resourceGroupId,
                    $host_uri
                ));
            }
        }

        return WordPressCoyoteApiClient::createResources($payload);
    }

}

