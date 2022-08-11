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
use Coyote\PluginConfiguration;
use Coyote\WordPressCoyoteApiClient;
use Coyote\WordPressPlugin;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use Twig\TwigFunction;

class SettingsController {
    use Logger;

    /**
     * Profile fetched from API
     */
    private ?ProfileModel $profile;

    /**
     * Is plugin in standalone mode
     * @var bool is_standalone
     */
    private bool $is_standalone;

    /**
     * WP user capability to access plugin settings
     * @var string capability
     */
    const capability = 'manage_options';

    /**
     * WP Admin settings main page title
     * @var string page_title_main
     */
    private $page_title_main;

    /**
     * WP Admin settings main menu title
     * @var string menu_title_main
     */
    private $menu_title_main;

    /**
     * WP Admin settings main admin url slug admin.php?page=[slug]
     * @var string menu_slug_main
     */
    const menu_slug_main = 'coyote';

    /**
     * WP Admin settings icon
     * @var string menu_icon
     * TODO: replace with Coyote icon?
     */
    const menu_icon = 'dashicons-universal-access';

    /**
     * WP Admin settings position
     * @var int position
     */
    const position = 250;

    /**
     * Page slug used for main settings
     * used with settings_field(), do_settings_section() and register_setting()
     * @var string settings_slug_main
     */
    const settings_slug_main = 'coyote_fields';

    /**
     * settings section slug for main fields
     * used with add_settings_section() which triggers standalone mode activated page
     * @var string settings_section
     */
    const settings_section = 'settings_section';

    /**
     * settings section slug for api fields
     * used with add_settings_section()
     * @var string settings_section
     */
    const api_settings_section = 'api_settings_section';

    /**
     * settings section slug for standalone fields
     * used with add_settings_section()
     * @var string standalone_settings_section
     */
    const standalone_settings_section = 'standalone_settings_section';

    /**
     * WP Admin settings advanced page title
     * @var string subpage_title_advanced
     */
    private $subpage_title_advanced;

    /**
     * WP Admin settings advanced menu title
     * @var string submenu_title_advanced
     */
    private $submenu_title_advanced;

    /**
     * WP Admin settings advanced admin url slug admin.php?page=[slug]
     * @var string menu_slug_main
     */
    const submenu_advanced_slug = 'coyote-advanced';

    /**
     * Page slug used for advanced settings
     * used with settings_field(), do_settings_section() and register_setting()
     * @var string settings_slug_advanced
     */
    const settings_slug_advanced = 'coyote_fields_advanced';

    /**
     * settings section slug for advanced fields
     * used with add_settings_section()
     * @var string advanced_settings_section
     */
    const advanced_settings_section = 'advanced_settings_section';

    /**
     * WP Admin settings tools page title
     * @var string subpage_title_tools
     */
    private $subpage_title_tools;

    /**
     * WP Admin settings main menu title
     * @var string menu_title_main
     */
    private $submenu_title_tools;

    /**
     * WP Admin settings tools admin url slug admin.php?page=[slug]
     * @var string submenu_tools_slug
     */
    const submenu_tools_slug = 'coyote-tools';

    /**
     * Page slug used for tools settings
     * used with settings_field(), do_settings_section() and register_setting()
     * @var string settings_slug_tools
     */
    const settings_slug_tools = 'coyote_fields_tools';

    /**
     * settings section slug for tools fields
     * used with add_settings_section()
     * @var string tools_settings_section
     */
    const tools_settings_section = 'tools_settings_section';

    /**
     * @var mixed batch_job WordPress transient
     */
    private $batch_job;

    /**
     * @var bool profile_fetch_failed
     */
    private bool $profile_fetch_failed;

    /**
     * Twig environment
     */
    protected $twig;

