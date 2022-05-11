<?php

namespace Coyote;

use Coyote\Controllers\RestApiController;
use Coyote\Controllers\SettingsController;
use Coyote\WordPressPlugin\Actions;

class WordPressPlugin
{
    public function __construct(string $pluginFile) {
        // $wpdb becomes available here
//        global $wpdb;
//        define('COYOTE_IMAGE_TABLE_NAME', $wpdb->prefix . 'coyote_image_resource');

        register_activation_hook($pluginFile, [Actions::class, 'onPluginActivate']);
        register_deactivation_hook($pluginFile, [Actions::class, 'onPluginDeactivate']);

        if (!PluginConfiguration::isInstalled()) {
            return;
        }

        register_uninstall_hook($pluginFile, [Actions::class, 'onPluginUninstall']);

        // only load updates option if we're either not in standalone mode,
        // or in standalone mode caused by repeated errors.
        // Explicit standalone mode disables remote updates.

        // TODO [JKVA] implement as part of PluginConfiguration::hasUpdatesEnabled()
        // if (!$this->is_standalone || $this->is_standalone_error) {
        //    $this->has_updates_enabled = get_option('coyote_updates_enabled', false);
        //}

        HooksAndFilters::setup($pluginFile);

        self::setupControllers();
    }

    private static function setupControllers(): void
    {
        if (PluginConfiguration::userIsAdmin()) {
            (new SettingsController());
        }

        if (PluginConfiguration::isConfigured() && PluginConfiguration::hasUpdatesEnabled()) {
            // allow remote updates
            Logger::log('Updates enabled.');

            (new RestApiController(
                PluginConfiguration::PLUGIN_VERSION,
                PluginConfiguration::API_VERSION,
                PluginConfiguration::getApiOrganizationId()
            ));
        } else {
            Logger::log('Updates disabled.');
        }

    }

    public static function registerApiSuccess(): void
    {
        do_action('coyote_api_client_success');
    }

    public static function registerApiError(): void
    {
        do_action('coyote_api_client_error');
    }

    private static function getPluginFile(string $type, string $name): string
    {
        return COYOTE_PLUGIN_PATH . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $name;
    }

    public static function getSqlFile(string $name): string
    {
        return self::getPluginFile('sql', $name);
    }
}