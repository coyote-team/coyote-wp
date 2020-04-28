<?php

namespace Coyote\Controllers;

require_once coyote_plugin_file('classes/class.logger.php');

use Coyote\Logger;

class SettingsController {
    private $version;

    private $page_title;
    private $menu_title;

    const i18n_ns    = 'coyote';
    const capability = 'manage_options';
    const page_slug  = 'coyote_fields';
    const icon       = 'dashicon-admin-plugins';
    const position   = 250;

    const api_settings_section = 'api_settings_section';

    function __construct(string $version) {
        $this->version = $version;
        $this->setup();
        Logger::log("Controller settings init");
    }

    private function setup() {
        $this->page_title = __('Coyote Plugin Settings', self::i18n_ns);
        $this->menu_title = __('Coyote Plugin', self::i18n_ns);

        add_action('admin_init', array($this, 'init'));
        add_action('admin_menu', array($this, 'menu'));

    }

    public function settings_page_cb() {
        echo "
            <div class=\"wrap\">
                <h2>{$this->page_title}</h2>
                <form method=\"post\" action=\"options.php\">
        ";

        settings_fields(self::page_slug);
        do_settings_sections(self::page_slug);
        submit_button();

        echo "
                </form>
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
        register_setting(self::page_slug, 'coyote__api_settings_endpoint');
        register_setting(self::page_slug, 'coyote__api_settings_token');
        register_setting(self::page_slug, 'coyote__api_settings_organization_id');

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
            self::api_settings_section
        );

        add_settings_field(
            'coyote__api_settings_token',
            __('Token', self::i18n_ns),
            array($this, 'api_settings_token_cb'),
            self::page_slug,
            self::api_settings_section
        );

        add_settings_field(
            'coyote__api_settings_organization_id',
            __('Organization ID', self::i18n_ns),
            array($this, 'api_settings_organization_id_cb'),
            self::page_slug,
            self::api_settings_section
        );

    }

    public function api_settings_cb() {
        //TODO refactor into generator
    }

    public function api_settings_endpoint_cb() {
        echo '<input name="coyote__api_settings_endpoint" id="coyote__api_settings_endpoint" type="text" value="' . get_option('coyote__api_settings_endpoint') . '" size="50"/>';
    }

    public function api_settings_token_cb() {
        echo '<input name="coyote__api_settings_token" id="coyote__api_settings_token" type="text" value="' . get_option('coyote__api_settings_token') . '" size="30"/>';
    }

    public function api_settings_organization_id_cb() {
        echo '<input name="coyote__api_settings_organization_id" id="coyote__api_settings_organization_id" type="text" value="' . get_option('coyote__api_settings_organization_id') . '" size="2" maxlength="2" />';
    }

}
