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

use Coyote\CoyoteApiClient;
use Coyote\Logger;
use Coyote\Handlers\ResourceUpdateHandler;

use Coyote\PluginConfiguration;
use Coyote\WordPressCoyoteApiClient;
use WP_REST_Server;
use WP_Rest_Request;

class RestApiController {

    private string $namespace;
    private int $pluginVersion;
    private int $organizationId;

    /**
     * RestApiController constructor.
     * @param int $pluginVersion
     * @param int $apiVersion
     * @param string $organizationId
     */
    public function __construct(string $pluginVersion, int $apiVersion, string $organizationId) {
        $apiVersion = $apiVersion ?? 1;

        $this->pluginVersion = $pluginVersion;
        $this->namespace = "coyote/v{$apiVersion}";
        $this->organizationId = $organizationId;

        // Appropriate registration hook
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function registerRestRoutes(): void {
        register_rest_route(
            $this->namespace,
            'callback',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'updateResource'],
                'permission_callback' => [$this, 'checkCallbackPermission']
            ]
        );

        register_rest_route(
            $this->namespace,
            'status',
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'provideStatus'],
                'permission_callback' => function() { return true; }
            ]
        );
    }

    public function updateResource(WP_Rest_Request $request): bool {
        // this counts as API success, so potentially recover from
        // error-based standalone mode
        WordPressCoyoteApiClient::registerApiSuccess();

        $body = $request->get_body();
        $json = json_decode($body);

        if (is_null($json)) {
            Logger::log('Received empty or invalid JSON during updateResource.');
            return false;
        }

        $resource = CoyoteApiClient::parseWebHookResourceUpdate($json);

        if (is_null($resource)) {
            Logger::log('Unable to map Update to ResourceModel');
            return false;
        }

        $representation = $resource->getTopRepresentationByMetum(PluginConfiguration::METUM);

        $alt = '';

        if (is_null($representation)) {
            Logger::log("Update contained no Alt metum representation, defaulting to empty string");
        } else {
            $alt = $representation->getText();
        }

        return ResourceUpdateHandler::run($resource->getId(), $alt);
    }

    public function checkCallbackPermission(WP_Rest_Request $request): bool {
        $body = $request->get_body();
        $json = json_decode($body);

        if (is_null($json)) {
            Logger::log('Received empty or invalid JSON in payload .');
            return false;
        }

        $resource = CoyoteApiClient::parseWebHookResourceUpdate($json);

        if (is_null($resource)) {
            Logger::log('Unable to map payload to ResourceModel');
            return false;
        }

        // TODO verify by header token as well

        return $resource->getOrganization()->getId() === $this->organizationId;
    }

    public function provideStatus(): string {
        return "Coyote Plugin v{$this->pluginVersion} OK";
    }

}
