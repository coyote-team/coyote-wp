<?php

namespace Coyote\Controllers;

if (!defined('WP_INC')) {
    exit;
}

use Coyote\BatchImportHelper;

class BatchImportController
{
    private const MINIMUM_BATCH_SIZE = 10;
    private const MAXIMUM_BATCH_SIZE = 200;

    public static function ajaxSetBatchJob(): void
    {
        session_write_close();
        check_ajax_referer('coyote_ajax');

        $job_id = sanitize_text_field($_POST['job_id']);
        $job_type = sanitize_text_field($_POST['job_type']);

        BatchImportHelper::setBatchJob($job_id, $job_type);

        echo true;

        wp_die();
    }

    public static function ajaxClearBatchJob(): void
    {
        session_write_close();
        check_ajax_referer('coyote_ajax');

        BatchImportHelper::clearBatchJob();

        echo true;

        wp_die();
    }

    public static function ajaxLoadProcessBatch(): void
    {
        session_write_close();

        $batchSize = intval($_GET['size']);

        if ($batchSize < self::MINIMUM_BATCH_SIZE) {
            $batchSize = self::MINIMUM_BATCH_SIZE;
        } elseif ($batchSize > self::MAXIMUM_BATCH_SIZE) {
            $batchSize = self::MAXIMUM_BATCH_SIZE;
        }

        echo json_encode(BatchImportHelper::getProcessBatch($batchSize));

        wp_die();
    }
}
