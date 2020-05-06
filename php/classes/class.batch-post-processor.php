<?php

namespace Coyote;

require_once coyote_plugin_file('classes/class.logger.php');
require_once coyote_plugin_file('classes/class.wp-async-request.php');
require_once coyote_plugin_file('classes/helpers/class.content-helper.php');
require_once coyote_plugin_file('classes/class.image-resource.php');

use \WP_Async_Request;

use Coyote\Logger;
use Coyote\ImageResource;
use Coyote\Helpers\ContentHelper;

class BatchPostProcessorState {
    const state_transient_key = 'coyote_batch_processor_state';

    private $state;

    public $total;
    public $batch_size;
    public $post_types;
    public $post_statuses;

    public function __construct($state, $total, $batch_size, $post_types, $post_statuses) {
        $this->state = $state;

        $this->total = $total;
        $this->batch_size = $batch_size;
        $this->post_types = $post_types;
        $this->post_statuses = $post_statuses;

        $this->persist();
    }

    public static function create($total, $batch_size, $post_types, $post_statuses) {
        $state = array(
            'skipped_post_ids'   => array(),
            'failed_post_ids'    => array(),
            'completed_post_ids' => array(),

            'current_post_ids'   => array(),
            'coyote_resources'   => array(),
            'current_post_id'    => null,

            'total_posts'        => $total,
            'batch_size'         => $batch_size,
            'post_types'         => $post_types,
            'post_statuses'      => $post_statuses,

            'last_update'        => null
        );

        return new BatchPostProcessorState($state, $total, $batch_size, $post_types, $post_statuses);
    }

    public function set_batch($posts, $resources) {
        $ids = wp_list_pluck($posts, 'ID');
        $this->state['current_post_ids'] = $ids;
        $this->state['coyote_resources'] = $resources;
        $this->state['last_update'] = new \DateTime();
        $this->persist();
    }

    public function get_offset() {
        return count($this->state['skipped_post_ids']) +
               count($this->state['failed_post_ids']) + 
               count($this->state['completed_post_ids']);
    }

    public function get_progress_percentage() {
        $processed = $this->get_offset();
        $total = $this->state['total_posts'];
        return (int) (($processed / $total) * 100);
    }

    public static function load() {
        $state = get_transient(self::state_transient_key);

        if ($state === false) {
            return null;
        }

        return new BatchPostProcessorState($state, $state['total_posts'], $state['batch_size'], $state['post_types'], $state['post_statuses']);
    }

    public function persist() {
        $result = set_transient(self::state_transient_key, $this->state);
        //if ($result === false) {
        //    $transient = self::state_transient_key;
        //    throw new \Exception("Failed to set transient {$transient}!");
        //}
    }

    public function current_post_id() {
        return $this->state['current_post_id'];
    }

    public function coyote_resources() {
        return $this->state['coyote_resources'];
    }

    public function shift_next_post_id() {
        $ids = $this->state['current_post_ids'];
        $next = array_shift($ids);

        $this->state['current_post_id'] = $next;
        $this->state['current_post_ids'] = $ids;

        return $next;
    }

    public function has_next_post_id() {
        return count($this->state['current_post_ids']) > 0;
    }

    public function skip_current() {
        array_push($this->state['skipped_post_ids'], $this->current_post_id());
        $this->state['current_post_id'] = null;
        $this->state['last_update'] = new \DateTime();
        return $this;
    }

    public function fail_current() {
        array_push($this->state['failed_post_ids'], $this->current_post_id());
        $this->state['current_post_id'] = null;
        $this->state['last_update'] = new \DateTime();
        return $this;
    }

    public function complete_current() {
        array_push($this->state['completed_post_ids'], $this->current_post_id());
        $this->state['current_post_id'] = null;
        $this->state['last_update'] = new \DateTime();
        return $this;
    }

    public function destroy() {
        delete_transient(self::state_transient_key);
    }
}

class BatchPostProcessor {

    private $state;

    public function __construct($batch_size = 50, $post_types = array('page', 'post'), $post_statuses = array('published')) {
        if ($state = BatchPostProcessorState::load()) {
            $this->state = $state;
            return;
        }

        $total_posts = array_reduce($post_types, function($carry, $type) {
            return $carry + wp_count_posts($type)->publish;
        }, 0);

        $state = BatchPostProcessorState::create($total_posts, $batch_size, $post_types, $post_statuses);

        $this->state = $state;

        $this->load_next_batch();
    }

    private function load_next_batch() {
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

        $resources = $this->fetch_resources($batch);
        $this->state->set_batch($batch, $resources);

        return true;
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

    public function process_next() {
        if ($next_post_id = $this->state->shift_next_post_id()) {
            $this->state->persist();
            Logger::log($this->state->get_progress_percentage() . '% complete');
            return $this->process($next_post_id);
        }

        //nothing left, we're done.
    }

    public function is_finished() {
        if ($this->state->has_next_post_id()) {
            return false;
        }
        
        return !$this->load_next_batch();
    }

    private function process($post_id) {
        Logger::log("Processing {$post_id}");

        $post = get_post($post_id);

        if (!$post) {
            Logger::log("Unable to get post {$post_id}?");
            $this->state->skip_current()->persist();
            return;
        }

        if (wp_check_post_lock($post_id)) {
            Logger::log("Post {$post_id} is locked for editing.");
            $this->state->skip_current()->persist();
            return;
        }

        Logger::log("Got post {$post_id}");

        //wp_set_post_lock($post_id);

        $resources = $this->state->coyote_resources();

        // do processing
        try {
            $content = $this->process_post($post, $resources);
        } catch (Exception $error) {
            $message = $error->get_error_message();
            Logger::log("Failed to process {$post_id}: {$message}");

            $this->state->fail_current()->persist();
        } finally {
            //TODO unlock the post for editing
        }

        Logger::log("Done processing {$post_id}");

        $this->state->complete_current()->persist();
    }

    private function process_post($post, $resources) {
        $helper = new ContentHelper($post->post_content);
        $images = $helper->get_images_with_attributes();

        $associated = array();

        foreach ($images as $image) {
            if ($image['data-coyote-id'] !== null) {
                continue;
            }

            if ($resource = $resources[$image['src']]) {
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
            //if the post update succeeded, then associate the resources with the post
            DB::associate_resource_ids_with_post($associated, $post->ID);
        }
    }
}

class AsyncPostProcessProcess extends WP_Async_Request {
    protected $action = 'coyote_process_post_async';

    protected function handle() {
        $processor = new BatchPostProcessor(5);
        if (!$processor->is_finished()) {
            $processor->process_next();
            $this->dispatch();
        }
    }
}
