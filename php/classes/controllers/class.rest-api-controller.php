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

use Coyote\Logger;
use Coyote\Handlers\ResourceUpdateHandler;

use Exception;
use WP_REST_Server;
use WP_Rest_Request;

class RestApiController {

    private $namespace;
    private $plugin_version;
    private $organization_id;
    private $metum;

    /**
     * RestApiController constructor.
     * @param int $plugin_version
     * @param int $api_version
     * @param int $organization_id
     * @param string $metum
     */
    public function __construct(string $plugin_version, int $api_version, int $organization_id, string $metum = 'Alt') {
        $api_version = intval($api_version ?? 1);

        $this->plugin_version = $plugin_version;
        $this->namespace = "coyote/v{$api_version}";
        $this->organization_id = $organization_id;
        $this->metum = $metum;

        // Appropriate registration hook
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function register_rest_routes(): void {
        register_rest_route(
            $this->namespace,
            'callback',
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'update_resource'),
                'permission_callback' => array($this, 'check_callback_permission')
            )
        );

        register_rest_route(
            $this->namespace,
            '/status',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'provide_status'),
            )
        );
    }

    public function update_resource(WP_Rest_Request $request): bool {
        $body = $request->get_body();
        $json = json_decode($body);

        try {
            $update = self::parse_update($json);
        } catch (Exception $e) {
            Logger::log("Error parsing update: " . $e->get_error_message());
            $update = array();
        }

        if ($update['alt'] === null) {
            Logger::log("Update contained no alt text, setting to empty string");
            $update['alt'] = "";
        }

        return ResourceUpdateHandler::run($update['id'], $update['alt']);
    }

    public function parse_update($json) {
        $alt_representations = array_filter($json->included, function ($item) {
            return $item->type === 'representation' &&
                   $item->attributes->metum === $this->metum;
        });


        $alt = count($alt_representations) ? array_shift($alt_representations)->attributes->text : null;

        return array(
            'id' => $json->data->id,
            'alt' => $alt
        );
    }

    public function check_callback_permission(WP_Rest_Request $request): bool {
        $body = $request->get_body();
        $json = json_decode($body);

        $req_org_id = intval($json->data->relationships->organization->data->id);

        // TODO verify by header token as well

        return $req_org_id === intval($this->organization_id);
    }

    public function provide_status(): string {
        return "Coyote Plugin v{$this->plugin_version} OK";
    }

}
