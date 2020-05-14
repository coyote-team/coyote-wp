<?php

namespace Coyote;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use Coyote\Logger;
use Coyote\ImageResource;
use Coyote\Helpers\ContentHelper;
use Coyote\Helpers\PostHelper;
use Coyote\BatchProcessor;

class BatchPostProcessor extends BatchProcessor {
    const STATE_CLASS = 'Coyote\BatchProcessExistingState';

    private $resources;

    protected function load_next_batch() {
        Logger::log("Loading next batch({$this->state->batch_size})");

        $batch = get_posts(array(
            'order'       => 'ASC',
            'order_by'    => 'ID',
            'offset'      => $this->state->get_offset(),
            'numberposts' => $this->state->batch_size,
            'post_type'   => $this->state->post_types,
            'post_status' => $this->state->post_statuses
        ));

        if (!count($batch)) {
            Logger::log('Empty batch. No more posts - done!');
            $this->state->destroy();
            return false;
        }

        Logger::log('Batch size: (' . count($batch) . ')'); 

        $this->on_batch_load($batch);
        $this->state->set_batch($batch);

        return true;
    }

    protected function on_batch_load($batch) {
        try {
            $resources = $this->fetch_resources($batch);
            $this->state->set('resources', $resources);
        } catch (Exception $e) {
            $this->state->set('resources', array());
            Logger::log("Error fetching resources: " . $e->get_error_message());
        }
    }

    private function fetch_resources($posts) {
        $all_images = array();

        foreach ($posts as $post) {
            $helper = new ContentHelper($post->post_content);
            $images = $helper->get_images_with_attributes();
            foreach ($images as $image) {
                $all_images[$image['src']] = $image;
            }
        }

        $resources = ImageResource::map(array_values($all_images));

        return array_reduce($resources, function($carry, $resource) {
            $carry[$resource->image['src']] = array(
                'id'  => $resource->coyote_resource_id,
                'alt' => $resource->coyote_description
            );

            return $carry;
        }, array());
    }

    protected function process_post($post) {
        $helper = new ContentHelper($post->post_content);
        $images = $helper->get_images_with_attributes();

        $associated = array();

        $resources = $this->state->get('resources');

        foreach ($images as $image) {
            if ($image['data-coyote-id'] !== null) {
                continue;
            }

            if ($resource = $resources[$image['src']]) {
                if (!$resource['id']) {
                    Logger::log("Resource for {$image['src']} has no id? Skipping");
                    continue;
                }
                $alt = $resource['alt'] === null ? '' : $resource['alt'];
                $helper->set_coyote_id_and_alt($image['element'], $resource['id'], $alt);
                Logger::log("Associated {$resource['id']} with image {$image['src']} in post {$post->ID}");
                array_push($associated, $resource['id']);
            } else {
                Logger::log("Couldn't find resource for {$image['src']}?");
                continue;
            }
        }

        if (!$helper->content_is_modified) {
            Logger::log("No modifications made, done.");
            return;
        }

        $post->post_content = $helper->get_content();
        $result = wp_update_post($post, true);

        if (is_wp_error($result)) {
            throw $result;
        } else {
            wp_save_post_revision($post->ID);
            //if the post update succeeded, then associate the resources with the post
            DB::associate_resource_ids_with_post($associated, $post->ID);
        }
    }
}

