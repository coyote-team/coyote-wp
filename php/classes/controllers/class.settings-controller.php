<?php

namespace Coyote\Controllers;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use Coyote\Logger;
use Coyote\Batching;
use Coyote\ApiClient;

class SettingsController {
    private $page_title;
    private $menu_title;
    private $profile;

    const i18n_ns    = 'coyote';
    const capability = 'manage_options';
    const page_slug  = 'coyote_fields';
    const icon       = 'dashicon-admin-plugins';
    const position   = 250;

    const settings_section = 'settings_section';
    const api_settings_section = 'api_settings_section';

    function __construct() {
        $this->page_title = __('Coyote settings', self::i18n_ns);
        $this->menu_title = __('Coyote', self::i18n_ns);

        $this->profile_fetch_failed = false;
        $this->profile = $this->get_profile();

        $this->batch_job = Batching::get_batch_job();

        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        add_action('admin_init', array($this, 'init'));
        add_action('admin_menu', array($this, 'menu'));

        add_action('update_option_coyote_api_token', array($this, 'verify_settings'), 10, 3);
        add_action('update_option_coyote_api_endpoint', array($this, 'verify_settings'), 10, 3);
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
            'endpoint' => get_option('coyote_processor_endpoint'),
            'job_id' => $this->batch_job ? $this->batch_job['id'] : null,
            'job_type' => $this->batch_job ? $this->batch_job['type'] : null
        ));
    }

    public function verify_settings($old, $new, $option) {
        $token = get_option('coyote_api_token');
        $endpoint = get_option('coyote_api_endpoint');
        $client = new ApiClient($endpoint, $token);

        $profile = $client->get_profile();

        if ($profile) {
            $stored_profile = get_option('coyote_api_profile');
            update_option('coyote_api_profile', $profile);

            if ($stored_profile && $profile->id !== $stored_profile->id) {
                $this->profile = $profile;
                update_option('coyote_api_organization_id', $profile->organizations[0]['id']);
            } else if (!$stored_profile) {
                update_option('coyote_api_organization_id', $profile->organizations[0]['id']);
            }
        } else {
            $this->profile_fetch_failed = true;
            delete_option('coyote_api_profile');
            delete_option('coyote_api_organization_id');
        }
    }

    private function get_profile() {
        $profile = get_option('coyote_api_profile', null);

        if (!$profile) {
            $token = get_option('coyote_api_token');
            $endpoint = get_option('coyote_api_endpoint');

            if (empty($token) || empty($endpoint)) {
                return;
            }

            $client = new ApiClient($endpoint, $token);

            if ($profile = $client->get_profile()) {
                add_option('coyote_api_profile', $profile);
                add_option('coyote_api_organization_id', $profile->organizations[0]['id']);
                Logger::log('Fetched profile successfully');
            } else {
                $this->profile_fetch_failed = true;
                Logger::log('Fetching profile failed');
            }
        } else {
            Logger::log('Found stored profile');
        }

        return $profile;
    }

    public function settings_page_cb() {
        echo "
            <div class=\"wrap\">
                <h2>{$this->page_title}</h2>
                <form method=\"post\" action=\"options.php\">
        ";

        settings_fields(self::page_slug);
        do_settings_sections(self::page_slug);

        if ($this->profile) {
            echo "<p>User: " . $this->profile->name . "</p>";
        } else if ($this->profile_fetch_failed) {
            echo "<strong>" . __('Unable to load Coyote profile.', self::i18n_ns) . "</strong>";
        }

        submit_button();

        echo "
                </form>
            </div>
        ";

        $this->tools();
    }

    public function tools() {
        if (!$this->profile) {
            return;
        }

        $title  = __("Process existing posts", self::i18n_ns);

        $process_disabled = $this->batch_job ? 'disabled' : '';
        $cancel_disabled = $this->batch_job ? '' : 'disabled';

        $batch_size = get_option('coyote_processing_batch_size', 50);

        echo "
            <hr>
            <h2>{$title}</h2>
        ";

        $processor_endpoint = 'https://processor.coyote.pics';

        echo "
            <div id=\"process-existing-posts\">
                <div class=\"form-group\">
                    <label for=\"coyote_processor_endpoint\">" . __('Processor endpoint', self::i18n_ns) . ":</label>
                    <input readonly {$process_disabled} id=\"coyote_processor_endpoint\" name=\"coyote_processor_endpoint\" type=\"text\" size=\"50\" maxlength=\"100\" value=\"{$processor_endpoint}\">
                </div>

                <div class=\"form-group\">
                    <label for=\"coyote_batch_size\">" . __('Batch size', self::i18n_ns) . ":</label>
                    <input id=\"coyote_batch_size\" type=\"text\" size=\"3\" maxlength=\"3\" value=\"{$batch_size}\">
                </div>

                <div id=\"process-controls\">
                    <button id=\"coyote_process_existing_posts\" {$process_disabled} type=\"submit\" class=\"button button-primary\">" . __('Start processing job', self::i18n_ns) . "</button>
                    <button id=\"coyote_cancel_processing\" {$cancel_disabled} type=\"button\" class=\"button\">" . __('Cancel processing job', self::i18n_ns). "</button>
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

    public function init() {
        // TODO generate this from static typedef

        register_setting(self::page_slug, 'coyote_filters_enabled');
        register_setting(self::page_slug, 'coyote_updates_enabled');
        register_setting(self::page_slug, 'coyote_processor_endpoint');
        register_setting(self::page_slug, 'coyote_post_types');
        register_setting(self::page_slug, 'coyote_post_statuses');

        register_setting(self::page_slug, 'coyote_api_endpoint');
        register_setting(self::page_slug, 'coyote_api_token');
        register_setting(self::page_slug, 'coyote_api_metum');

        if ($this->profile) {
            register_setting(self::page_slug, 'coyote_api_organization_id');
        }

        add_settings_section(
            self::settings_section,
            __('Plugin settings', self::i18n_ns),
            [$this, 'setting_section_cb'],
            self::page_slug
        );

        add_settings_field(
            'coyote_filters_enabled',
            __('Filter images through Coyote', self::i18n_ns),
            array($this, 'settings_filters_enabled_cb'),
            self::page_slug,
            self::settings_section,
            array('label_for' => 'coyote_filters_enabled')
        );

        add_settings_field(
            'coyote_updates_enabled',
            __('Enable Coyote remote description updates', self::i18n_ns),
            array($this, 'settings_updates_enabled_cb'),
            self::page_slug,
            self::settings_section,
            array('label_for' => 'coyote_updates_enabled')
        );

        add_settings_field(
            'coyote_post_types',
            __('Process post types', self::i18n_ns),
            array($this, 'settings_post_types_cb'),
            self::page_slug,
            self::settings_section,
            array('label_for' => 'coyote_post_types')
        );

        add_settings_field(
            'coyote_post_statuses',
            __('Process post statuses', self::i18n_ns),
            array($this, 'settings_post_statuses_cb'),
            self::page_slug,
            self::settings_section,
            array('label_for' => 'coyote_post_statuses')
        );

        add_settings_section(
            self::api_settings_section,
            __('API settings', self::i18n_ns),
            array($this, 'setting_section_cb'),
            self::page_slug
        );

        add_settings_field(
            'coyote_api_endpoint',
            __('Endpoint', self::i18n_ns),
            array($this, 'api_endpoint_cb'),
            self::page_slug,
            self::api_settings_section,
            array('label_for' => 'coyote_api_endpoint')
        );


        add_settings_field(
            'coyote_api_token',
            __('Token', self::i18n_ns),
            array($this, 'api_token_cb'),
            self::page_slug,
            self::api_settings_section,
            array('label_for' => 'coyote_api_token')
        );

        add_settings_field(
            'coyote_api_metum',
            __('Metum', self::i18n_ns),
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
            __('Organization', self::i18n_ns),
            array($this, 'api_organization_id_cb'),
            self::page_slug,
            self::api_settings_section,
            array('label_for' => 'coyote_api_organization_id')
        );

    }

    public function setting_section_cb() {
        //TODO refactor into generator
    }

    public function api_endpoint_cb() {
        echo '<input name="coyote_api_endpoint" id="coyote_api_endpoint" type="text" value="' . get_option('coyote_api_endpoint', 'https://api.coyote.pics') . '" size="50"/>';
    }

    public function api_token_cb() {
        echo '<input name="coyote_api_token" id="coyote_api_token" type="text" value="' . get_option('coyote_api_token') . '" size="30"/>';
    }

    public function api_metum_cb() {
        echo '<input name="coyote_api_metum" id="coyote_api_metum" type="text" value="' . get_option('coyote_api_metum', 'Alt') . '" size="50"/>';
    }

    public function api_organization_id_cb() {
        $organization_id = get_option('coyote_api_organization_id');

        echo '<select name="coyote_api_organization_id" id="coyote_api_organization_id">';
        foreach ($this->profile->organizations as $org) {
            $selected = $org['id'] === $organization_id ? 'selected' : '';
            echo "<option {$selected} value=\"" . $org['id'] ."\">" . htmlspecialchars($org['name']). "</option>";
        }
        echo '</select>';
    }

    public function settings_filters_enabled_cb() {
        $setting = get_option('coyote_filters_enabled', true);
        $checked = $setting ? 'checked' : '';
        echo "<input type=\"checkbox\" name=\"coyote_filters_enabled\" id=\"coyote_filters_enabled\" {$checked}>";
    }

    public function settings_updates_enabled_cb() {
        $setting = get_option('coyote_updates_enabled', true);
        $checked = $setting ? 'checked' : '';
        echo "<input type=\"checkbox\" name=\"coyote_updates_enabled\" id=\"coyote_updates_enabled\" {$checked}>";
    }

    public function settings_post_types_cb() {
        $setting = get_option('coyote_post_types', ['page', 'post', 'attachment']);

        if (empty($setting)) {
            $setting = [];
        }

        if (!is_array($setting)) {
            $setting = [$setting];
        }

        $custom_types = get_post_types(['public' => true, '_builtin' => false], 'objects', 'or');
        $builtin_types = get_post_types(['public' => true, '_builtin' => true], 'objects', 'and');

        echo "<select multiple name=\"coyote_post_types[]\" id=\"coyote_post_types\">";
        foreach (array_merge($custom_types, $builtin_types) as $slug => $type) {
            $selected = in_array($slug, $setting) ? 'selected' : '';
            echo "<option {$selected} value=\"{$slug}\">{$type->label}</option>";
        }
        echo "</select>";
    }

    public function settings_post_statuses_cb() {
        $setting = get_option('coyote_post_statuses', ['publish']);

        if (empty($setting)) {
            $setting = [];
        }

        if (!is_array($setting)) {
            $setting = [$setting];
        }

        $statuses = get_post_statuses();
        echo "<select multiple name=\"coyote_post_statuses[]\" id=\"coyote_post_statuses\">";
        foreach ($statuses as $slug => $status) {
            $selected = in_array($slug, $setting) ? 'selected' : '';
            echo "<option {$selected} value=\"{$slug}\">{$status}</option>";
        }
        echo "</select>";
    }

}
