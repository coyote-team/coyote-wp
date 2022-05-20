<?php

namespace Coyote\Controllers;

use Coyote\BatchImportHelper;

class BatchImportController
{
    private const MINIMUM_BATCH_SIZE = 10;
    private const MAXIMUM_BATCH_SIZE = 200;

    public static function ajax_set_batch_job() {
        session_write_close();
        check_ajax_referer('coyote_ajax');

        $job_id = sanitize_text_field($_POST['job_id']);
        $job_type = sanitize_text_field($_POST['job_type']);

        BatchImportHelper::set_batch_job($job_id, $job_type);

        echo true;

        wp_die();
    }

    public static function ajax_clear_batch_job() {
        session_write_close();
        check_ajax_referer('coyote_ajax');

        BatchImportHelper::clear_batch_job();

        echo true;

        wp_die();
    }

    public static function ajax_load_process_batch() {
        session_write_close();

        $batch_size = intval($_GET['size']);

        if ($batch_size < self::MINIMUM_BATCH_SIZE) {
            $batch_size = self::MINIMUM_BATCH_SIZE;
        } elseif ($batch_size > self::MAXIMUM_BATCH_SIZE) {
            $batch_size = self::MAXIMUM_BATCH_SIZE;
        }

        echo json_encode(BatchImportHelper::get_process_batch($batch_size));

        wp_die();
    }
}