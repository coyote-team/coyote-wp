<?php

namespace Coyote;

if (!defined('WP_INC')) {
    exit;
}

use Coyote\Traits\Logger;
use Coyote\Controllers\RestApiController;
use Coyote\Controllers\SettingsController;

class WordPressPlugin
{
    use Logger;

    public const PLUGIN_NAME = "Coyote";
    public const I18N_NS = 'coyote';
    public const LOG_PATH = COYOTE_PLUGIN_PATH . 'coyote.log';

    public function __construct(string $pluginFile)
    {
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

        /*
         * setupControllers after plugins_loaded so wp-includes/pluggable.php is loaded
         */
        add_action('plugins_loaded', [$this, 'setupControllers']);
    }

    public static function setupControllers(): void
    {
        if (PluginConfiguration::userIsAdmin()) {
            (new SettingsController());
        }

        if (PluginConfiguration::hasStoredApiProfile() && PluginConfiguration::hasUpdatesEnabled()) {
            // allow remote updates
            self::logDebug('Updates enabled.');

            (new RestApiController(
                PluginConfiguration::PLUGIN_VERSION,
                PluginConfiguration::API_VERSION,
                PluginConfiguration::getApiOrganizationId()
            ));
        } else {
            self::logDebug('Updates disabled.');
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
