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
    private $version;

    private $page_title;
    private $menu_title;
    private $profile;

    const i18n_ns    = 'coyote';
    const capability = 'manage_options';
    const page_slug  = 'coyote_fields';
    const icon       = 'dashicon-admin-plugins';
    const position   = 250;

    const api_settings_section = 'api_settings_section';

    function __construct(string $version) {
        $this->version = $version;
        $this->setup();
    }

    private function setup() {
        $this->page_title = __('Coyote settings', self::i18n_ns);
        $this->menu_title = __('Coyote', self::i18n_ns);

        $this->profile_fetch_failed = false;
        $this->profile = $this->get_profile();

        $this->batch_job = Batching::get_batch_job();

        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        add_action('admin_init', array($this, 'init'));
        add_action('admin_menu', array($this, 'menu'));

        add_action('update_option_coyote__api_settings_token', array($this, 'verify_settings'), 10, 3);
        add_action('update_option_coyote__api_settings_endpoint', array($this, 'verify_settings'), 10, 3);

    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'coyote_settings_js',
            coyote_asset_url('settings.js'),
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
        $token = get_option('coyote__api_settings_token');
        $endpoint = get_option('coyote__api_settings_endpoint');
        $client = new ApiClient($endpoint, $token);

        $profile = $client->get_profile();

        if ($profile) {
            $stored_profile = get_option('coyote__api_profile');
            update_option('coyote__api_profile', $profile);

            if ($stored_profile && $profile->id !== $stored_profile->id) {
                $this->profile = $profile;
                update_option('coyote__api_settings_organization_id', $profile->organizations[0]['id']);
            } else if (!$stored_profile) {
                Logger::log($profile->organizations[0]['id']);
                update_option('coyote__api_settings_organization_id', $profile->organizations[0]['id']);
            }
        } else {
            $this->profile_fetch_failed = true;
            delete_option('coyote__api_profile');
            delete_option('coyote__api_settings_organization_id');
        }
    }

    private function get_profile() {
        $token = get_option('coyote__api_settings_token');
        $endpoint = get_option('coyote__api_settings_endpoint');
        $client = new ApiClient($endpoint, $token);

        $profile = get_option('coyote__api_profile', null);

        if (!$profile) {
            if ($profile = $client->get_profile()) {
                add_option('coyote__api_profile', $profile);
                add_option('coyote__api_settings_organization_id', $profile->organizations[0]['id']);
            } else if ($endpoint && $token) {
                $this->profile_fetch_failed = true;
            }
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

        $title  = __("Tools", self::i18n_ns);

        $process_disabled = $this->batch_job ? 'disabled' : '';
        $cancel_disabled = $this->batch_job ? '' : 'disabled';
        
        $batch_size = get_option('coyote__processing_batch_size', 50);

        echo "
            <hr>
            <h2>{$title}</h2>
        ";

        echo "
            <button id=\"coyote_process_existing_posts\" {$process_disabled} type=\"submit\" class=\"button button-primary\">" . __('Process existing posts', self::i18n_ns) . "</button>
            <button id=\"coyote_cancel_processing\" {$cancel_disabled} type=\"button\" class=\"button\">" . __('Cancel processing', self::i18n_ns). "</button>
            <div class=\"form-group\">
                <label for=\"\">" . __('Batch size', self::i18n_ns) . "</label>
                <input {$process_disabled} id=\"batch_size\" type=\"text\" size=\"3\" maxlength=\"3\" value=\"{$batch_size}\">
            </div>
        ";

        $hidden = $process_disabled ? '' : 'hidden';

        echo "
            <div id=\"coyote_processing_status\" {$hidden} aria-live=\"assertive\" aria-atomic=\"true\">
                <strong id=\"coyote_job_status\">" . __('Status', 'coyote') . ": <span></span></strong>
                <strong id=\"coyote_processing\">" . __('Processing', 'coyote') . ": <span></span>%</strong>
                <strong hidden id=\"coyote_processing_complete\">" . __('Processing complete', 'coyote') . ".</strong>
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

        register_setting(self::page_slug, 'coyote__api_settings_endpoint');
        register_setting(self::page_slug, 'coyote__api_settings_token');

        register_setting(self::page_slug, 'coyote_processor_endpoint');

        if ($this->profile) {
            register_setting(self::page_slug, 'coyote__api_settings_organization_id');
        }

        add_settings_section(
            self::api_settings_section, 
            __('API settings', self::i18n_ns),
            array($this, 'api_settings_cb'),
            self::page_slug
        );

        add_settings_field(
            'coyote__api_settings_endpoint',
            __('Endpoint', self::i18n_ns),
            array($this, 'api_settings_endpoint_cb'),
            self::page_slug,
            self::api_settings_section,
            array('label_for' => 'coyote__api_settings_endpoint')
        );

        add_settings_field(
            'coyote__api_settings_token',
            __('Token', self::i18n_ns),
            array($this, 'api_settings_token_cb'),
            self::page_slug,
            self::api_settings_section,
            array('label_for' => 'coyote__api_settings_token')
        );

        if (!$this->profile) {
            return;
        }

        add_settings_field(
            'coyote__api_settings_organization_id',
            __('Organization', self::i18n_ns),
            array($this, 'api_settings_organization_id_cb'),
            self::page_slug,
            self::api_settings_section,
            array('label_for' => 'coyote__api_settings_organization_id')
        );

        add_settings_field(
            'coyote_processor_endpoint',
            __('Processor endpoint', self::i18n_ns),
            array($this, 'processor_endpoint_cb'),
            self::page_slug,
            self::api_settings_section,
            array('label_for' => 'coyote_processor_endpoint')
        );
    }

    public function api_settings_cb() {
        //TODO refactor into generator
    }

    public function processor_endpoint_cb() {
        echo '<input name="coyote_processor_endpoint" id="coyote_processor_endpoint" type="text" value="' . get_option('coyote_processor_endpoint', 'https://processor.coyote.pics') . '" size="50"/>';
    }

    public function api_settings_endpoint_cb() {
        echo '<input name="coyote__api_settings_endpoint" id="coyote__api_settings_endpoint" type="text" value="' . get_option('coyote__api_settings_endpoint', 'https://staging.coyote.pics') . '" size="50"/>';
    }

    public function api_settings_token_cb() {
        echo '<input name="coyote__api_settings_token" id="coyote__api_settings_token" type="text" value="' . get_option('coyote__api_settings_token') . '" size="30"/>';
    }

    public function api_settings_method_cb() {
        $method = get_option('coyote__api_settings_method', 'post');

        echo '<p>' . __('Some platforms (e.g. WPEngine) don\'t handle POST requests well.', self::i18n_ns) . '</p>';
        echo '<select name="coyote__api_settings_method" id="coyote__api_settings_method">';

        foreach ([['id' => 'get', 'label' => 'GET'],['id' => 'post', 'label' => 'POST']] as $option) {
            $selected = $option['id'] === $method ? 'selected' : '';
            echo "<option {$selected} value=\"" . $option['id'] ."\">" . htmlspecialchars($option['label']). "</option>";
        }
        echo '</select>';
    }

    public function api_settings_organization_id_cb() {
        $organization_id = get_option('coyote__api_settings_organization_id');

        echo '<select name="coyote__api_settings_organization_id" id="coyote__api_settings_organization_id">';
        foreach ($this->profile->organizations as $org) {
            $selected = $org['id'] === $organization_id ? 'selected' : '';
            echo "<option {$selected} value=\"" . $org['id'] ."\">" . htmlspecialchars($org['name']). "</option>";
        }
        echo '</select>';
    }

}
