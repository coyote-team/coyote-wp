<?php

namespace Coyote\WordPressPlugin;

use Coyote\ContentHelper\Image;
use Coyote\DB;
use Coyote\DB\ResourceRecord;
use Coyote\PluginConfiguration;
use Coyote\WordPressCoyoteApiClient;
use Coyote\WordPressHelper;
use Coyote\WordPressImage;
use Coyote\WordPressPlugin;
use Coyote\Traits\Logger;

class Actions
{
    use Logger;

    public static function displayAdminNotices(): void
    {
        $errorCount = PluginConfiguration::getApiErrorCount();

        if (PluginConfiguration::isEnabled() && $errorCount >= 10) {
            PluginConfiguration::setDisabledByPlugin();

            $message = __(
                "The Coyote API client has thrown 10 consecutive errors, " .
                "the Coyote plugin has switched to standalone mode.",
                WordPressPlugin::I18N_NS
            );

            echo sprintf("<div class=\"notice notice-error\">
                    <p>%s</p>
                </div>", $message);
        }
    }

    public static function adminEnqueueScripts(): void
    {
        global $post;

        if (is_null($post)) {
            return;
        }

        // FIXME [JKVA] is this used within the media manager?
        if ($post->post_type !== 'attachment') {
            return;
        }

        $image = new WordPressImage(new Image(
            coyote_attachment_url($post->ID),
            '',
            ''
        ));

        /** @var ResourceRecord|null $resource */
        $resource = WordpressHelper::getResourceForWordPressImage($image, PluginConfiguration::isEnabled());

        if (is_null($resource)) {
            return;
        }

        $link = implode('/', [
            PluginConfiguration::getApiEndPoint(),
            'organizations',
            PluginConfiguration::getApiOrganizationId(),
            'resources',
            $resource->getResourceId()
        ]);

        $alt = esc_html($resource->getCoyoteDescription());

        echo <<<js
<script>
    window.coyote = {};
    window.coyote.post_data = {
        management_link: "{$link}",
        alt: "{$alt}",
    };
</script>
js;

        wp_enqueue_script(
            'coyote_hook_alt_js',
            coyote_asset_url('hook_alt_fields.js'),
            false
        );
    }

    public static function checkStandaloneStatus()
    {
        self::logDebug('check_standalone hook firing');

        if (PluginConfiguration::isStandalone() &&
            PluginConfiguration::isDisabled()
        ) {
            $profile = WordPressCoyoteApiClient::getProfile();

            if (!is_null($profile)) {
                // if we can obtain the profile, disable standalone mode
                // and clear the scheduled event
                self::logDebug('Recovering from standalone mode');
                WordPressCoyoteApiClient::registerApiSuccess();
            } else {
                self::logDebug('Unable to recover from standalone mode');
            }
        }
    }

    public static function onPluginUninstall(): void
    {
        self::logDebug("Uninstalling plugin");

        self::logDebug("Deleting table");
        DB::runSqlFromFile(WordPressPlugin::getSqlFile('uninstall_plugin.sql'));

        self::logDebug("Deleting options");
        PluginConfiguration::deletePluginOptions();
    }

    public static function onPluginActivate(): void
    {
        if (PluginConfiguration::isInstalled()) {
            self::logDebug("Plugin was active previously, not adding table");
            return;
        }

        self::logDebug("Activating plugin");
        PluginConfiguration::setInstalled();
        DB::runSqlFromFile(WordPressPlugin::getSqlFile('create_resource_table.sql'));
    }

    public static function onPluginDeactivate(): void
    {
        self::logDebug('Deactivating plugin');
    }

    /**
     * Load the plugin text domain for translation.
     */
    public static function loadPluginTextdomain(): void
    {
		load_plugin_textdomain(WordPressPlugin::I18N_NS, false, COYOTE_TRANSLATION_REL_PATH);
    }
}
