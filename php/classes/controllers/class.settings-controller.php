<?php

namespace Coyote\Controllers;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

require_once coyote_plugin_file('classes/class.logger.php');
require_once coyote_plugin_file('classes/class.api-client.php');

use Coyote\Logger;
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
        $this->page_title = __('Coyote Plugin Settings', self::i18n_ns);
        $this->menu_title = __('Coyote Plugin', self::i18n_ns);

        $this->profile_fetch_failed = false;
        $this->profile = $this->get_profile();

        add_action('admin_init', array($this, 'init'));
        add_action('admin_menu', array($this, 'menu'));

        add_action('update_option_coyote__api_settings_token', array($this, 'verify_settings'), 10, 3);
        add_action('update_option_coyote__api_settings_endpoint', array($this, 'verify_settings'), 10, 3);
        add_action('pre_update_option_coyote__settings_tool', array($this, 'run_tool'), 10, 3);
    }

    public function run_tool($old, $new, $option) {
        switch ($old) {
            case 'process_existing_posts':
                if ($this->profile) {
                    do_action('coyote_process_existing_posts', $this->profile);
                }
            break;
        }
    }

    public function verify_settings($old, $new, $option) {
        $token = get_option('coyote__api_settings_token');
        $endpoint = get_option('coyote__api_settings_endpoint');
        $client = new ApiClient($endpoint, $token);

        $profile = $client->getProfile();

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
            if ($profile = $client->getProfile()) {
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
            echo "<p>Username: " . $this->profile->name . "</p>";
        } else if ($this->profile_fetch_failed) {
            echo "<strong>" . __('Unable to load Coyote profile.', self::i18n_ns) . "</strong>";
        }

        submit_button();
        $this->tools();

        echo "
                </form>
            </div>
        ";
    }

    public function tools() {
        if (!$this->profile) {
            return;
        }

        $title  = __("Tools", self::i18n_ns);
        $action = plugins_url(COYOTE_PLUGIN_NAME . "/php/public/tools.php");

        $processing = get_transient('coyote_process_posts_progress');
        $disabled = $processing !== false ? 'disabled' : '';

        $update = get_transient('coyote_posts_processed')
            ? '<div id="coyote-posts-processed" class="notice notice-success"><p><strong>Posts processed.</strong></p></div>'
            : ''
        ;

        echo "
            {$update}
            <hr>
            <h2>{$title}</h2>
        ";

        settings_fields(self::page_slug);

        echo "
            <button {$disabled} type=\"submit\" name=\"coyote__settings_tool\" value=\"process_existing_posts\" class=\"button button-primary\">Process existing posts</button>
        ";
    
        if ($disabled) {
            echo "
                <div aria-live=\"polite\">
                    <strong>Processing: {$processing}%.</strong>
                </div>";
        }
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
        register_setting(self::page_slug, 'coyote__settings_tool');

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
    }

    public function api_settings_cb() {
        //TODO refactor into generator
    }

    public function api_settings_endpoint_cb() {
        echo '<input name="coyote__api_settings_endpoint" id="coyote__api_settings_endpoint" type="text" value="' . get_option('coyote__api_settings_endpoint', 'https://staging.coyote.pics') . '" size="50"/>';
    }

    public function api_settings_token_cb() {
        echo '<input name="coyote__api_settings_token" id="coyote__api_settings_token" type="text" value="' . get_option('coyote__api_settings_token') . '" size="30"/>';
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
