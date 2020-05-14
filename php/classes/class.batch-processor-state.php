<?php

namespace Coyote;

use Coyote\Logger;

class BatchRestoreState extends BatchProcessorState {
    const state_transient_key = 'coyote_restore_state';
    const last_update_transient_key = 'coyote_restore_last_update';
    const cancelled_transient_key = 'coyote_restore_cancelled';
}

class BatchProcessExistingState extends BatchProcessorState {
    const state_transient_key = 'coyote_process_existing_state';
    const last_update_transient_key = 'coyote_process_existing_last_update';
    const cancelled_transient_key = 'coyote_process_existing_cancelled';
}

abstract class BatchProcessorState {
    const state_transient_key = null;
    const last_update_transient_key = null;
    const cancelled_transient_key = null;

    private $state;

    public $total;
    public $batch_size;
    public $post_types;
    public $post_statuses;

    private $state_key;
    private $last_update_key;
    private $cancelled_key;

    public function __construct($state, $total, $batch_size, $post_types, $post_statuses) {
        if (!(static::state_transient_key && static::last_update_transient_key && static::cancelled_transient_key)) {
            throw new Exception("Not all required class consts defined!");
        }

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
            'current_post_id'    => null,

            'total_posts'        => $total,
            'batch_size'         => $batch_size,
            'post_types'         => $post_types,
            'post_statuses'      => $post_statuses,

            'last_update'        => null,
            'cancelled'          => false,

            'custom'             => array(),
        );

        $class = static::class;

        return new $class($state, $total, $batch_size, $post_types, $post_statuses);
    }

    public function set_batch($posts) {
        $ids = wp_list_pluck($posts, 'ID');
        $this->state['current_post_ids'] = $ids;
        $this->persist();
    }

    public function set($key, $value) {
        $this->state['custom'][$key] = $value;
        $this->persist();
    }

    public function get($key) {
        return $this->state['custom'][$key];
    }

    public static function has_stale_state() {
        $last_update = get_transient(static::last_update_transient_key);

        if ($last_update === false) {
            return false;
        }

        $now = new \DateTime();
        $seconds = (int) $now->getTimeStamp() - $last_update->getTimeStamp();

        // at least five minutes old?
        if ($seconds >= COYOTE_BATCH_STALE_SECONDS) {
            // block asap
            set_transient(static::last_update_transient_key, new \DateTime());
            Logger::log('Found stale batch processor state.');
            return true;
        }

        return false;
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

    public static function load($refresh = true) {
        $state = get_transient(static::state_transient_key);

        if ($state === false) {
            return null;
        }

        $class = static::class;

        $loaded_state = new $class($state, $state['total_posts'], $state['batch_size'], $state['post_types'], $state['post_statuses']);

        if ($cancelled = get_transient(static::cancelled_transient_key)) {
            $loaded_state->state['cancelled'] = true;
            delete_transient(static::cancelled_transient_key);
        }

        if ($refresh) {
            $loaded_state->touch();
        }

        return $loaded_state;
    }

    public static function exists() {
        return get_transient(static::last_update_transient_key) !== false;
    }

    public function persist() {
       return set_transient(static::state_transient_key, $this->state);
    }

    public function current_post_id() {
        return $this->state['current_post_id'];
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

    public function touch() {
        $dt = new \DateTime();
        $this->state['last_update'] = $dt;
        set_transient(static::last_update_transient_key, $dt);
    }

    public function skip_current() {
        array_push($this->state['skipped_post_ids'], $this->current_post_id());
        $this->state['current_post_id'] = null;
        $this->touch();
        return $this;
    }

    public function fail_current() {
        array_push($this->state['failed_post_ids'], $this->current_post_id());
        $this->state['current_post_id'] = null;
        $this->touch();
        return $this;
    }

    public function complete_current() {
        array_push($this->state['completed_post_ids'], $this->current_post_id());
        $this->state['current_post_id'] = null;
        $this->touch();
        return $this;
    }

    public function destroy() {
        delete_transient(static::state_transient_key);
        delete_transient(static::last_update_transient_key);
        delete_transient(static::cancelled_transient_key);
    }

    public function cancel() {
        set_transient(static::cancelled_transient_key, true);
    }

    public function is_cancelled() {
        return $this->state['cancelled'];
    }

}

