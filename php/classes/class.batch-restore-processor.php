<?php

namespace Coyote;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use Coyote\Logger;
use Coyote\DB;
use Coyote\Helpers\ContentHelper;
use Coyote\BatchProcessor;

class BatchRestoreProcessor extends BatchProcessor {
    const STATE_CLASS = 'Coyote\BatchRestoreState';

    protected function load_next_batch() {
        Logger::log("Loading next batch({$this->state->batch_size})");

        $post_ids = DB::get_edited_post_ids();

        $batch = get_posts(array(
            'order'       => 'ASC',
            'order_by'    => 'ID',
            'offset'      => $this->state->get_offset(),
            'numberposts' => $this->state->batch_size,
            'post_type'   => $this->state->post_types,
            'post_status' => $this->state->post_statuses,
            'post_in'     => $post_ids
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

    protected function process_post($post) {
        $resources = DB::get_resources_for_post($post->ID);
        $helper = new ContentHelper($post->post_content);

        foreach ($resources as $resource) {
            $helper->restore_resource($resource->coyote_resource_id, $resource->original_description);
        }

        $post->post_content = $helper->get_content();

        wp_update_post($post);
        wp_save_post_revision($post->ID);
    }
}

