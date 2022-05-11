<?php

namespace Coyote\WordPressPlugin;

use Coyote\ContentHelper\Image;
use Coyote\DB;
use Coyote\DB\ResourceRecord;
use Coyote\Logger;
use Coyote\Plugin;
use Coyote\PluginConfiguration;
use Coyote\WordPressCoyoteApiClient;
use Coyote\WordPressHelper;
use Coyote\WordPressImage;
use Coyote\WordPressPlugin;

class Actions
{
    public function displayAdminNotices() {
        $errorCount = PluginConfiguration::getApiErrorCount();

        if (PluginConfiguration::isEnabled() && $errorCount >= 10) {
            PluginConfiguration::setDisabledByPlugin();

            $message = __("The Coyote API client has thrown 10 consecutive errors, the Coyote plugin has switched to standalone mode.", COYOTE_I18N_NS);

            echo sprintf("<div class=\"notice notice-error\">
                    <p>%s</p>
                </div>", $message);
        }
    }

    public function adminEnqueueScripts(): void
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

    public function checkStandaloneStatus() {
        Logger::log('check_standalone hook firing');

        if (PluginConfiguration::isStandalone() &&
            PluginConfiguration::isDisabled()
        ) {
            $profile = WordPressCoyoteApiClient::getProfile();

            if (!is_null($profile)) {
                // if we can obtain the profile, disable standalone mode
                // and clear the scheduled event
                Logger::log('Recovering from standalone mode');
                $this->onApiClientSuccess();
            } else {
                Logger::log('Unable to recover from standalone mode');
            }
        }
    }

    public function onApiClientError($message) {
        Logger::log("Coyote API error: ${message}");

        $error_count = get_transient('coyote_api_error_count');

        if ($error_count === false) {
            $error_count = 1;
        } else {
            $error_count = intval($error_count) + 1;
        }

        Logger::log("Updating API error count to ${error_count}");

        // TODO [JKVA] implement via PluginConfiguration
        set_transient('coyote_api_error_count', $error_count);
    }

    public function onApiClientSuccess() {
        if (PluginConfiguration::isDisabledByPlugin()) {
            // plugin is in standalone because of api errors, a success can recover.
            // we don't recover in case of manual standalone.
            PluginConfiguration::setEnabledThroughRecovery();

            // clear the cron recovery attempt logic
            Logger::log('Unscheduling standalone check');
            wp_clear_scheduled_hook('coyote_check_standalone_hook');
        }

        // clear any existing api error count
        PluginConfiguration::clearApiErrorCount();
    }

    public function onPluginUninstall(): void
    {
        Logger::log("Uninstalling plugin");

        Logger::log("Deleting table");
        DB::runSqlFromFile(WordPressPlugin::getSqlFile('uninstall_plugin.sql'));

        Logger::log("Deleting options");
        PluginConfiguration::deletePluginOptions();
    }

    public function onPluginActivate() {
        if (PluginConfiguration::isInstalled()) {
            Logger::log("Plugin was active previously, not adding table");
            return;
        }

        Logger::log("Activating plugin");
        PluginConfiguration::setInstalled();
        DB::runSqlFromFile(WordPressPlugin::getSqlFile('create_resource_table.sql'));
    }

    public function onPluginDeactivate() {
        Logger::log('Deactivating plugin');
    }

}