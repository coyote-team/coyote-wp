<?php

/**
 * Set up WordPress Hooks and Filters
 * @category class
 * @package Coyote\HooksAndFilters
 * @since 1.0
 */

namespace Coyote;

use Coyote\Traits\Logger;
use Coyote\Controllers\BatchImportController;
use Coyote\Controllers\SettingsController;
use Coyote\Handlers\PostUpdateHandler;
use Coyote\WordPressPlugin\Actions;
use Coyote\WordPressPlugin\Filters;

if (!defined('WPINC')) {
    exit;
}

class WordPressActionsAndFilters
{
    use Logger;

    private static function enableBatchImport(): void
    {
        add_action('wp_ajax_coyote_load_process_batch', [BatchImportController::class, 'ajaxLoadProcessBatch']);
        add_action('wp_ajax_nopriv_coyote_load_process_batch', [BatchImportController::class, 'ajaxLoadProcessBatch']);

        add_action('wp_ajax_coyote_set_batch_job', [BatchImportController::class, 'ajaxSetBatchJob']);
        add_action('wp_ajax_nopriv_coyote_set_batch_job', [BatchImportController::class, 'ajaxSetBatchJob']);

        add_action('wp_ajax_coyote_clear_batch_job', [BatchImportController::class, 'ajaxClearBatchJob']);
        add_action('wp_ajax_nopriv_coyote_clear_batch_job', [BatchImportController::class, 'ajaxClearBatchJob']);

        add_action('wp_ajax_coyote_cancel_batch_job', [BatchImportController::class, 'ajaxClearBatchJob']);
        add_action('wp_ajax_nopriv_coyote_cancel_batch_job', [BatchImportController::class, 'ajaxClearBatchJob']);

        add_action('wp_ajax_coyote_verify_resource_group', [SettingsController::class, 'ajaxVerifyResourceGroup']);
    }

    private static function setupContentFilters(): void
    {
        self::logDebug('Filters enabled.');

        add_filter('the_content', [Filters::class, 'filterPostContent'], 255, 1);
        add_filter('wp_prepare_attachment_for_js', [Filters::class, 'filterAttachmentForJavaScript'], 10, 3);
        add_filter('wp_get_attachment_image_attributes', [Filters::class, 'filterAttachmentImageAttributes'], 10, 3);

        if (PluginConfiguration::isNotStandalone()) {
            // handle updates to posts made by the front-end
            add_filter('wp_insert_post_data', [PostUpdateHandler::class, 'run'], 10, 2);

            // allow custom resource management link in tinymce
            add_action('admin_init', [Filters::class, 'addTinyMcePlugin']);

            // load custom admin functionality scripts to patch alt fields
            add_action('admin_enqueue_scripts', [Actions::class, 'adminEnqueueScripts']);
        }
    }

    public static function setupPluginHooks(string $pluginFile): void
    {
        register_activation_hook($pluginFile, [Actions::class, 'onPluginActivate']);
        register_deactivation_hook($pluginFile, [Actions::class, 'onPluginDeactivate']);

        if (!PluginConfiguration::isInstalled()) {
            return;
        }

        register_uninstall_hook($pluginFile, [Actions::class, 'onPluginUninstall']);
    }

    public static function setupPluginActionsAndFilters(string $pluginFile): void
    {
        add_action('coyote_check_standalone_hook', [Actions::class, 'checkStandaloneStatus']);

        // add settings link to plugin page
        add_filter('plugin_action_links_' . plugin_basename($pluginFile), [Filters::class, 'addActionLinks']);

        // display any errors
        add_action('admin_notices', [Actions::class, 'displayAdminNotices']);

        // api client action handlers
        add_action('coyote_api_client_error', [WordPressCoyoteApiClient::class, 'registerApiError']);
        add_action('coyote_api_client_success', [WordPressCoyoteApiClient::class, 'registerApiSuccess']);

        // load plugin textdomain
        add_action('init', [Actions::class, 'loadPluginTextdomain']);

        if (PluginConfiguration::hasFiltersEnabled() && PluginConfiguration::hasApiConfiguration()) {
            self::setupContentFilters();
        } else {
            self::logDebug('Filters disabled.');
        }

        if (PluginConfiguration::isNotStandalone()) {
            self::enableBatchImport();
        }

        add_filter('cron_schedules', [Filters::class, 'addCronSchedule']);

        if (PluginConfiguration::isDisabledByPlugin()) {
            self::logDebug('checking coyote_check_standalone_hook');
            if (!wp_next_scheduled('coyote_check_standalone_hook')) {
                // setting standalone recovery wp-cron hook

                self::logDebug('Setting standalone recovery wp-cron hook');
                wp_schedule_event(time(), 'five_minutes', 'coyote_check_standalone_hook');
            } else {
                self::logDebug('Standalone recovery hook already scheduled');
            }
        }
    }
}
