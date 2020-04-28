<?php

namespace Coyote;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

require_once coyote_plugin_file('classes/class.logger.php');
require_once coyote_plugin_file('classes/helpers/class.post-process-helper.php');
require_once coyote_plugin_file('classes/handlers/class.post-update-handler.php');
require_once coyote_plugin_file('classes/controllers/class.rest-api-controller.php');
require_once coyote_plugin_file('classes/controllers/class.settings-controller.php');

use Coyote\Logger;
use Coyote\Handlers\PostUpdateHandler;
use Coyote\Helpers\PostProcessHelper;
use Coyote\Controllers\RestApiController;
use Coyote\Controllers\SettingsController;

class Plugin {
    private $is_activated = false;

    private $file;
    private $version;

    public $config = [
        'CoyoteApiVersion' => "1",
        'CoyoteApiToken' => null,
        'CoyoteApiEndpoint' => "",
        'CoyoteOrganizationId' => null
    ];

    public function __construct(string $file, string $version, bool $is_admin = false) {
        if(get_option('coyote_plugin_is_activated', null) !== null) {
            $this->is_activated = true;
        }

        $this->file = $file;
        $this->version = $version;

        if ($is_admin) {
            $_settings = new SettingsController($this->version);
        }

        $this->setup();
    }

    private function load_config() {
        $_config = $this->config;

        $_config['CoyoteApiVersion']     = get_option('coyote__api_settings_version', $_config['CoyoteApiVersion']);
        $_config['CoyoteApiToken']       = get_option('coyote__api_settings_token', $_config['CoyoteApiToken']);
        $_config['CoyoteApiEndpoint']    = get_option('coyote__api_settings_endpoint', $_config['CoyoteApiEndpoint']);
        $_config['CoyoteOrganizationId'] = get_option('coyote__api_settings_organization_id', $_config['CoyoteOrganizationId']);

        $this->config = $_config;
    }

    private function setup() {
        $this->load_config();

        $controller = new RestApiController($this->version);

        // $wpdb becomes available here
        global $wpdb;
        define('COYOTE_IMAGE_TABLE_NAME', $wpdb->prefix . 'coyote_image_resource');
        define('COYOTE_JOIN_TABLE_NAME', $wpdb->prefix . 'coyote_resource_post_jt');

        register_activation_hook($this->file, array($this, 'activate'));
        register_deactivation_hook($this->file, array($this, 'deactivate'));

        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        add_filter('wp_insert_post_data', array('Coyote\Handlers\PostUpdateHandler', 'run'), 10, 2);
    }

    public function enqueue_scripts() {
        $requirements = array(
            'wp-blocks',
            'wp-components',
            'wp-compose',
            'wp-dom-ready',
            'wp-editor',
            'wp-element',
            'wp-hooks'
        );

        wp_enqueue_script('coyote_editor_javascript', '/wp-content/plugins/coyote/asset/editor.js', $requirements);
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
        $posts = get_posts(array(
            'numberposts' => -1, //all
            'post_type' => array('post', 'page')
        ));

        foreach ($posts as $post) {
            // simulate a post update
            Logger::log("Processing post {$post->ID}");
            PostProcessHelper::processExistingPost($post);
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

        $this->is_activated = true;
        add_option('coyote_plugin_is_activated', $this->is_activated);
    }

    public function deactivate() {
        Logger::log("Deactivating plugin");
        $this->run_plugin_sql(coyote_sql_file('deactivate_plugin.sql'));
        delete_option('coyote_plugin_is_activated');
        $this->is_activated = false;
    }
}