    /**
     * Constructor
     */
    function __construct() {

        /*
         * Set page and menu titles via i18n functions
         */
        $this->page_title_main          = __('Coyote settings', WordPressPlugin::I18N_NS);
        $this->menu_title_main          = __('Coyote', WordPressPlugin::I18N_NS);
        $this->subpage_title_advanced   = __('Coyote advanced', WordPressPlugin::I18N_NS);
        $this->submenu_title_advanced   = __('Advanced', WordPressPlugin::I18N_NS);
        $this->subpage_title_tools      = __('Coyote tools', WordPressPlugin::I18N_NS);
        $this->submenu_title_tools      = __('Tools', WordPressPlugin::I18N_NS);

        /*
         * Set profile_fetch_failed to false and fetch profile
         * when successful profile_fetch_failed will be set to true
         */
        $this->profile_fetch_failed     = false;
        $this->profile                  = $this->getProfile();

        $this->batch_job                = BatchImportHelper::getBatchJob();

        /*
         * Check if standalone mode is active
         */
        $this->is_standalone            = PluginConfiguration::isStandalone();

        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

        add_action('admin_init', [$this, 'init']);
        add_action('admin_menu', [$this, 'menu']);

        if (!$this->is_standalone) {
            add_action('update_option_coyote_api_token', [$this, 'verify_settings'], 10, 3);
            add_action('update_option_coyote_api_endpoint', [$this, 'verify_settings'], 10, 3);
            add_action('update_option_coyote_api_organization_id', [$this, 'change_organization_id'], 10, 3);
            add_action('add_option_coyote_api_organization_id', [$this, 'set_organization_id'], 10, 2);
            add_action('update_option_coyote_is_standalone', [$this, 'change_standalone_mode'], 10, 3);
        }

        /*
         * Set Twig environment and add functions to it
         */
        $this->twig = new Environment(new FilesystemLoader(PluginConfiguration::TWIG_TEMPLATES_PATH));
        $this->twig = $this->set_twig_functions($this->twig);

    }

