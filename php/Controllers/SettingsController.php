<?php

namespace Coyote\Controllers;

if (!defined('WP_INC')) {
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

class SettingsController
{
    use Logger;

    /**
     * Profile fetched from API
     */
    private ?ProfileModel $profile;

    /**
     * Is plugin in standalone mode
     * @var bool isStandalone
     */
    private bool $isStandalone;

    /**
     * WP user capability to access plugin settings
     * @var string CAPABILITY
     */
    const CAPABILITY = 'manage_options';

    /**
     * WP Admin settings main page title
     * @var string mainPageTitle
     */
    private $mainPageTitle;

    /**
     * WP Admin settings main menu title
     * @var string mainMenuTitle
     */
    private $mainMenuTitle;

    /**
     * WP Admin settings main admin url slug admin.php?page=[slug]
     * @var string MAIN_MENU_SLUG
     */
    const MAIN_MENU_SLUG = 'coyote';

    /**
     * WP Admin settings icon
     * @var string MENU_ICON
     * TODO: replace with Coyote icon?
     */
    const MENU_ICON = 'dashicons-universal-access';

    /**
     * WP Admin settings position
     * @var int POSITION
     */
    const POSITION = 250;

    /**
     * Page slug used for main settings
     * used with settings_field(), do_settings_section() and register_setting()
     * @var string MAIN_SETTINGS_SLUG
     */
    const MAIN_SETTINGS_SLUG = 'coyote_fields';

    /**
     * settings section slug for main fields
     * used with add_settings_section() which triggers standalone mode activated page
     * @var string SETTINGS_SECTION
     */
    const SETTINGS_SECTION = 'settings_section';

    /**
     * settings section slug for api fields
     * used with add_settings_section()
     * @var string API_SETTINGS_SECTION
     */
    const API_SETTINGS_SECTION = 'api_settings_section';

    /**
     * settings section slug for standalone fields
     * used with add_settings_section()
     * @var string STANDALONE_SETTINGS_SECTION
     */
    const STANDALONE_SETTINGS_SECTION = 'standalone_settings_section';

    /**
     * WP Admin settings advanced page title
     * @var string advancedSubpageTitle
     */
    private string $advancedSubpageTitle;

    /**
     * WP Admin settings advanced menu title
     * @var string advancedSubmenuTitles
     */
    private string $advancedSubmenuTitles;

    /**
     * WP Admin settings advanced admin url slug admin.php?page=[slug]
     * @var string ADVANCED_SUBMENU_SLUG
     */
    const ADVANCED_SUBMENU_SLUG = 'coyote-advanced';

    /**
     * Page slug used for advanced settings
     * used with settings_field(), do_settings_section() and register_setting()
     * @var string ADVANCED_SETTINGS_SLUG
     */
    const ADVANCED_SETTINGS_SLUG = 'coyote_fields_advanced';

    /**
     * settings section slug for advanced fields
     * used with add_settings_section()
     * @var string ADVANCED_SETTINGS_SECTION
     */
    const ADVANCED_SETTINGS_SECTION = 'advanced_settings_section';

    /**
     * WP Admin settings tools page title
     * @var string toolsSubpageTitle
     */
    private string $toolsSubpageTitle;

    /**
     * WP Admin settings tools submenu title
     * @var string $toolsSubmenuTitle
     */
    private string $toolsSubmenuTitle;

    /**
     * WP Admin settings tools admin url slug admin.php?page=[slug]
     * @var string TOOLS_SUBMENU_SLUG
     */
    const TOOLS_SUBMENU_SLUG = 'coyote-tools';

    /**
     * Page slug used for tools settings
     * used with settings_field(), do_settings_section() and register_setting()
     * @var string TOOLS_SETTINGS_SLUG
     */
    const TOOLS_SETTINGS_SLUG = 'coyote_fields_tools';

    /**
     * settings section slug for tools fields
     * used with add_settings_section()
     * @var string TOOLS_SETTINGS_SECTION
     */
    const TOOLS_SETTINGS_SECTION = 'tools_settings_section';

    /**
     * @var mixed batch_job WordPress transient
     */
    private string $batchJob;

    /**
     * @var bool profile_fetch_failed
     */
    private bool $profileFetchFailed;

    /**
     * Twig environment
     */
    protected Environment $twig;

    /**
     * Constructor
     */
    public function __construct()
    {

        /*
         * Set page and menu titles via i18n functions
         */
        $this->mainPageTitle = __('Coyote settings', WordPressPlugin::I18N_NS);
        $this->mainMenuTitle = __('Coyote', WordPressPlugin::I18N_NS);
        $this->advancedSubpageTitle = __('Coyote advanced', WordPressPlugin::I18N_NS);
        $this->advancedSubmenuTitle = __('Advanced', WordPressPlugin::I18N_NS);
        $this->toolsSubpageTitle = __('Coyote tools', WordPressPlugin::I18N_NS);
        $this->toolsSubmenuTitle = __('Tools', WordPressPlugin::I18N_NS);

        /*
         * Set profile_fetch_failed to false and fetch profile
         * when successful profile_fetch_failed will be set to true
         */
        $this->profileFetchFailed = false;
        $this->profile = $this->getProfile();

        $this->batchJob = BatchImportHelper::getBatchJob();

        /*
         * Check if standalone mode is active
         */
        $this->isStandalone = PluginConfiguration::isStandalone();

        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);

        add_action('admin_init', [$this, 'init']);
        add_action('admin_menu', [$this, 'menu']);

        if (!$this->isStandalone) {
            add_action('update_option_coyote_api_token', [$this, 'verifySettings'], 10, 3);
            add_action('update_option_coyote_api_endpoint', [$this, 'verifySettings'], 10, 3);
            add_action('update_option_coyote_api_organization_id', [$this, 'changeOrganizationId'], 10, 3);
            add_action('add_option_coyote_api_organization_id', [$this, 'setOrganizationId'], 10, 2);
            add_action('update_option_coyote_is_standalone', [$this, 'changeStandaloneMode'], 10, 3);
        }

        /*
         * Set Twig environment and add functions to it
         */
        $this->twig = new Environment(new FilesystemLoader(PluginConfiguration::TWIG_TEMPLATES_PATH));
        $this->twig = $this->setTwigFunctions($this->twig);
    }

    public static function ajaxVerifyResourceGroup()
    {
        $resourceGroupUrl = get_site_url(get_current_blog_id(), '/wp-json/coyote/v1/callback');

        $resourceGroup = WordPressCoyoteApiClient::createResourceGroup($resourceGroupUrl);

        if (!is_null($resourceGroup)) {
            PluginConfiguration::setResourceGroupId(intval($resourceGroup->getId()));
        }

        wp_die();
    }

    public function enqueueScripts()
    {
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
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('coyote_ajax'),
            'endpoint' => esc_url(get_option('coyote_processor_endpoint')),
            'job_id' => $this->batchJob ? $this->batchJob['id'] : null,
            'job_type' => $this->batchJob ? $this->batchJob['type'] : null,
        ]);
    }

    public function verifySettings($old, $new, $option)
    {
        $profile = WordPressCoyoteApiClient::getProfile();

        if (is_null($profile)) {
            $this->profileFetchFailed = true;
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

    public function changeStandaloneMode($old, $new, $option): void
    {
        //FIXME use PluginConfiguration
        //clear any data about what caused standalone mode to be active, if any
        update_option('coyote_error_standalone', false);
        delete_transient('coyote_api_error_count');
    }

    public function setOrganizationId($option, $value): void
    {
        self::logDebug('setting organization id', [$option, $value]);
        $this->changeOrganizationId(null, $value, $option);
    }

    public function changeOrganizationId($old, $new, $option): void
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

    private function getProfile(): ?ProfileModel
    {
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
    public function settingsPageCallback()
    {

        echo $this->twig->render('CoyotePage.html.twig', [
            'pageTitle' => $this->mainPageTitle,
            'isStandalone' => $this->isStandalone,
            'profile' => $this->profile,
            'profileFetchFailed' => $this->profileFetchFailed,
            'hasProfileMessage' => __('Linked API profile: %s (role: %s)', WordPressPlugin::I18N_NS),
            'noProfileMessage' => __('Unable to load Coyote profile.', WordPressPlugin::I18N_NS),
            'settingsSlug' => self::MAIN_SETTINGS_SLUG
        ]);
    }

    /**
     * WP Admin advanced settings page
     * @void string HTML for page holding form with setting inputs
     */
    public function advancedSettingsPageCallback()
    {

        /*
         * Return when no profile is set or when in standalone
         */
        if ($this->isStandalone) {
            return;
        }

        echo $this->twig->render('AdvancedPage.html.twig', [
            // FIXME pageTitle is not the right var
            'pageTitle' => $this->advancedSubmenuTitle,
            'isStandalone' => $this->isStandalone,
            'settingsSlug' => self::ADVANCED_SETTINGS_SLUG
        ]);
    }

    /**
     * WP Admin tools settings page
     * @void string HTML for page holding form with setting inputs
     */
    public function toolsSettingsPageCallback()
    {

        /*
         * Return when no profile is set or when in standalone
         */
        if (!$this->profile || $this->isStandalone) {
            return;
        }

        echo $this->twig->render('ToolsPage.html.twig', [
            'pageTitle' => $this->toolsSubpageTitle,
            'isStandalone' => $this->isStandalone,
            'emptyOrganizationOption' => empty(get_option('coyote_api_organization_id')),
            'processEndpoint' => 'https://processor.coyote.pics',
            'batchJob' => $this->batchJob,
            'batchSize' => esc_html(get_option('coyote_processing_batch_size', 50)),
            'text' => [
                'processTitle' => __('Process existing posts', WordPressPlugin::I18N_NS),
                'emptyOrganizationMessage' => __('Please select a Coyote organization to process posts.', WordPressPlugin::I18N_NS),
                'explainingMessages' => [
                    __('Using a remote service, your WordPress installation will be queried remotely and this process will populate the associated Coyote organisation. Depending on your WordPress installation, this process may take a while to complete.', WordPressPlugin::I18N_NS),
                    __('If the status of the processing job keeps resulting in an error, consider decreasing the batch size.', WordPressPlugin::I18N_NS),
                    __('This process does not modify your WordPress content itself, and may be used more than once.', WordPressPlugin::I18N_NS)
                ],
                'processEndpointLabel' => __('Processor endpoint', WordPressPlugin::I18N_NS),
                'batchSizeLabel' => __('Batch size', WordPressPlugin::I18N_NS),
                'processStatusLabel' => __('Status', WordPressPlugin::I18N_NS),
                'processProgressLabel' => __('Processing', WordPressPlugin::I18N_NS),
                'processCompleteLabel' => __('Processing complete', WordPressPlugin::I18N_NS),
                'startProcessButtonText' => __('Start processing job', WordPressPlugin::I18N_NS),
                'cancelProcessButtonText' => __('Cancel processing job', WordPressPlugin::I18N_NS),
            ]
        ]);
    }

    /**
     * Register admin menu (sub)pages
     */
    public function menu()
    {

        /*
         * Register menu page for Coyote settings
         * The page is added in the main sidebar menu of WordPress
         */
        add_menu_page(
            $this->mainPageTitle,
            $this->mainMenuTitle,
            self::CAPABILITY,
            self::MAIN_MENU_SLUG,
            [$this, 'settingsPageCallback'],
            self::MENU_ICON,
            self::POSITION
        );

        /*
         * Register submenu page for Coyote advanced settings
         * Only when not in standalone mode
         */
        if (!$this->isStandalone) {
            add_submenu_page(
                self::MAIN_MENU_SLUG,
                $this->advancedSubpageTitle,
                $this->advancedSubmenuTitle,
                self::CAPABILITY,
                self::ADVANCED_SUBMENU_SLUG,
                [$this, 'advancedSettingsPageCallback'],
                self::POSITION
            );
        }

        /*
         * Register submenu page for Coyote tools
         * Only when not in standalone mode and a valid profile is set
         */
        if (!$this->isStandalone && $this->profile) {
            add_submenu_page(
                self::MAIN_MENU_SLUG,
                $this->toolsSubpageTitle,
                $this->toolsSubmenuTitle,
                self::CAPABILITY,
                self::TOOLS_SUBMENU_SLUG,
                [$this, 'toolsSettingsPageCallback'],
                self::POSITION
            );
        }
    }

    public function sanitizeBool(bool $option): bool
    {
        return !empty($option);
    }

    public function sanitizeEndpoint(string $endpoint): string
    {
        if (!empty($endpoint)) {
            // check if it's a valid url
            if (filter_var($endpoint, FILTER_VALIDATE_URL) !== false) {
                return esc_url($endpoint);
            }
        }

        return '';
    }

    public function sanitizeToken(string $token): string
    {
        return esc_html($token);
    }

    public function sanitizeMetum(string $metum): string
    {
        return esc_html($metum);
    }

    public function sanitizeOrganizationId(string $organizationId): string
    {
        // TODO validate the organization id is valid?
        return esc_html($organizationId);
    }

    public function init()
    {
        register_setting(
            self::MAIN_SETTINGS_SLUG,
            'coyote_is_standalone',
            ['type' => 'boolean', 'sanitize_callback' => [$this, 'sanitizeBool']]
        );

        if (!$this->isStandalone) {
            /*
             * Register admin page main settings
             */
            register_setting(
                self::MAIN_SETTINGS_SLUG,
                'coyote_api_token',
                ['type' => 'string', 'sanitize_callback' => [$this, 'sanitizeToken']]
            );

            if ($this->profile) {
                register_setting(
                    self::MAIN_SETTINGS_SLUG,
                    'coyote_api_organization_id',
                    ['type' => 'integer', 'sanitize_callback' => [$this, 'sanitizeOrganizationId']]
                );
            }

            /*
             * Register admin subpage advanced settings
             */
            register_setting(
                self::ADVANCED_SETTINGS_SLUG,
                'coyote_api_endpoint',
                ['type' => 'string', 'sanitize_callback' => [$this, 'sanitizeEndpoint']]
            );

            register_setting(
                self::ADVANCED_SETTINGS_SLUG,
                'coyote_api_metum',
                ['type' => 'string', 'sanitize_callback' => [$this, 'sanitizeMetum']]
            );
            register_setting(
                self::ADVANCED_SETTINGS_SLUG,
                'coyote_filters_enabled',
                ['type' => 'boolean', 'sanitize_callback' => [$this, 'sanitizeBool']]
            );

            register_setting(
                self::ADVANCED_SETTINGS_SLUG,
                'coyote_updates_enabled',
                ['type' => 'boolean', 'sanitize_callback' => [$this, 'sanitizeBool']]
            );

            register_setting(
                self::ADVANCED_SETTINGS_SLUG,
                'coyote_skip_unpublished_enabled',
                ['type' => 'boolean', 'sanitize_callback' => [$this, 'sanitizeBool']]
            );

            /*
             * Register admin subpage tools settings
             */
            register_setting(
                self::TOOLS_SETTINGS_SLUG,
                'coyote_processor_endpoint',
                ['type' => 'string', 'sanitize_callback' => [$this, 'sanitizeEndpoint']]
            );
        }

        /*
         * Register settings section
         * Overall section that is linked to all setting fields
         */
        add_settings_section(
            self::SETTINGS_SECTION,
            __('Plugin settings', WordPressPlugin::I18N_NS),
            [$this, 'pluginSettingsSectionCallback'],
            self::MAIN_SETTINGS_SLUG
        );

        /*
         * Check if in standalone mode, if so return (don't render any fields)
         * This check can't be placed before the previous add_settings_section (if so no admin page content is rendered)
         */
        if ($this->isStandalone) {
            return;
        }

        /*
         * Register api settings section
         */
        add_settings_section(
            self::API_SETTINGS_SECTION,
            __('API settings', WordPressPlugin::I18N_NS),
            [$this, 'noOp'],
            self::MAIN_SETTINGS_SLUG
        );

        add_settings_field(
            'coyote_api_token',
            __('Token', WordPressPlugin::I18N_NS),
            [$this, 'apiTokenCallback'],
            self::MAIN_SETTINGS_SLUG,
            self::API_SETTINGS_SECTION,
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
                [$this, 'apiOrgIdCallback'],
                self::MAIN_SETTINGS_SLUG,
                self::API_SETTINGS_SECTION,
                ['label_for' => 'coyote_api_organization_id']
            );

            /*
             * Register standalone settings section
             */
            add_settings_section(
                self::STANDALONE_SETTINGS_SECTION,
                __('Standalone settings', WordPressPlugin::I18N_NS),
                [$this, 'noOp'],
                self::MAIN_SETTINGS_SLUG
            );

            add_settings_field(
                'coyote_is_standalone',
                __('Run in standalone mode', WordPressPlugin::I18N_NS),
                [$this, 'isStandaloneCallback'],
                self::MAIN_SETTINGS_SLUG,
                self::STANDALONE_SETTINGS_SECTION,
                ['label_for' => 'coyote_is_standalone']
            );
        }

        /*
         * Register advanced settings section
         */
        add_settings_section(
            self::ADVANCED_SETTINGS_SECTION,
            __('Advanced settings', WordPressPlugin::I18N_NS),
            [$this, 'noOp'],
            self::ADVANCED_SETTINGS_SLUG
        );

        add_settings_field(
            'coyote_api_endpoint',
            __('Endpoint', WordPressPlugin::I18N_NS),
            [$this, 'apiEndpointCallback'],
            self::ADVANCED_SETTINGS_SLUG,
            self::ADVANCED_SETTINGS_SECTION,
            ['label_for' => 'coyote_api_endpoint']
        );

        /*
         * Check if profile is set, if not return
         * If no profile is set, the rendering stops at this point
         * only the required fields to link to the Coyote API are visible
         */
        if (!$this->profile) {
            return;
        }

        add_settings_field(
            'coyote_api_metum',
            __('Metum', WordPressPlugin::I18N_NS),
            [$this, 'apiMetumCallback'],
            self::ADVANCED_SETTINGS_SLUG,
            self::ADVANCED_SETTINGS_SECTION,
            ['label_for' => 'coyote_api_metum']
        );

        add_settings_field(
            'coyote_filters_enabled',
            __('Filter images through Coyote', WordPressPlugin::I18N_NS),
            [$this, 'areFiltersEnabledCallback'],
            self::ADVANCED_SETTINGS_SLUG,
            self::ADVANCED_SETTINGS_SECTION,
            ['label_for' => 'coyote_filters_enabled']
        );

        add_settings_field(
            'coyote_updates_enabled',
            __('Enable Coyote remote description updates', WordPressPlugin::I18N_NS),
            [$this, 'areUpdatesEnabledCallback'],
            self::ADVANCED_SETTINGS_SLUG,
            self::ADVANCED_SETTINGS_SECTION,
            ['label_for' => 'coyote_updates_enabled']
        );

        add_settings_field(
            'coyote_skip_unpublished_enabled',
            __('Skip unpublished items when importing', WordPressPlugin::I18N_NS),
            [$this, 'isSkipUnpublishedEnabledCallback'],
            self::ADVANCED_SETTINGS_SLUG,
            self::ADVANCED_SETTINGS_SECTION,
            ['label_for' => 'coyote_skip_unpublished_enabled']
        );
    }

    /**
     * WP Admin standalone page
     * @void string HTML for page showing standalone is active
     */
    public function pluginSettingsSectionCallback()
    {
        /*
         * Only render when in standalone mode
         */
        if ($this->isStandalone) {
            echo $this->twig->render('Partials/HiddenActionNotice.html.twig', [
                'type' => 'info',
                'title' => __('Standalone mode', WordPressPlugin::I18N_NS),
                'text' => __('Coyote is running in standalone mode. No settings are available, and no remote Coyote API is used to manage resources and descriptions. Any locally stored image descriptions will be used to describe images.', WordPressPlugin::I18N_NS),
                'buttonText' => __('Turn off standalone mode', WordPressPlugin::I18N_NS),
                'hiddenAction' => [
                    'id' => 'coyote_is_standalone',
                    'value' => 'false',
                ]
            ]);

            return;
        }

        echo $this->twig->render('Partials/Paragraph.html.twig', [
            'text' => __('In order to use the plugin, configure the API settings accordingly. Once your profile has been retrieved and an organisation has been selected, you can optionally process any existing posts, pages and images to populate the Coyote instance.', WordPressPlugin::I18N_NS),
        ]);
    }

    public function noOp()
    {
    }

    public function apiEndpointCallback()
    {
        echo $this->twig->render('Partials/InputText.html.twig', [
            'name' => 'coyote_api_endpoint',
            'label' => __('The endpoint for your Coyote instance, e.g. "https://staging.coyote.pics".', WordPressPlugin::I18N_NS),
            'size' => 50,
            'value' => esc_url(pluginConfiguration::getApiEndPoint())
        ]);
    }

    public function apiTokenCallback()
    {
        echo $this->twig->render('Partials/InputText.html.twig', [
            'name' => 'coyote_api_token',
            'label' => __('The API token associated with your Coyote account.', WordPressPlugin::I18N_NS),
            'size' => 30,
            'value' => sanitize_text_field(pluginConfiguration::getApiToken())
        ]);
    }

    public function apiMetumCallback()
    {
        echo $this->twig->render('Partials/InputText.html.twig', [
            'name' => 'coyote_api_metum',
            'label' => __('The metum used by the API to categorise image descriptions, e.g. "Alt".', WordPressPlugin::I18N_NS),
            'size' => 20,
            'value' => sanitize_text_field(pluginConfiguration::getMetum())
        ]);
    }

    public function apiOrgIdCallback()
    {
        echo $this->twig->render('Partials/Select.html.twig', [
            'name' => 'coyote_api_organization_id',
            'label' => __('The metum used by the API to categorise image descriptions, e.g. "Alt".', WordPressPlugin::I18N_NS),
            'notSingleLabel' => __('--select an organization--', WordPressPlugin::I18N_NS),
            'options' => $this->profile->getOrganizations(),
            'currentOption' => PluginConfiguration::getApiOrganizationId(),
            'alert' => [
                'id' => 'coyote_org_change_alert',
                'message' => __('Important: changing organization requires an import of coyote resources.', WordPressPlugin::I18N_NS),
            ]
        ]);
    }

    public function isStandaloneCallback()
    {
        echo $this->twig->render('Partials/InputCheckbox.html.twig', [
            'name' => 'coyote_is_standalone',
            'label' => __('The plugin does not attempt to communicate with the API. The plugin configuration becomes unavailable until standalone mode is again disabled.', WordPressPlugin::I18N_NS),
            'checked' => PluginConfiguration::isStandalone()
        ]);
    }

    public function areFiltersEnabledCallback()
    {
        echo $this->twig->render('Partials/InputCheckbox.html.twig', [
            'name' => 'coyote_filters_enabled',
            'label' => __('The plugin manages image descriptions for posts, pages and media.', WordPressPlugin::I18N_NS),
            'checked' => PluginConfiguration::hasFiltersEnabled()
        ]);
    }

    public function areUpdatesEnabledCallback()
    {
        echo $this->twig->render('Partials/InputCheckbox.html.twig', [
            'name' => 'coyote_updates_enabled',
            'label' => __('The plugin responds to approved image description updates issued through the Coyote API.', WordPressPlugin::I18N_NS),
            'checked' => PluginConfiguration::hasUpdatesEnabled()
        ]);
    }

    public function isSkipUnpublishedEnabledCallback()
    {
        echo $this->twig->render('Partials/InputCheckbox.html.twig', [
            'name' => 'coyote_skip_unpublished_enabled',
            'label' => __('During import the plugin skips unpublished posts and media library images contained in unpublished posts.', WordPressPlugin::I18N_NS),
            'checked' => PluginConfiguration::isNotProcessingUnpublishedPosts()
        ]);
    }

    /*
     * Functions to add to a Twig environment
     */
    private function setTwigFunctions($twig)
    {
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
