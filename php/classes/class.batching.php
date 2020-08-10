<?php

namespace Coyote;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use Coyote\Helpers\ContentHelper;
use Coyote\Logger;
use Coyote\CoyoteResource;

class Batching {

    public static function ajax_set_batch_job() {
        session_write_close();
        check_ajax_referer('coyote_ajax');

        $job_id = $_POST['job_id'];  
        $job_type = $_POST['job_type'];

        self::set_batch_job($job_id, $job_type);

        echo true;

        return wp_die();
    }

    public static function ajax_clear_batch_job() {
        session_write_close();
        check_ajax_referer('coyote_ajax');

        self::clear_batch_job();

        echo true;

        return wp_die();
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

        $batch_size = $_GET['size'];

        echo json_encode(self::_get_process_batch($batch_size));

        wp_die();
    }

    public static function _get_process_batch($size) {
        $post_types = ['page', 'post'];

        $offset = get_transient('coyote_batch_offset');

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

        $resources = self::create_resources($batch);

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

    public static function create_resources($posts) {
        $all_images = array();

        foreach ($posts as $post) {
            $helper = new ContentHelper($post->post_content);
            $images = $helper->get_images();
            foreach ($images as $image) {
                $all_images[$image['src']] = $image;
            }
        }

        return CoyoteResource::resources_from_images(array_values($all_images));
    }

}