    public static function ajax_verify_resource_group() {
        $resourceGroupUrl = get_site_url(get_current_blog_id(), '/wp-json/coyote/v1/callback');

        $resourceGroup = WordPressCoyoteApiClient::createResourceGroup($resourceGroupUrl);

        if (!is_null($resourceGroup))
            PluginConfiguration::setResourceGroupId(intval($resourceGroup->getId()));

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

        wp_localize_script('coyote_settings_js', 'coyote_ajax_obj', [
            'ajax_url'  => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('coyote_ajax'),
            'endpoint'  => esc_url(get_option('coyote_processor_endpoint')),
            'job_id'    => $this->batch_job ? $this->batch_job['id'] : null,
            'job_type'  => $this->batch_job ? $this->batch_job['type'] : null,
        ]);
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

    /**
     * WP Admin main settings page
     * @void string HTML for page holding form with setting inputs
     */
    public function settings_page_cb() {

        echo $this->twig->render('CoyotePage.html.twig', [
            'pageTitle'             => $this->page_title_main,
            'isStandalone'          => $this->is_standalone,
            'profile'               => $this->profile,
            'profileFetchFailed'    => $this->profile_fetch_failed,
            'hasProfileMessage'     => __('Linked API profile: %s (role: %s)', WordPressPlugin::I18N_NS ),
            'noProfileMessage'      => __('Unable to load Coyote profile.', WordPressPlugin::I18N_NS ),
            'settingsSlug'          => self::settings_slug_main
        ]);
    }

    /**
     * WP Admin advanced settings page
     * @void string HTML for page holding form with setting inputs
     */
    public function settings_subpage_advanced_cb() {

        /*
         * Return when no profile is set or when in standalone
         */
        if ($this->is_standalone)
            return;

        echo $this->twig->render('AdvancedPage.html.twig', [
            'pageTitle'             => $this->settings_slug_advanced,
            'isStandalone'          => $this->is_standalone,
            'settingsSlug'          => self::settings_slug_advanced
        ]);
    }

    /**
     * WP Admin tools settings page
     * @void string HTML for page holding form with setting inputs
     */
    public function settings_subpage_tools_cb() {

        /*
         * Return when no profile is set or when in standalone
         */
        if (!$this->profile || $this->is_standalone)
            return;

        echo $this->twig->render('ToolsPage.html.twig', [
            'pageTitle'                 => $this->subpage_title_tools,
            'isStandalone'              => $this->is_standalone,
            'emptyOrganizationOption'   => empty(get_option('coyote_api_organization_id')),
            'processEndpoint'           => 'https://processor.coyote.pics',
            'batchJob'                  => $this->batch_job,
            'batchSize'                 => esc_html(get_option('coyote_processing_batch_size', 50)),
            'text'                      => [
                'processTitle'              => __('Process existing posts', WordPressPlugin::I18N_NS),
                'emptyOrganizationMessage'  => __('Please select a Coyote organization to process posts.', WordPressPlugin::I18N_NS),
                'explainingMessages'        => [
                    __('Using a remote service, your WordPress installation will be queried remotely and this process will populate the associated Coyote organisation. Depending on your WordPress installation, this process may take a while to complete.', WordPressPlugin::I18N_NS),
                    __('If the status of the processing job keeps resulting in an error, consider decreasing the batch size.', WordPressPlugin::I18N_NS),
                    __('This process does not modify your WordPress content itself, and may be used more than once.', WordPressPlugin::I18N_NS)
                ],
                'processEndpointLabel'      => __('Processor endpoint', WordPressPlugin::I18N_NS),
                'batchSizeLabel'            => __('Batch size', WordPressPlugin::I18N_NS),
                'processStatusLabel'        => __('Status', WordPressPlugin::I18N_NS),
                'processProgressLabel'      => __('Processing', WordPressPlugin::I18N_NS),
                'processCompleteLabel'      => __('Processing complete', WordPressPlugin::I18N_NS),
                'startProcessButtonText'    => __('Start processing job', WordPressPlugin::I18N_NS),
                'cancelProcessButtonText'   => __('Cancel processing job', WordPressPlugin::I18N_NS),
            ]
        ]);
    }

    /**
     * Register admin menu (sub)pages
     */
    public function menu() {

        /*
         * Register menu page for Coyote settings
         * The page is added in the main sidebar menu of WordPress
         */
        add_menu_page(
            $this->page_title_main,
            $this->menu_title_main,
            self::capability,
            self::menu_slug_main,
            [$this, 'settings_page_cb'],
            self::menu_icon,
            self::position
        );

        /*
         * Register submenu page for Coyote advanced settings
         * Only when not in standalone mode
         */
        if (!$this->is_standalone) {
            add_submenu_page(
                self::menu_slug_main,
                $this->subpage_title_advanced,
                $this->submenu_title_advanced,
                self::capability,
                self::submenu_advanced_slug,
                [$this, 'settings_subpage_advanced_cb'],
                self::position
            );
        }

        /*
         * Register submenu page for Coyote tools
         * Only when not in standalone mode and a valid profile is set
         */
        if (!$this->is_standalone && $this->profile) {
            add_submenu_page(
                self::menu_slug_main,
                $this->subpage_title_tools,
                $this->submenu_title_tools,
                self::capability,
                self::submenu_tools_slug,
                [$this, 'settings_subpage_tools_cb'],
                self::position
            );
        }

    }

    public function sanitize_boolean($option): bool {
        return !empty($option);
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

        register_setting(self::settings_slug_main, 'coyote_is_standalone', ['type' => 'boolean', 'sanitize_callback' => [$this, 'sanitize_boolean']]);

        if (!$this->is_standalone) {

            /*
             * Register admin page main settings
             */
            register_setting(self::settings_slug_main, 'coyote_api_token', ['type' => 'string', 'sanitize_callback' => [$this, 'sanitize_token']]);

            if ($this->profile)
                register_setting(self::settings_slug_main, 'coyote_api_organization_id', ['type' => 'integer', 'sanitize_callback' => [$this, 'sanitize_organization_id']]);

            /*
             * Register admin subpage advanced settings
             */
            register_setting(self::settings_slug_advanced, 'coyote_api_endpoint', ['type' => 'string', 'sanitize_callback' => [$this, 'sanitize_endpoint']]);
            register_setting(self::settings_slug_advanced, 'coyote_api_metum', ['type' => 'string', 'sanitize_callback' => [$this, 'sanitize_metum']]);
            register_setting(self::settings_slug_advanced, 'coyote_filters_enabled', ['type' => 'boolean', 'sanitize_callback' => [$this, 'sanitize_boolean']]);
            register_setting(self::settings_slug_advanced, 'coyote_updates_enabled', ['type' => 'boolean', 'sanitize_callback' => [$this, 'sanitize_boolean']]);
            register_setting(self::settings_slug_advanced, 'coyote_skip_unpublished_enabled', ['type' => 'boolean', 'sanitize_callback' => [$this, 'sanitize_boolean']]);

            /*
             * Register admin subpage tools settings
             */
            register_setting(self::settings_slug_tools, 'coyote_processor_endpoint', ['type' => 'string', 'sanitize_callback' => [$this, 'sanitize_endpoint']]);

        }

        /*
         * Register settings section
         * Overall section that is linked to all setting fields
         */
        add_settings_section(
            self::settings_section,
            __('Plugin settings', WordPressPlugin::I18N_NS),
            [$this, 'plugin_setting_section_cb'],
            self::settings_slug_main
        );

        /*
         * Check if in standalone mode, if so return (don't render any fields)
         * This check can't be placed before the previous add_settings_section (if so no admin page content is rendered)
         */
        if ($this->is_standalone)
            return;

        /*
         * Register api settings section
         */
        add_settings_section(
            self::api_settings_section,
            __('API settings', WordPressPlugin::I18N_NS),
            [$this, 'noop_setting_section_cb'],
            self::settings_slug_main
        );

        add_settings_field(
            'coyote_api_token',
            __('Token', WordPressPlugin::I18N_NS),
            [$this, 'api_token_cb'],
            self::settings_slug_main,
            self::api_settings_section,
            ['label_for' => 'coyote_api_token']
        );

        /*
         * Check if profile is set
         * This renders all Coyote settings fields
         */
        if ($this->profile) {

            add_settings_field(
                'coyote_api_organization_id',
                __('Organization', WordPressPlugin::I18N_NS),
                [$this, 'api_organization_id_cb'],
                self::settings_slug_main,
                self::api_settings_section,
                ['label_for' => 'coyote_api_organization_id']
            );

            /*
             * Register standalone settings section
             */
            add_settings_section(
                self::standalone_settings_section,
                __('Standalone settings', WordPressPlugin::I18N_NS),
                [$this, 'noop_setting_section_cb'],
                self::settings_slug_main
            );

            add_settings_field(
                'coyote_is_standalone',
                __('Run in standalone mode', WordPressPlugin::I18N_NS),
                [$this, 'settings_is_standalone_cb'],
                self::settings_slug_main,
                self::standalone_settings_section,
                ['label_for' => 'coyote_is_standalone']
            );
        }

        /*
         * Register advanced settings section
         */
        add_settings_section(
            self::advanced_settings_section,
            __('Advanced settings', WordPressPlugin::I18N_NS),
            [$this, 'noop_setting_section_cb'],
            self::settings_slug_advanced
        );

        add_settings_field(
            'coyote_api_endpoint',
            __('Endpoint', WordPressPlugin::I18N_NS),
            [$this, 'api_endpoint_cb'],
            self::settings_slug_advanced,
            self::advanced_settings_section,
            ['label_for' => 'coyote_api_endpoint']
        );

        /*
         * Check if profile is set, if not return
         * If no profile is set, the rendering stops at this point
         * only the required fields to link to the Coyote API are visible
         */
        if(!$this->profile)
            return;

        add_settings_field(
            'coyote_api_metum',
            __('Metum', WordPressPlugin::I18N_NS),
            [$this, 'api_metum_cb'],
            self::settings_slug_advanced,
            self::advanced_settings_section,
            ['label_for' => 'coyote_api_metum']
        );

        add_settings_field(
            'coyote_filters_enabled',
            __('Filter images through Coyote', WordPressPlugin::I18N_NS),
            [$this, 'settings_filters_enabled_cb'],
            self::settings_slug_advanced,
            self::advanced_settings_section,
            ['label_for' => 'coyote_filters_enabled']
        );

        add_settings_field(
            'coyote_updates_enabled',
            __('Enable Coyote remote description updates', WordPressPlugin::I18N_NS),
            [$this, 'settings_updates_enabled_cb'],
            self::settings_slug_advanced,
            self::advanced_settings_section,
            ['label_for' => 'coyote_updates_enabled']
        );

        add_settings_field(
            'coyote_skip_unpublished_enabled',
            __('Skip unpublished items when importing', WordPressPlugin::I18N_NS),
            [$this, 'settings_skip_unpublished_enabled_cb'],
            self::settings_slug_advanced,
            self::advanced_settings_section,
            ['label_for' => 'coyote_skip_unpublished_enabled']
        );

    }

    /**
     * WP Admin standalone page
     * @void string HTML for page showing standalone is active
     */
    public function plugin_setting_section_cb() {

        /*
         * Only render when in standalone mode
         */
        if ($this->is_standalone) {
            echo $this->twig->render('Partials/HiddenActionNotice.html.twig', [
                'type'                  => 'info',
                'title'                 => __('Standalone mode', WordPressPlugin::I18N_NS),
                'text'                  => __('Coyote is running in standalone mode. No settings are available, and no remote Coyote API is used to manage resources and descriptions. Any locally stored image descriptions will be used to describe images.', WordPressPlugin::I18N_NS),
                'buttonText'            => __('Turn off standalone mode', WordPressPlugin::I18N_NS),
                'hiddenAction'          => [
                    'id'                => 'coyote_is_standalone',
                    'value'             => 'false',
                ]
            ]);

            return;
        }

        echo $this->twig->render('Partials/Paragraph.html.twig', [
            'text' => __('In order to use the plugin, configure the API settings accordingly. Once your profile has been retrieved and an organisation has been selected, you can optionally process any existing posts, pages and images to populate the Coyote instance.', WordPressPlugin::I18N_NS),
        ]);
    }

    public function noop_setting_section_cb() {}

    public function api_endpoint_cb() {
        echo $this->twig->render('Partials/InputText.html.twig', [
            'name'                  => 'coyote_api_endpoint',
            'label'                 => __('The endpoint for your Coyote instance, e.g. "https://staging.coyote.pics".', WordPressPlugin::I18N_NS),
            'size'                  => 50,
            'value'                 => esc_url(pluginConfiguration::getApiEndPoint())
        ]);
    }

    public function api_token_cb() {
        echo $this->twig->render('Partials/InputText.html.twig', [
            'name'                  => 'coyote_api_token',
            'label'                 => __('The API token associated with your Coyote account.', WordPressPlugin::I18N_NS),
            'size'                  => 30,
            'value'                 => sanitize_text_field(pluginConfiguration::getApiToken())
        ]);
    }

    public function api_metum_cb() {
        echo $this->twig->render('Partials/InputText.html.twig', [
            'name'                  => 'coyote_api_metum',
            'label'                 => __('The metum used by the API to categorise image descriptions, e.g. "Alt".', WordPressPlugin::I18N_NS),
            'size'                  => 20,
            'value'                 => sanitize_text_field(pluginConfiguration::getMetum())
        ]);
    }

    public function api_organization_id_cb() {
        echo $this->twig->render('Partials/Select.html.twig', [
            'name'                  => 'coyote_api_organization_id',
            'label'                 => __('The metum used by the API to categorise image descriptions, e.g. "Alt".', WordPressPlugin::I18N_NS),
            'notSingleLabel'        => __('--select an organization--', WordPressPlugin::I18N_NS),
            'options'               => $this->profile->getOrganizations(),
            'currentOption'         => PluginConfiguration::getApiOrganizationId(),
            'alert'                 => [
                'id'                => 'coyote_org_change_alert',
                'message'           => __('Important: changing organization requires an import of coyote resources.', WordPressPlugin::I18N_NS),
            ]
        ]);
    }

    public function settings_is_standalone_cb() {
        echo $this->twig->render('Partials/InputCheckbox.html.twig', [
            'name'                  => 'coyote_is_standalone',
            'label'                 => __('The plugin does not attempt to communicate with the API. The plugin configuration becomes unavailable until standalone mode is again disabled.', WordPressPlugin::I18N_NS),
            'checked'               => PluginConfiguration::isStandalone()
        ]);
    }

    public function settings_filters_enabled_cb() {
        echo $this->twig->render('Partials/InputCheckbox.html.twig', [
            'name'                  => 'coyote_filters_enabled',
            'label'                 => __('The plugin manages image descriptions for posts, pages and media.', WordPressPlugin::I18N_NS),
            'checked'               => PluginConfiguration::hasFiltersEnabled()
        ]);
    }

    public function settings_updates_enabled_cb() {
        echo $this->twig->render('Partials/InputCheckbox.html.twig', [
            'name'                  => 'coyote_updates_enabled',
            'label'                 => __('The plugin responds to approved image description updates issued through the Coyote API.', WordPressPlugin::I18N_NS),
            'checked'               => PluginConfiguration::hasUpdatesEnabled()
        ]);
    }

    public function settings_skip_unpublished_enabled_cb() {
        echo $this->twig->render('Partials/InputCheckbox.html.twig', [
            'name'                  => 'coyote_skip_unpublished_enabled',
            'label'                 => __('During import the plugin skips unpublished posts and media library images contained in unpublished posts.', WordPressPlugin::I18N_NS),
            'checked'               => PluginConfiguration::isNotProcessingUnpublishedPosts()
        ]);
    }

    /*
     * Functions to add to a Twig environment
     */
    private function set_twig_functions($twig) {
        $twig->addFunction(new TwigFunction('settings_fields', function ($slug) {
            return settings_fields($slug);
        }));
        $twig->addFunction(new TwigFunction('do_settings_sections', function ($slug) {
            return do_settings_sections($slug);
        }));
        $twig->addFunction(new TwigFunction('submit_button', function () {
            return submit_button();
        }));
        $twig->addFunction(new TwigFunction('submit_button_text', function ($text) {
            return submit_button($text);
        }));

        return $twig;
    }
}
