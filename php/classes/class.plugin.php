<?php

namespace Coyote;

require_once coyote_plugin_file('classes/handlers/class.post-update-handler.php');
require_once coyote_plugin_file('classes/controllers/class.rest-api-controller.php');

use Coyote\Handlers\PostUpdateHandler;
use Coyote\Controllers\RestApiController;

class Plugin {
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
        $this->file = $file;
        $this->version = $version;

        $this->post_update_handler = new Handlers\PostUpdateHandler();

        $this->setup();
    }

    private function setup() {
        $controller = new RestApiController($this->version);

        register_activation_hook($this->file, array($this, 'activate'));
        register_deactivation_hook($this->file, array($this, 'deactivate'));

        add_action('plugins_loaded', array($this, 'load'));
        add_action('save_post', array($this->post_update_handler, 'run'), 10, 3);
    }

    public function activate() {
        // run sql
        // sweep posts
    }

    public function deactivate() {
        // run sql
    }

    public function load() {
        // $wpdb becomes available here
        global $wpdb;
        DEFINE('COYOTE_IMAGE_TABLE_NAME', $wpdb->prefix . '_coyote_image_resource');
        DEFINE('COYOTE_JOIN_TABLE_NAME', $wpdb->prefix . '_coyote_resource_post_jt');
    }
}


