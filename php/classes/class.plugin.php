<?php

namespace Coyote;

require_once coyote_plugin_file('classes/class.logger.php');
require_once coyote_plugin_file('classes/handlers/class.post-update-handler.php');
require_once coyote_plugin_file('classes/controllers/class.rest-api-controller.php');

use Coyote\Logger;
use Coyote\Handlers\PostUpdateHandler;
use Coyote\Controllers\RestApiController;

class Plugin {
    private $is_activated = false;

    private $file;
    private $version;

    private $post_update_handler;
    private $api_client;

    static $config = [
        'CoyoteApiToken' => COYOTE_API_TOKEN,
        'CoyoteApiEndpoint' => 'https://staging.coyote.pics',
        'CoyoteOrganizationId' => 1
    ];

    public function __construct(string $file, string $version) {
        if(get_option('coyote_plugin_is_activated', null) !== null) {
            $this->is_activated = true;
        }

        $this->file = $file;
        $this->version = $version;

        $this->post_update_handler = new Handlers\PostUpdateHandler();

        $this->setup();
    }

    private function setup() {
        $controller = new RestApiController($this->version);

        // $wpdb becomes available here
        global $wpdb;
        define('COYOTE_IMAGE_TABLE_NAME', $wpdb->prefix . 'coyote_image_resource');
        define('COYOTE_JOIN_TABLE_NAME', $wpdb->prefix . 'coyote_resource_post_jt');

        register_activation_hook($this->file, array($this, 'activate'));
        register_deactivation_hook($this->file, array($this, 'deactivate'));

        add_action('plugins_loaded', array($this, 'load'));
        add_action('save_post', array($this->post_update_handler, 'run'), 10, 3);
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

    private function process_existing_posts() {
        $posts = get_posts(array(
            'numberposts' => -1 //all
        ));

        foreach ($posts as $post) {
            // simulate a post update
            Logger::log("Processing post {$post->ID}");
            //$this->post_update_handler->run($post->ID, $post, true)
        }
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
        $this->process_existing_posts();

        $this->is_activated = true;
        add_option('coyote_plugin_is_activated', $this->is_activated);
    }

    public function deactivate() {
        Logger::log("Deactivating plugin");
        $this->run_plugin_sql(coyote_sql_file('deactivate_plugin.sql'));
        delete_option('coyote_plugin_is_activated');
        $this->is_activated = false;
    }

    public function load() {
        Logger::log("Loading plugins");
    }
}


