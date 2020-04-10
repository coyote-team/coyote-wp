<?php

use WP_REST_Server;

/**
 * Afford custom Coyote callback endpoint on WP REST API
 *
 * @package Coyote Plugin
 * @since 0.1
 */

namespace Coyote\Controllers;

class RestApiController {
    /**
     * @var $namespace string API path namespace
     */
    private $namespace;

    /**
     * Constructor
     *
     * @param string $version Plugin version
     */
    public function __construct($version = 1) {
        $this->namespace = 'coyote/v' . $version;

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
    }

    public function updateResourceInPosts(WP_Rest_Request $request) {
        // $resource = new CoyoteApiResource($resourceId, $resourceDescription);
        // $coyotePluginModel->updateResource($resource);
    }

    public function checkCallbackPermission(WP_Rest_Request $request) {
        // perform authentication header checks
    }
}