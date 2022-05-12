<?php

namespace Coyote;

use Coyote\Controllers\RestApiController;
use Coyote\Controllers\SettingsController;

class WordPressPlugin
{
    public function __construct(string $pluginFile) {
        WordPressActionsAndFilters::setupPluginHooks($pluginFile);

        if (!PluginConfiguration::isInstalled()) {
            return;
        }

        // only load updates option if we're either not in standalone mode,
        // or in standalone mode caused by repeated errors.
        // Explicit standalone mode disables remote updates.

        // TODO [JKVA] implement as part of PluginConfiguration::hasUpdatesEnabled()
        // if (!$this->is_standalone || $this->is_standalone_error) {
        //    $this->has_updates_enabled = get_option('coyote_updates_enabled', false);
        //}

        WordPressActionsAndFilters::setupPluginActionsAndFilters($pluginFile);

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

    private static function getPluginFile(string $type, string $name): string
    {
        return COYOTE_PLUGIN_PATH . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $name;
    }

    public static function getSqlFile(string $name): string
    {
        return self::getPluginFile('sql', $name);
    }
}