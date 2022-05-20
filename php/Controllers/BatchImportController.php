<?php

namespace Coyote\Controllers;

use Coyote\BatchImportHelper;
use JetBrains\PhpStorm\NoReturn;

class BatchImportController
{
    private const MINIMUM_BATCH_SIZE = 10;
    private const MAXIMUM_BATCH_SIZE = 200;

    #[NoReturn] public static function ajaxSetBatchJob(): void
    {
        session_write_close();
        check_ajax_referer('coyote_ajax');

        $job_id = sanitize_text_field($_POST['job_id']);
        $job_type = sanitize_text_field($_POST['job_type']);

        BatchImportHelper::setBatchJob($job_id, $job_type);

        echo true;

        wp_die();
    }

    #[NoReturn] public static function ajaxClearBatchJob(): void
    {
        session_write_close();
        check_ajax_referer('coyote_ajax');

        BatchImportHelper::clearBatchJob();

        echo true;

        wp_die();
    }

    #[NoReturn] public static function ajaxLoadProcessBatch(): void
    {
        session_write_close();

        $batch_size = intval($_GET['size']);

        if ($batch_size < self::MINIMUM_BATCH_SIZE) {
            $batch_size = self::MINIMUM_BATCH_SIZE;
        } elseif ($batch_size > self::MAXIMUM_BATCH_SIZE) {
            $batch_size = self::MAXIMUM_BATCH_SIZE;
        }

        echo json_encode(BatchImportHelper::getProcessBatch($batch_size));

        wp_die();
    }
}
