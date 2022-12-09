<?php

namespace Coyote\Controllers;

use Coyote\BatchImportHelper;

if (!defined('WPINC')) {
    exit;
}

class BatchImportController
{
    const REFERER_KEY = 'coyote_ajax';

    public static function ajaxStartBatchJob(): void
    {
        session_write_close();
        check_ajax_referer(self::REFERER_KEY);

        $job = BatchImportHelper::createBatchJob();

        echo $job->getId();

        wp_die();
    }

    public static function ajaxCancelBatchJob(): void
    {
        session_write_close();
        check_ajax_referer(self::REFERER_KEY);

        $id = $_POST['id'];
        BatchImportHelper::clearBatchJob($id);

        echo "1";

        wp_die();
    }

    public static function ajaxRunBatchJob(): void
    {
        session_write_close();
        check_ajax_referer(self::REFERER_KEY);

        $id = $_POST['id'];

        if (intval($id) === 0) {
            echo "0";
            wp_die();
        }

        $job = BatchImportHelper::getBatchJob($id);

        if (is_null($job)) {
            echo "0";
            wp_die();
        }

        try {
            $result = $job->processNextBatch();

            if ($job->isFinished()) {
                BatchImportHelper::clearBatchJob($id);
            }

            BatchImportHelper::updateBatchJob($job);

            echo json_encode($result);
        } catch (\Exception $e) {
            echo "0";
        }

        wp_die();
    }

    public static function ajaxResizeBatchJob(): void
    {
        session_write_close();
        check_ajax_referer(self::REFERER_KEY);

        $id = $_POST['id'];

        if (intval($id) === 0) {
            echo "0";
            wp_die();
        }

        BatchImportHelper::decreaseBatchSize($id);

        echo "1";

        wp_die();
    }
}
