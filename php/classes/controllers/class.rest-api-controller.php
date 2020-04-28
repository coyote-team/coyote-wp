<?php


/**
 * Afford custom Coyote callback endpoint on WP REST API
 *
 * @package Coyote Plugin
 * @since 0.1
 */

namespace Coyote\Controllers;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use WP_REST_Server;
use WP_Rest_Request;

class RestApiController {
    /**
     * @var $namespace string API path namespace
     */
    private $namespace;

    private $pluginVersion;

    /**
     * Constructor
     *
     * @param string $version Plugin version
     */
    public function __construct($pluginVersion, $apiVersion = 1) {
        $this->pluginVersion = $pluginVersion;
        $this->namespace = "coyote/v{$apiVersion}";

        // Appropriate registration hook
        add_action('rest_api_init', array($this, 'registerRestRoutes'));
    }

    public function registerRestRoutes() {
        register_rest_route(
            $this->namespace,
            'callback',
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'updateResourceInPosts'),
                'permission_callback' => array($this, 'checkCallbackPermission')
            )
        );

        register_rest_route(
            $this->namespace,
            '/status',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'provideStatus'),
            )
        );

    }

    public function updateResourceInPosts(WP_Rest_Request $request) {
        // $resource = new CoyoteApiResource($resourceId, $resourceDescription);
        // $coyotePluginModel->updateResource($resource);
    }

    public function checkCallbackPermission(WP_Rest_Request $request) {
        // perform authentication header checks
    }

    public function provideStatus(WP_Rest_Request $request) {
        return "Coyote Plugin v{$this->pluginVersion} OK";
    }

}
