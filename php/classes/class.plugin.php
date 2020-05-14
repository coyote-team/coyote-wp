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

use Coyote\Logger;
use Coyote\AsyncProcessRequest;
use Coyote\BatchPostProcessorState;
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
        // $wpdb becomes available here
        global $wpdb;
        define('COYOTE_IMAGE_TABLE_NAME', $wpdb->prefix . 'coyote_image_resource');
        define('COYOTE_JOIN_TABLE_NAME', $wpdb->prefix . 'coyote_resource_post_jt');

        register_activation_hook($this->file, array($this, 'activate'));
        register_deactivation_hook($this->file, array($this, 'deactivate'));

        $this->load_config();

        if (!$this->is_activated) {
            return;
        }

        add_filter('plugin_action_links_' . plugin_basename($this->file), array($this, 'add_action_links'));

        if (!$this->is_configured) {
            return;
        }

        (new RestApiController($this->version));
        add_filter('wp_insert_post_data', array('Coyote\Handlers\PostUpdateHandler', 'run'), 10, 2);
        add_action('plugins_loaded', array($this, 'loaded'), 10, 0);

        if (!$this->is_admin) {
            return;
        }

        (new SettingsController($this->version));

        // only allow post processing if there is a valid api configuration
        // and there is not already a post-processing in place.
        add_action('coyote_process_existing_posts', array($this, 'process_existing_posts'), 10, 1);
        $this->async_process_request = new AsyncProcessRequest();
    }

    public function loaded() {
        // The loading order for this is important, otherwise required WP functions aren't available
        if (BatchPostProcessorState::has_stale_state()) {
            do_action('coyote_process_existing_posts');
        }
    }

    public function add_action_links($links) {
        $settings_links = array(
            '<a href="' . admin_url('options-general.php?page=coyote_fields') . '"> ' . __('Settings') . '</a>',
        );

        return array_merge($links, $settings_links);
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

    public function process_existing_posts($batch_size = 5) {
        $batch_size = isset($_POST['batchSize']) ? intval($_POST['batchSize']) : $batch_size;

        // minimum batch size
        if ($batch_size < 1) { $batch_size = 1; }

        // maximum batch size
        else if ($batch_size > 500) { $batch_size = 500; }

        $this->async_process_request->data(array('batch_size' => $batch_size));
        $this->async_process_request->dispatch();
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
            //TODO get this setting from config
            PostProcessHelper::restore_images();
        } catch (Exception $error) {
            Logger::log("Error restoring images: " . $error->getMessage());
        } finally {
            $this->run_plugin_sql(coyote_sql_file('deactivate_plugin.sql'));
            delete_option('coyote_plugin_is_activated');
        }
    }
}

