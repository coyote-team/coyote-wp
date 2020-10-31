<?php

/**
 * Coyote Plugin
 * @package Coyote\Plugin
 */

namespace Coyote;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use Coyote\HooksAndFilters;
use Coyote\Helpers\ContentHelper;
use Coyote\Controllers\RestApiController;
use Coyote\Controllers\SettingsController;

class Plugin {
    private $is_installed = false;
    private $is_admin = false;
    private $version;

    public $has_filters_enabled = false;
    public $has_updates_enabled = false;
    public $file;

    public $is_standalone = false;
    public $is_standalone_error = false;

    public $config = [
        'CoyoteApiVersion'         => "1",
        'CoyoteApiToken'           => null,
        'CoyoteApiEndpoint'        => "",
        'CoyoteApiMetum'           => 'Alt',
        'CoyoteApiOrganizationId'  => null,
        'CoyoteApiResourceGroupId' => null,
        'ProcessTypes'          => ['page', 'post', 'attachment'],
        'ProcessStatuses'       => ['publish'],
    ];

    public $is_configured = false;

    /**
     * Plugin constructor.
     * @param string $file
     * @param string $version
     * @param bool $is_admin
     */
    public function __construct(string $file, string $version, bool $is_admin = false) {
        $this->file = $file;
        $this->version = $version;
        $this->is_admin = $is_admin;

        $this->is_standalone = get_option('coyote_is_standalone', false);
        $this->is_standalone_error = get_option('coyote_error_standalone', false);
        $this->is_installed = get_option('coyote_plugin_is_installed', false);

        $this->setup();
    }

    private function load_config() {
        $_config = $this->config;

        $_config['CoyoteApiVersion']   = get_option('coyote_api_version',  $_config['CoyoteApiVersion']);
        $_config['CoyoteApiToken']     = get_option('coyote_api_token',    $_config['CoyoteApiToken']);
        $_config['CoyoteApiEndpoint']  = get_option('coyote_api_endpoint', $_config['CoyoteApiEndpoint']);
        $_config['CoyoteApiMetum']     = get_option('coyote_api_metum',    $_config['CoyoteApiMetum']);

        $_config['CoyoteApiOrganizationId']  = intval(get_option('coyote_api_organization_id',   $_config['CoyoteApiOrganizationId']));
        $_config['CoyoteApiResourceGroupId'] = intval(get_option('coyote_api_resource_group_id', $_config['CoyoteApiResourceGroupId']));

        $_config['ProcessTypes']    = get_option('coyote_post_types',    $_config['ProcessTypes']);
        $_config['ProcessStatuses'] = get_option('coyote_post_statuses', $_config['ProcessStatuses']);

        if (get_option('coyote_api_profile', null) !== null) {
            $this->is_configured = true;
        }

        $this->config = $_config;
    }

    private function setup() {
        // $wpdb becomes available here
        global $wpdb;
        define('COYOTE_IMAGE_TABLE_NAME', $wpdb->prefix . 'coyote_image_resource');

        register_activation_hook($this->file, [$this, 'activate']);
        register_deactivation_hook($this->file, [$this, 'deactivate']);

        if (!$this->is_installed) {
            return;
        }

        $this->load_config();

        register_uninstall_hook($this->file, ['Coyote\Plugin', 'uninstall']);

        $this->has_filters_enabled = get_option('coyote_filters_enabled', false);

        // only load updates option if we're either not in standalone mode,
        // or in standalone mode caused by repeated errors.
        // Explicit standalone mode disables remote updates.
        if (!$this->is_standalone || $this->is_standalone_error) {
            $this->has_updates_enabled = get_option('coyote_updates_enabled', false);
        }

        (new HooksAndFilters($this))->run();

        $this->setup_controllers();
    }

    public function setup_controllers() {
        if ($this->is_admin) {
            (new SettingsController());
        }

        if ($this->is_configured && $this->has_updates_enabled) {
            // allow remote updates
            Logger::log('Updates enabled.');
            (new RestApiController($this->version, 1, $this->config['CoyoteApiOrganizationId'], $this->config['CoyoteApiMetum']));
        } else {
            Logger::log('Updates disabled.');
        }
    }

    private function replace_sql_variables(string $sql) {
        global $wpdb;

        $search_strings = array(
            '%image_resource_table_name%',
            '%wp_post_table_name%',
            '%charset_collate%'
        );

        $replace_strings = array(
            COYOTE_IMAGE_TABLE_NAME,
            $wpdb->prefix . 'posts',
            $wpdb->get_charset_collate()
        );

        $sql = str_replace($search_strings, $replace_strings, $sql);
        return $sql;
    }

    private function run_sql_query(string $sql) {
        global $wpdb;
        $wpdb->query($sql);
    }

    private function run_plugin_sql(string $path) {
        $file_sql = file_get_contents($path);
        $sql = $this->replace_sql_variables($file_sql);
        $this->run_sql_query($sql);
    }

    public function activate() {
        if ($this->is_installed) {
            Logger::log("Plugin was active previously, not adding table");
            return;
        }

        Logger::log("Activating plugin");
        // for some weird reason you can't create multiple tables at once?
        $this->run_plugin_sql(coyote_sql_file('create_resource_table.sql'));
        $this->is_installed = true;

        add_option('coyote_plugin_is_installed', $this->is_installed);
    }

    public function deactivate() {
        Logger::log('Deactivating plugin');
    }

    public function api_client() {
        $cfg = $this->config;

        return new ApiClient([
            'endpoint' => $cfg["CoyoteApiEndpoint"],
            'token' => $cfg["CoyoteApiToken"],
            'organization_id' => $cfg["CoyoteApiOrganizationId"],
            'api_version' => $cfg["CoyoteApiVersion"],
            'language' => 'en',
            'metum' => $cfg["CoyoteApiMetum"],
            'resource_group_id' => $cfg["CoyoteApiResourceGroupId"]
        ]);
    }

    public function classic_editor_data() {
        global $post;

        if (empty($post)) {
            return '';
        }

        if (empty($post->post_type)) {
            return '';
        }

        $prefix = implode('/', [$this->config['CoyoteApiEndpoint'], 'organizations', $this->config['CoyoteApiOrganizationId']]);
        $helper = new ContentHelper($post->post_content);
        $mapping = $helper->get_src_and_coyote_id();
        $json_mapping = json_encode($mapping, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return <<<js
<script>
    window.coyote = {};
    window.coyote.classic_editor = {
        postId: "{$post->ID}",
        prefix: "{$prefix}",
        mapping: $json_mapping
    };
</script>
js;
    }


    public static function uninstall() {
        global $coyote_plugin;
        Logger::log("Uninstalling plugin");

        Logger::log("Deleting table");
        $coyote_plugin->run_plugin_sql(coyote_sql_file('uninstall_plugin.sql'));

        Logger::log("Deleting options");
        $options = [
            'coyote_api_version', 'coyote_api_token', 'coyote_api_endpoint', 'coyote_api_metum', 'coyote_api_organization_id',
            'coyote_api_profile',
            'coyote_filters_enabled', 'coyote_updates_enabled', 'coyote_processor_endpoint',
            'coyote_plugin_is_installed'
        ];

        foreach ($options as $option) {
            delete_option($option);
        }
    }
}

