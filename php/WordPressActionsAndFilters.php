<?php

/**
 * Set up WordPress Hooks and Filters
 * @category class
 * @package Coyote\HooksAndFilters
 * @since 1.0
 */

namespace Coyote;

use Coyote\Controllers\BatchImportController;
use Coyote\Controllers\SettingsController;
use Coyote\Handlers\PostUpdateHandler;
use Coyote\WordPressPlugin\Actions;
use Coyote\WordPressPlugin\Filters;

class WordPressActionsAndFilters {

    private static function enableBatchImport(): void
    {
        add_action('wp_ajax_coyote_load_process_batch', array(BatchImportController::class, 'ajax_load_process_batch'));
        add_action('wp_ajax_nopriv_coyote_load_process_batch', array(BatchImportController::class, 'ajax_load_process_batch'));

        add_action('wp_ajax_coyote_set_batch_job', array(BatchImportController::class, 'ajax_set_batch_job'));
        add_action('wp_ajax_nopriv_coyote_set_batch_job', array(BatchImportController::class, 'ajax_set_batch_job'));

        add_action('wp_ajax_coyote_clear_batch_job', array(BatchImportController::class, 'ajax_clear_batch_job'));
        add_action('wp_ajax_nopriv_coyote_clear_batch_job', array(BatchImportController::class, 'ajax_clear_batch_job'));

        add_action('wp_ajax_coyote_cancel_batch_job', array(BatchImportController::class, 'ajax_clear_batch_job'));
        add_action('wp_ajax_nopriv_coyote_cancel_batch_job', array(BatchImportController::class, 'ajax_clear_batch_job'));

        add_action('wp_ajax_coyote_verify_resource_group', array(SettingsController::class, 'ajax_verify_resource_group'));
    }

    private static function setupContentFilters(): void
    {
        Logger::log('Filters enabled.');

        add_filter('the_content', [Filters::class, 'filterPostContent'], 10, 1);
//            add_filter('the_editor_content', [$this, 'filter_post_content'], 10, 1);
        add_filter('wp_prepare_attachment_for_js', [Filters::class, 'filterAttachmentForJavaScript'], 10, 3);
        add_filter('wp_get_attachment_image_attributes', [Filters::class, 'filterAttachmentImageAttributes'], 10, 3);

//            add_filter('rest_prepare_post', [$this, 'filter_gutenberg_content'], 10, 3);
//            add_filter('rest_prepare_page', [$this, 'filter_gutenberg_content'], 10, 3);

        if (!PluginConfiguration::isNotStandalone()) {
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

        if (PluginConfiguration::hasFiltersEnabled() && PluginConfiguration::hasApiConfiguration()) {
            self::setupContentFilters();
        } else {
            Logger::log('Filters disabled.');
        }

        if (PluginConfiguration::isNotStandalone()) {
            self::enableBatchImport();
        }

        add_filter('cron_schedules', [Filters::class, 'addCronSchedule']);

        if (PluginConfiguration::isDisabledByPlugin()) {
            Logger::log('checking hook');
            if (!wp_next_scheduled('coyote_check_standalone_hook')) {
                // setting standalone recovery wp-cron hook

                Logger::log('Setting standalone recovery wp-cron hook');
                wp_schedule_event(time(), 'five_minutes', 'coyote_check_standalone_hook');
            } else {
                Logger::log('Standalone recovery hook already scheduled');
            }
        }
    }
}
