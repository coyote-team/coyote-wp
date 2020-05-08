<?php

namespace Coyote;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

require_once coyote_plugin_file('classes/class.logger.php');
require_once coyote_plugin_file('classes/class.batch-post-processor.php');
require_once coyote_plugin_file('classes/helpers/class.post-process-helper.php');
require_once coyote_plugin_file('classes/handlers/class.post-update-handler.php');
require_once coyote_plugin_file('classes/controllers/class.rest-api-controller.php');
require_once coyote_plugin_file('classes/controllers/class.settings-controller.php');

use Coyote\Logger;
use Coyote\BatchPostProcessor;
use Coyote\Handlers\PostUpdateHandler;
use Coyote\Helpers\PostProcessHelper;
use Coyote\Controllers\RestApiController;
use Coyote\Controllers\SettingsController;

class Plugin {
    private $is_activated = false;
    private $is_admin = false;
    private $process_posts_async_request;

    private $file;
    private $version;

    public $config = [
        'CoyoteApiVersion' => "1",
        'CoyoteApiToken' => null,
        'CoyoteApiEndpoint' => "",
        'CoyoteOrganizationId' => null
    ];

    public $is_configured = false;

    public function __construct(string $file, string $version, bool $is_admin = false) {
        if(get_option('coyote_plugin_is_activated', null) !== null) {
            $this->is_activated = true;
        }

        $this->file = $file;
        $this->version = $version;
        $this->is_admin = $is_admin;

        $this->setup();
    }

    private function load_config() {
        $_config = $this->config;

        $_config['CoyoteApiVersion']     = get_option('coyote__api_settings_version', $_config['CoyoteApiVersion']);
        $_config['CoyoteApiToken']       = get_option('coyote__api_settings_token', $_config['CoyoteApiToken']);
        $_config['CoyoteApiEndpoint']    = get_option('coyote__api_settings_endpoint', $_config['CoyoteApiEndpoint']);
        $_config['CoyoteOrganizationId'] = get_option('coyote__api_settings_organization_id', $_config['CoyoteOrganizationId']);

        if (get_option('coyote__api_profile')) {
            $this->is_configured = true;
        }

        $this->config = $_config;
    }

    private function setup() {
        $this->load_config();

        (new RestApiController($this->version));

        if ($this->is_admin) {
            (new SettingsController($this->version));

            // only allow post processing if there is a valid api configuration
            // and there is not already a post-processing in place.
            if ($this->is_activated && $this->is_configured) {
                Logger::log("Configuring hooks");
                add_action('coyote_process_existing_posts', array($this, 'process_existing_posts'), 10, 1);
                add_filter('wp_insert_post_data', array('Coyote\Handlers\PostUpdateHandler', 'run'), 10, 2);
                $this->batch_processor = new AsyncPostProcessProcess();
            } else {
                Logger::log("Not activated or configured; skipping hooks");
            }
        }

        // $wpdb becomes available here
        global $wpdb;
        define('COYOTE_IMAGE_TABLE_NAME', $wpdb->prefix . 'coyote_image_resource');
        define('COYOTE_JOIN_TABLE_NAME', $wpdb->prefix . 'coyote_resource_post_jt');

        register_activation_hook($this->file, array($this, 'activate'));
        register_deactivation_hook($this->file, array($this, 'deactivate'));

        add_action('plugins_loaded', array($this, 'loaded'), 10, 0);
    }

    public function loaded() {
        // The loading order for this is important, otherwise required WP functions aren't available
        if ($this->is_activated && $this->is_configured && BatchPostProcessorState::has_stale_state()) {
            do_action('coyote_process_existing_posts');
        }
    }

    private function replace_sql_variables(string $sql) {
        global $wpdb;

        $search_strings = array(
            '%image_resource_table_name%',
            '%resource_post_join_table_name%',
            '%wp_post_table_name%',
            '%charset_collate%'
        );
        
        $replace_strings = array(
            COYOTE_IMAGE_TABLE_NAME,
            COYOTE_JOIN_TABLE_NAME,
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

    public function process_existing_posts() {
        $this->batch_processor->dispatch();
    }

    public static function get_api_client() {
        global $coyote_plugin;
        $client = new ApiClient(
            $coyote_plugin->config["CoyoteApiEndpoint"],
            $coyote_plugin->config["CoyoteApiToken"],
            $coyote_plugin->config["CoyoteOrganizationId"],
            $coyote_plugin->config["CoyoteApiVersion"]
        );

        return $client;
    }

    public function activate() {
        if ($this->is_activated) {
            Logger::log("Plugin already active");
            return;
        }

        Logger::log("Activating plugin");
        // for some weird reason you can't create multiple tables at once?
        $this->run_plugin_sql(coyote_sql_file('create_resource_table.sql'));
        $this->run_plugin_sql(coyote_sql_file('create_join_table.sql'));

        $this->is_activated = true;
        add_option('coyote_plugin_is_activated', $this->is_activated);
    }

    public function deactivate() {
        Logger::log("Deactivating plugin");
        // don't trigger update filters when removing coyote ids
        remove_filter('wp_insert_post_data', array('Coyote\Handlers\PostUpdateHandler', 'run'), 10);

        try {
            PostProcessHelper::restoreImages();
        } catch (Exception $error) {
            Logger::log("Error restoring images: " . $error->getMessage());
        } finally {
            $this->run_plugin_sql(coyote_sql_file('deactivate_plugin.sql'));
            delete_option('coyote_plugin_is_activated');
        }
    }
}

