<?php

namespace Coyote;


// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use Coyote\Helpers\ContentHelper;
use Coyote\Logger;

class Batching {
    public static function is_processing($post_id) {
        return get_transient('coyote_post_restore_' . $post_id) || get_transient('coyote_post_process_' . $post_id);
    }

    public static function ajax_set_batch_job() {
        // Don't lock up other requests while processing
        session_write_close();

        //check_ajax_referer('coyote_ajax');

        $job_id = $_POST['job_id'];  
        $job_type = $_POST['job_type'];

        self::set_batch_job($job_id, $job_type);

        echo true;

        return wp_die();
    }

    public static function ajax_clear_batch_job() {
        // Don't lock up other requests while processing
        session_write_close();

        //check_ajax_referer('coyote_ajax');

        self::clear_batch_job();

        echo true;

        return wp_die();
    }

    public static function clear_batch_job() {
        delete_transient('coyote_batch_job');
        delete_transient('coyote_restore_batch_offset');
        delete_transient('coyote_process_batch_offset');
    }

    public static function set_batch_job($id, $type) {
        set_transient('coyote_batch_job', ['id' => $id, 'type' => $type]);
    }

    public static function get_batch_job() {
        return get_transient('coyote_batch_job') ?? null;
    }

    public static function load_process_batch() {
        // Don't lock up other requests while processing
        session_write_close();

//        check_ajax_referer('coyote_ajax');

        $batch_size = $_POST['size'];

        echo json_encode(self::_get_process_batch($batch_size));

        wp_die();
    }

    public static function load_restore_batch() {
        // Don't lock up other requests while processing
        session_write_close();

        check_ajax_referer('coyote_ajax');

        $batch_size = $_POST['size'];

        echo json_encode(self::_get_restore_batch($batch_size));

        wp_die();
    }


    public static function restore_post($post) {
        session_write_close();

        check_ajax_referer('coyote_ajax');

        $post_id = $_GET['post_id'];
        Logger::log("Restoring post {$post_id}");

        try {
            set_transient('coyote_post_restore_' . $post_id, true);

            $post = get_post($post_id);

            $resources = DB::get_resources_for_post($post->ID);
            $helper = new ContentHelper($post->post_content);

            foreach ($resources as $resource) {
                $helper->restore_resource($resource->coyote_resource_id, $resource->original_description);
            }

            $post->post_content = $helper->get_content();

            wp_update_post($post);
            wp_save_post_revision($post->ID);

            echo true;
        } catch (\Exception $e) {
            Logger::log($e);
            echo false;
        } finally {
            delete_transient('coyote_post_restore_' . $post_id);
        }

        wp_die();
    }

    public static function process_post() {
        session_write_close();

//        check_ajax_referer('coyote_ajax');

        $post_id = $_GET['post_id'];
        Logger::log("Processing post {$post_id}");

        try {
            set_transient('coyote_post_process_' . $post_id, true);

            $post = get_post($post_id);

            $helper = new ContentHelper($post->post_content);
            $images = $helper->get_images();

            $associated = array();

            $resources = get_transient('coyote_process_batch_resources');

            foreach ($images as $image) {
                if ($image['coyote_id'] !== null) {
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
                echo true;
                return wp_die();
            }

            $post->post_content = $helper->get_content();
            $result = wp_update_post($post, true);

            echo true;
        } catch (\Exception $e) {
            echo false;
        } finally {
            delete_transient('coyote_post_process_' . $post_id);
        }

        if (is_wp_error($result)) {
            throw $result;
        } else {
            wp_save_post_revision($post->ID);
            //if the post update succeeded, then associate the resources with the post
            DB::associate_resource_ids_with_post($associated, $post->ID);
        }

        wp_die();
    }

    public static function _get_restore_batch($size) {
        $post_types = ['page', 'post'];

        $offset = get_transient('coyote_restore_batch_offset');

        $response = [];

        $post_ids = DB::get_edited_post_ids();

        if ($offset === false) {
            $offset = 0;
            $response['total'] = count($post_ids);
        }

        $batch = get_posts(array(
            'order'       => 'ASC',
            'order_by'    => 'ID',
            'offset'      => $offset,
            'numberposts' => $size,
            'post_type'   => $post_types,
            'post_status' => 'publish',
            'post_in'     => $post_ids
        ));

        $ids = wp_list_pluck($batch, 'ID');

        $response['ids'] = $ids;

        if (count($batch) === 0) {
            // no more posts
            delete_transient('coyote_restore_batch_offset');
        } else {
            set_transient('coyote_restore_batch_offset', $offset + count($batch));
        }

        return $response;
    }


    public static function _get_process_batch($size) {
        $post_types = ['page', 'post'];

        $offset = get_transient('coyote_process_batch_offset');

        $response = [];

        if ($offset === false) {
            $offset = 0;

            $total_posts = array_reduce($post_types, function($carry, $type) {
                return $carry + wp_count_posts($type)->publish;
            }, 0);

            $response['total'] = $total_posts;
        }

        $batch = get_posts(array(
            'order'       => 'ASC',
            'order_by'    => 'ID',
            'offset'      => $offset,
            'numberposts' => $size,
            'post_type'   => $post_types,
            'post_status' => 'publish'
        ));

        $resources = self::_fetch_resources($batch);

        set_transient('coyote_process_batch_resources', $resources);

        $ids = wp_list_pluck($batch, 'ID');

        $response['ids'] = $ids;

        if (count($batch) === 0) {
            // no more posts
            delete_transient('coyote_process_batch_offset');
        } else {
            set_transient('coyote_process_batch_offset', $offset + count($batch));
        }

        return $response;
    }

    public static function _fetch_resources($posts) {
        $all_images = array();

        foreach ($posts as $post) {
            $helper = new ContentHelper($post->post_content);
            $images = $helper->get_images();
            foreach ($images as $image) {
                $all_images[$image['src']] = $image;
            }
        }

        $resources = ImageResource::resources_from_images(array_values($all_images));

        return array_reduce($resources, function($carry, $resource) {
            $carry[$resource->image['src']] = array(
                'id'  => $resource->coyote_resource_id,
                'alt' => $resource->coyote_description
            );

            return $carry;
        }, array());
    }

}

