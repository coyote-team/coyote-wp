<?php

namespace Coyote;

use Coyote\Logger;
use Coyote\BatchProcessorState;
use Coyote\Helpers\PostHelper;

abstract class BatchProcessor {
    const STATE_CLASS = null;

    protected function on_batch_load($batch) {
    }

    protected function process_post($post) {
    }

    protected function load_next_batch() {
    }

    protected $state;

    public function __construct($batch_size = 50, $post_types = array('page', 'post'), $post_statuses = array('published')) {
        if (static::STATE_CLASS === null) {
            throw new Exception('STATE_CLASS constant not defined in ' . __CLASS__);
        }

        $CLASS = static::STATE_CLASS;

        if ($state = $CLASS::load()) {
            $this->state = $state;

            if ($this->state->is_cancelled()) {
                $this->state->destroy();
            }

            return;
        }

        $total_posts = array_reduce($post_types, function($carry, $type) {
            return $carry + wp_count_posts($type)->publish;
        }, 0);

        $state = $CLASS::create($total_posts, $batch_size, $post_types, $post_statuses);

        $this->state = $state;

        $this->load_next_batch();
    }

    public function process_next() {
        $next_post_id = $this->state->current_post_id()
            ? $this->state->current_post_id()
            : $this->state->shift_next_post_id()
        ;

        if ($next_post_id) {
            $this->state->persist();
            Logger::log($this->state->get_progress_percentage() . '% complete');
            return $this->process($next_post_id);
        }

        //nothing left, we're done.
    }

    public function is_finished() {
        if ($this->state->current_post_id()) {
            return false;
        }

        if ($this->state->has_next_post_id()) {
            return false;
        }
        
        return !$this->load_next_batch();
    }

    public function is_active() {
        return !$this->state->is_cancelled() && !$this->is_finished();
    }

    private function process($post_id) {
        Logger::log("Processing {$post_id}");

        $post = get_post($post_id);

        if (!$post) {
            Logger::log("Unable to get post {$post_id}?");
            $this->state->skip_current()->persist();
            return;
        }

        if (PostHelper::is_locked($post_id)) {
            Logger::log("Post {$post_id} is locked for editing.");
            $this->state->skip_current()->persist();
            return;
        }

        Logger::log("Got post {$post_id}");

        // do processing
        try {
            PostHelper::lock($post_id);
            $content = $this->process_post($post);
            Logger::log("Done processing {$post_id}");
            $this->state->complete_current()->persist();
        } catch (Exception $error) {
            $message = $error->get_error_message();
            Logger::log("Failed to process {$post_id}: {$message}");
            $this->state->fail_current()->persist();
        } finally {
            PostHelper::unlock($post_id);
        }
    }
}

