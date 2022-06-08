<?php

namespace Coyote\Controllers;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use Coyote\Traits\Logger;
use Coyote\BatchImportHelper;
use Coyote\DB;
use Coyote\Model\ProfileModel;
use Coyote\WordPressPlugin\Actions;
use Coyote\PluginConfiguration;
use Coyote\WordPressCoyoteApiClient;

class SettingsController {
    use Logger;

    private string $page_title;
    private string $menu_title;
    private ?ProfileModel $profile;
    private bool $is_standalone;

    const capability = 'manage_options';
    const page_slug  = 'coyote_fields';
    const position   = 250;

    const settings_section = 'settings_section';
    const api_settings_section = 'api_settings_section';
    const advanced_settings_section = 'advanced_settings_section';

    private $batch_job;
    private bool $profile_fetch_failed;

    function __construct() {
        $this->page_title = __('Coyote settings', COYOTE_I18N_NS);
        $this->menu_title = __('Coyote', COYOTE_I18N_NS);

        $this->profile_fetch_failed = false;
        $this->profile = $this->getProfile();

        $this->batch_job = BatchImportHelper::getBatchJob();

        $this->is_standalone = !!(get_option('coyote_is_standalone', false));

        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        add_action('admin_init', array($this, 'init'));
        add_action('admin_menu', array($this, 'menu'));

        if (!$this->is_standalone) {
            add_action('update_option_coyote_api_token', array($this, 'verify_settings'), 10, 3);
            add_action('update_option_coyote_api_endpoint', array($this, 'verify_settings'), 10, 3);
            add_action('update_option_coyote_api_organization_id', array($this, 'change_organization_id'), 10, 3);
            add_action('add_option_coyote_api_organization_id', array($this, 'set_organization_id'), 10, 2);
            add_action('update_option_coyote_is_standalone', array($this, 'change_standalone_mode'), 10, 3);
        }
    }

    public static function ajax_verify_resource_group() {
        $resourceGroupUrl = get_site_url(get_current_blog_id(), '/wp-json/coyote/v1/callback');

        $resourceGroup = WordPressCoyoteApiClient::createResourceGroup($resourceGroupUrl);

        if (!is_null($resourceGroup)) {
            PluginConfiguration::setResourceGroupId(intval($resourceGroup->getId()));
        }

        wp_die();
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'coyote_settings_js',
            coyote_asset_url('settings.js'),
            false
        );

        wp_enqueue_style(
            'coyote_settings_css',
            coyote_asset_url('settings.css'),
            false
        );

