<?php

namespace Coyote;

if (!defined('WPINC')) {
    exit;
}

class BatchImportHelper
{
    private const TRANSIENT_KEY = 'coyote_batch_job';

    public static function clearBatchJob(string $id): void
    {
        $job = self::getBatchJob($id);

        if (!is_null($job)) {
            delete_transient(self::TRANSIENT_KEY);
        }
    }

    public static function createBatchJob($size = 0): BatchProcessingJob
    {
        $job = new BatchProcessingJob(
            wp_generate_uuid4(),
            PluginConfiguration::getProcessedPostTypes(),
            $size || PluginConfiguration::getProcessingBatchSize(),
            PluginConfiguration::getApiResourceGroupId(),
            PluginConfiguration::isProcessingUnpublishedPosts()
        );

        set_transient(self::TRANSIENT_KEY, $job);
        return $job;
    }

    public static function updateBatchJob(BatchProcessingJob $job): void
    {
        set_transient(self::TRANSIENT_KEY, $job);
    }

    public static function getCurrentBatchJob(): ?BatchProcessingJob
    {
        /** @var BatchProcessingJob|false $job */
        $job = get_transient(self::TRANSIENT_KEY);

        if ($job === false) {
            return null;
        }

        if ($job->isFinished()) {
            self::clearBatchJob($job->getId());
            return null;
        }

        return $job;
    }

    public static function getBatchJob(string $id): ?BatchProcessingJob
    {
        $job = get_transient(self::TRANSIENT_KEY);

        if ($job === false || is_null($job) || $job->getId() !== $id) {
            return null;
        }

        return $job;
    }

    public static function decreaseBatchSize(string $id): void
    {
        $job = self::getBatchJob($id);

        if (!is_null($job)) {
            $job->decreaseBatchSize();
            self::updateBatchJob($job);
        }
    }
}
