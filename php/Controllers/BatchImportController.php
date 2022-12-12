<?php

namespace Coyote\Controllers;

use Coyote\BatchImportHelper;

if (!defined('WPINC')) {
    exit;
}

class BatchImportController
{
    const REFERER_KEY = 'coyote_ajax';

    private static function isValidId(string $id) {
        return preg_match('/^[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i', $id);
    }

    public static function ajaxStartBatchJob(): void
    {
        session_write_close();
        check_ajax_referer(self::REFERER_KEY);

        $size = intval($_POST['size']);

        $job = BatchImportHelper::createBatchJob($size);

        echo $job->getId();

        wp_die();
    }

    public static function ajaxCancelBatchJob(): void
    {
        session_write_close();
        check_ajax_referer(self::REFERER_KEY);

        $id = $_POST['id'];

        if (!self::isValidId($id)) {
            echo "0";
            wp_die();
        }

        echo BatchImportHelper::clearBatchJob($id)
            ? "1"
            : "0"
        ;

        wp_die();
    }

    public static function ajaxRunBatchJob(): void
    {
        session_write_close();
        check_ajax_referer(self::REFERER_KEY);

        $id = $_POST['id'];

        if (!self::isValidId($id)) {
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
            } else {
                BatchImportHelper::updateBatchJob($job);
            }

            echo json_encode($result);
        } catch (\Exception $e) {
            echo "-1";
        }

        wp_die();
    }

    public static function ajaxResizeBatchJob(): void
    {
        session_write_close();
        check_ajax_referer(self::REFERER_KEY);

        $id = $_POST['id'];

        if (!self::isValidId($id)) {
            echo "0";
            wp_die();
        }

        BatchImportHelper::decreaseBatchSize($id);

        echo "1";

        wp_die();
    }
}