        wp_localize_script('coyote_settings_js', 'coyote_ajax_obj', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('coyote_ajax'),
            'endpoint' => esc_url(get_option('coyote_processor_endpoint')),
            'job_id' => $this->batch_job ? $this->batch_job['id'] : null,
            'job_type' => $this->batch_job ? $this->batch_job['type'] : null,
        ));
    }

    public function verify_settings($old, $new, $option) {
        $profile = WordPressCoyoteApiClient::getProfile();

        if (is_null($profile)) {
            $this->profile_fetch_failed = true;
            // TODO these should be PluginConfiguration functions
            delete_option('coyote_api_profile');
            delete_option('coyote_api_organization_id');
        } else {
            $organizations = $profile->getOrganizations();

            // default to the first organization if there is only one available
            if (count($organizations) === 1) {
                PluginConfiguration::setApiOrganizationId(array_pop($organizations)->getId());
            }
        }
    }

    public function change_standalone_mode($old, $new, $option): void
    {
        //clear any data about what caused standalone mode to be active, if any
        update_option('coyote_error_standalone', false);
        delete_transient('coyote_api_error_count');
    }

    public function set_organization_id($option, $value): void
    {
        self::logDebug('setting organization id', [$option, $value]);
        $this->change_organization_id(null, $value, $option);
    }

    public function change_organization_id($old, $new, $option): void
    {
        // When changing an organization, the existing resource tracking records need to be removed; clear the table.
        $deleted = DB::clearResourceTable();
        self::logDebug("Deleted $deleted resources");

        $resourceGroupUrl = get_site_url(get_current_blog_id(), '/wp-json/coyote/v1/callback');
        $resourceGroup = WordPressCoyoteApiClient::createResourceGroup($resourceGroupUrl);

        if (!is_null($resourceGroup)) {
            PluginConfiguration::setResourceGroupId(intval($resourceGroup->getId()));
        }
    }

    private function getProfile(): ?ProfileModel {
        $profile = PluginConfiguration::getApiProfile();

        if (!is_null($profile)) {
            self::logDebug('Found stored profile');
            return $profile;
        }

        if (PluginConfiguration::hasApiConfiguration()) {
            $profile = WordPressCoyoteApiClient::getProfile();
        }

        if (is_null($profile)) {
            // TODO should be in PluginConfiguration
            delete_option('coyote_api_organization_id');
            add_action('admin_notices', [Actions::class, 'notifyCredentials']);
            return null;
        }

        PluginConfiguration::setApiProfile($profile);
        $organizations = $profile->getOrganizations();

        // default to the first organization if there is only one available
        if (count($organizations) === 1) {
            PluginConfiguration::setApiOrganizationId(array_pop($organizations)->getId());
        }

        return $profile;
    }

    public function settings_page_cb() {
        echo sprintf("<div class=\"wrap\">
                <h2>%s</h2>
                <form method=\"post\" action=\"options.php\">
        ", $this->page_title);

        settings_fields(self::page_slug);
        do_settings_sections(self::page_slug);

        if (!$this->is_standalone) {
            if ($this->profile) {
                echo "<p>User: " . $this->profile->getName() . "</p>";
            } elseif ($this->profile_fetch_failed) {
                echo "<strong>" . __('Unable to load Coyote profile.', COYOTE_I18N_NS) . "</strong>";
            }

            submit_button();
        }

        echo "
                </form>
            </div>
        ";

        if (!$this->is_standalone) {
            $this->tools();
        }
    }

    public function tools() {
        if (!$this->profile) {
            return;
        }

        echo sprintf("<h2>%s</h2>", __("Tools", COYOTE_I18N_NS));

        echo "
          <div id=\"coyote_verify_resource_group_container\">
              <button class=\"button button-primary\" type=\"button\" id=\"coyote_verify_resource_group\" aria-describedby=\"coyote_verify_resource_group_hint\">Verify resource group</button>
              <span role=\"alert\" id=\"coyote_verify_resource_group_status\"></span>
              <p id=\"coyote_verify_resource_group_hint\">A resource group is required to make dynamic updates to image description work. When encountering update problems, verify the group exists.</p>
          </div>
        ";

        $title  = __("Process existing posts", COYOTE_I18N_NS);

        echo sprintf("<hr>
            <h3>%s</h3>
        ", $title);

        if (empty(get_option('coyote_api_organization_id'))) {
            echo __('Please select a Coyote organization to process posts.', COYOTE_I18N_NS);
            return;
        }

        $process_disabled = $this->batch_job ? 'disabled' : '';
        $cancel_disabled = $this->batch_job ? '' : 'disabled';

        $batch_size = esc_html(get_option('coyote_processing_batch_size', 50));

        $processor_endpoint = 'https://processor.coyote.pics';

        $info = __('Using a remote service, your WordPress installation will be queried remotely and this process will populate the associated Coyote organisation. Depending on your WordPress installation, this process may take a while to complete.', COYOTE_I18N_NS);
        $error = __('If the status of the processing job keeps resulting in an error, consider decreasing the batch size.', COYOTE_I18N_NS);
        $idempotence = __('This process does not modify your WordPress content itself, and may be used more than once.', COYOTE_I18N_NS);

        echo "
            <p>{$info}</p>
            <p>{$error}</p>
            <p>{$idempotence}</p>
            <div id=\"process-existing-posts\">
                <div class=\"form-group\">
                    <label for=\"coyote_processor_endpoint\">" . __('Processor endpoint', COYOTE_I18N_NS) . ":</label>
                    <input readonly {$process_disabled} id=\"coyote_processor_endpoint\" name=\"coyote_processor_endpoint\" type=\"text\" size=\"50\" maxlength=\"100\" value=\"{$processor_endpoint}\">
                </div>

                <div class=\"form-group\">
                    <label for=\"coyote_batch_size\">" . __('Batch size', COYOTE_I18N_NS) . ":</label>
                    <input id=\"coyote_batch_size\" type=\"text\" size=\"3\" maxlength=\"3\" value=\"{$batch_size}\">
                </div>

                <div id=\"process-controls\">
                    <button id=\"coyote_process_existing_posts\" {$process_disabled} type=\"submit\" class=\"button button-primary\">" . __('Start processing job', COYOTE_I18N_NS) . "</button>
                    <button id=\"coyote_cancel_processing\" {$cancel_disabled} type=\"button\" class=\"button\">" . __('Cancel processing job', COYOTE_I18N_NS). "</button>
                </div>
            </div>
        ";

        $hidden = $process_disabled ? '' : 'hidden';

        echo "
            <div id=\"coyote_processing_status\" {$hidden} aria-live=\"assertive\" aria-atomic=\"true\">
                <div>
                    <strong id=\"coyote_job_status\">" . __('Status', 'coyote') . ": <span></span></strong>
                </div>

                <div>
                    <strong id=\"coyote_processing\">" . __('Processing', 'coyote') . ": <span></span>%</strong>
                </div>

                <div>
                    <strong hidden id=\"coyote_processing_complete\">" . __('Processing complete', 'coyote') . ".</strong>
                </div>
            </div>
        ";
    }

    public function menu() {
        add_submenu_page(
            'options-general.php',
            $this->page_title,
            $this->menu_title,
            self::capability,
            self::page_slug,
            array($this, 'settings_page_cb')
        );
    }

    public function sanitize_boolean($option) {
        return !empty($option) ? 'on' : '';
    }

    public function sanitize_endpoint($endpoint) {
        if (!empty($endpoint)) {
            // check if it's a valid url
            if (filter_var($endpoint, FILTER_VALIDATE_URL) !== false) {
                return esc_url($endpoint);
            }
        }

        return '';
    }

    public function sanitize_token($token) {
        return esc_html($token);
    }

    public function sanitize_metum($metum) {
        return esc_html($metum);
    }

    public function sanitize_organization_id($organization_id) {
        // validate the organization id is valid?
        return esc_html($organization_id);
    }

    public function init() {
        register_setting(self::page_slug, 'coyote_is_standalone', ['type' => 'boolean', 'sanitize_callback' => [$this, 'sanitize_boolean']]);

        if (!$this->is_standalone) {
            register_setting(self::page_slug, 'coyote_filters_enabled', ['type' => 'boolean', 'sanitize_callback' => [$this, 'sanitize_boolean']]);
            register_setting(self::page_slug, 'coyote_updates_enabled', ['type' => 'boolean', 'sanitize_callback' => [$this, 'sanitize_boolean']]);
            register_setting(self::page_slug, 'coyote_skip_unpublished_enabled', ['type' => 'boolean', 'sanitize_callback' => [$this, 'sanitize_boolean']]);

            register_setting(self::page_slug, 'coyote_processor_endpoint', ['type' => 'string', 'sanitize_callback' => [$this, 'sanitize_endpoint']]);

            register_setting(self::page_slug, 'coyote_api_endpoint', ['type' => 'string', 'sanitize_callback' => [$this, 'sanitize_endpoint']]);
            register_setting(self::page_slug, 'coyote_api_token', ['type' => 'string', 'sanitize_callback' => [$this, 'sanitize_token']]);
            register_setting(self::page_slug, 'coyote_api_metum', ['type' => 'string', 'sanitize_callback' => [$this, 'sanitize_metum']]);

            if ($this->profile) {
                register_setting(self::page_slug, 'coyote_api_organization_id', ['type' => 'integer', 'sanitize_callback' => [$this, 'sanitize_organization_id']]);
            }
        }

        add_settings_section(
            self::settings_section,
            __('Plugin settings', COYOTE_I18N_NS),
            [$this, 'plugin_setting_section_cb'],
            self::page_slug
        );

        if ($this->is_standalone) {
            return;
        }

        add_settings_section(
            self::api_settings_section,
            __('API settings', COYOTE_I18N_NS),
            array($this, 'noop_setting_section_cb'),
            self::page_slug
        );

        add_settings_field(
            'coyote_api_endpoint',
            __('Endpoint', COYOTE_I18N_NS),
            array($this, 'api_endpoint_cb'),
            self::page_slug,
            self::api_settings_section,
            array('label_for' => 'coyote_api_endpoint')
        );

        add_settings_field(
            'coyote_api_token',
            __('Token', COYOTE_I18N_NS),
            array($this, 'api_token_cb'),
            self::page_slug,
            self::api_settings_section,
            array('label_for' => 'coyote_api_token')
        );

        add_settings_field(
            'coyote_api_metum',
            __('Metum', COYOTE_I18N_NS),
            array($this, 'api_metum_cb'),
            self::page_slug,
            self::api_settings_section,
            array('label_for' => 'coyote_api_metum')
        );

        if (!$this->profile) {
            return;
        }

        add_settings_field(
            'coyote_api_organization_id',
            __('Organization', COYOTE_I18N_NS),
            array($this, 'api_organization_id_cb'),
            self::page_slug,
            self::api_settings_section,
            array('label_for' => 'coyote_api_organization_id')
        );

        add_settings_section(
            self::advanced_settings_section,
            __('Advanced settings', COYOTE_I18N_NS),
            [$this, 'noop_setting_section_cb'],
            self::page_slug
        );

        add_settings_field(
            'coyote_is_standalone',
            __('Run in standalone mode', COYOTE_I18N_NS),
            array($this, 'settings_is_standalone_cb'),
            self::page_slug,
            self::advanced_settings_section,
            array('label_for' => 'coyote_is_standalone')
        );

        add_settings_field(
            'coyote_filters_enabled',
            __('Filter images through Coyote', COYOTE_I18N_NS),
            array($this, 'settings_filters_enabled_cb'),
            self::page_slug,
            self::advanced_settings_section,
            array('label_for' => 'coyote_filters_enabled')
        );

        add_settings_field(
            'coyote_updates_enabled',
            __('Enable Coyote remote description updates', COYOTE_I18N_NS),
            array($this, 'settings_updates_enabled_cb'),
            self::page_slug,
            self::advanced_settings_section,
            array('label_for' => 'coyote_updates_enabled')
        );

        add_settings_field(
            'coyote_skip_unpublished_enabled',
            __('Skip unpublished items when importing', COYOTE_I18N_NS),
            array($this, 'settings_skip_unpublished_enabled_cb'),
            self::page_slug,
            self::advanced_settings_section,
            array('label_for' => 'coyote_skip_unpublished_enabled')
        );

    }

    public function plugin_setting_section_cb() {
        if ($this->is_standalone) {
            $heading = __('Standalone mode', COYOTE_I18N_NS);
            $message = __('Coyote is running in standalone mode. No settings are available, and no remote Coyote API is used to manage resources and descriptions. Any locally stored image descriptions will be used to describe images.', COYOTE_I18N_NS);
            echo "
                <div id=\"coyote_is_standalone\">
                    <h3>{$heading}</h3>
                    <p>{$message}</p>
                    <input id=\"coyote_is_standalone\" value=\"false\" type=\"hidden\">
            ";

            submit_button(__('Turn off standalone mode', COYOTE_I18N_NS));

            echo "
                </div>
            ";

            return;
        }

        $message = __('In order to use the plugin, configure the API settings accordingly. Once your profile has been retrieved and an organisation has been selected, you can optionally process any existing posts, pages and images to populate the Coyote instance.', COYOTE_I18N_NS);
        echo "<p>{$message}</p>";
    }

    public function noop_setting_section_cb() {}

    public function api_endpoint_cb() {
        echo '<input name="coyote_api_endpoint" id="coyote_api_endpoint" type="text" value="' . esc_url(get_option('coyote_api_endpoint', 'https://staging.coyote.pics')) . '" size="50" aria-describedby="coyote_api_endpoint_hint"/>';
        echo '<p id="coyote_api_endpoint_hint">' . __('The endpoint for your Coyote instance, e.g. "https://staging.coyote.pics".', COYOTE_I18N_NS) . '</p>';
    }

    public function api_token_cb() {
        echo '<input name="coyote_api_token" id="coyote_api_token" type="text" value="' . sanitize_text_field(get_option('coyote_api_token')) . '" size="30" aria-describedby="coyote_api_token_hint"/>';
        echo '<p id="coyote_api_token_hint">' . __('The API token associated with your Coyote account.', COYOTE_I18N_NS) . '</p>';
    }

    public function api_metum_cb() {
        echo '<input name="coyote_api_metum" id="coyote_api_metum" type="text" value="' . sanitize_text_field(get_option('coyote_api_metum', 'Alt')) . '" size="20" aria-describedby="coyote_api_metum_hint"/>';
        echo '<p id="coyote_api_metum_hint">' . __('The metum used by the API to categorise image descriptions, e.g. "Alt".', COYOTE_I18N_NS) . '</p>';
    }

    public function api_organization_id_cb() {
        $organization_id = PluginConfiguration::getApiOrganizationId();
        $organizations = $this->profile->getOrganizations();
        $single_org = count($organizations) === 1;

        echo '<select name="coyote_api_organization_id" id="coyote_api_organization_id" aria-describedby="coyote_api_organization_id_hint">';

        if (!$single_org) {
            $default = empty($organization_id) ? 'selected' : '';
            echo "<option {$default} value=''>" . __('--select an organization--', COYOTE_I18N_NS) . "</option>";
        }

        foreach ($organizations as $org) {
            $selected = $org->getId() === $organization_id ? 'selected' : '';
            echo "<option {$selected} value=\"" . $org->getId() ."\">" . esc_html($org->getName()). "</option>";
        }

        echo '</select>';

        echo '<div id="coyote_org_change_alert" role="alert" data-message="' . __('Important: changing organization requires an import of coyote resources.', COYOTE_I18N_NS) . '"></div>';

        echo '<p id="coyote_api_organization_id_hint">' . __('The Coyote organization to associate with.', COYOTE_I18N_NS) . '</p>';
    }

    public function settings_is_standalone_cb() {
        $setting = esc_html(get_option('coyote_is_standalone', true));
        $checked = $setting ? 'checked' : '';
        echo "<input type=\"checkbox\" name=\"coyote_is_standalone\" id=\"coyote_is_standalone\" {$checked} aria-describedby=\"coyote_is_standalone_hint\">";
        echo '<p id="coyote_is_standalone_hint">' . __('The plugin does not attempt to communicate with the API. The plugin configuration becomes unavailable until standalone mode is again disabled.', COYOTE_I18N_NS) . '</p>';
    }

    public function settings_filters_enabled_cb() {
        $setting = esc_html(get_option('coyote_filters_enabled', true));
        $checked = $setting ? 'checked' : '';
        echo "<input type=\"checkbox\" name=\"coyote_filters_enabled\" id=\"coyote_filters_enabled\" {$checked} aria-describedby=\"coyote_filters_enabled_hint\">";
        echo '<p id="coyote_filters_enabled_hint">' . __('The plugin manages image descriptions for posts, pages and media.', COYOTE_I18N_NS) . '</p>';
    }

    public function settings_updates_enabled_cb() {
        $setting = esc_html(get_option('coyote_updates_enabled', true));
        $checked = $setting ? 'checked' : '';
        echo "<input type=\"checkbox\" name=\"coyote_updates_enabled\" id=\"coyote_updates_enabled\" {$checked} aria-describedby=\"coyote_updates_enabled_hint\">";
        echo '<p id="coyote_updates_enabled_hint">' . __('The plugin responds to approved image description updates issued through the Coyote API.', COYOTE_I18N_NS) . '</p>';
    }

    public function settings_skip_unpublished_enabled_cb() {
        $setting = esc_html(get_option('coyote_skip_unpublished_enabled', false));
        $checked = $setting ? 'checked' : '';
        echo "<input type=\"checkbox\" name=\"coyote_skip_unpublished_enabled\" id=\"coyote_skip_unpublished_enabled\" {$checked} aria-describedby=\"coyote_skip_unpublished_enabled_hint\">";
        echo '<p id="coyote_skip_unpublished_enabled_hint">' . __('During import the plugin skips unpublished posts and media library images contained in unpublished posts.', COYOTE_I18N_NS) . '</p>';
    }
}
