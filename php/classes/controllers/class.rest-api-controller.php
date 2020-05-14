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
use Coyote\ApiClient;
use Coyote\Handlers\ResourceUpdateHandler;

use WP_REST_Server;
use WP_Rest_Request;

class RestApiController {
    /**
     * @var $namespace string API path namespace
     */
    private $namespace;

    private $plugin_version;

    /**
     * Constructor
     *
     * @param string $plugin_version    Local Plugin version
     * @param string $api_version       Remote Coyote API version
     */
    public function __construct($plugin_version, $api_version = 1) {
        $this->plugin_version = $plugin_version;
        $this->namespace = "coyote/v{$api_version}";

        // Appropriate registration hook
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function register_rest_routes() {
        register_rest_route(
            $this->namespace,
            'callback',
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'update_resource_in_posts'),
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

    public function update_resource_in_posts(WP_Rest_Request $request) {
        $body = $request->get_body();
        $json = json_decode($body);

        try {
            $update = self::parse_update($json);
        } catch (Exception $e) {
            Logger::log("Error parsing update: " . $e->get_error_message());
            $update = array();
        }

        if ($update['alt'] === null) {
            Logger::log("Update contained no alt text");
            return true;
        }

        // disable the post update hook
        remove_action('wp_insert_post_data', array('Coyote\Handlers\PostUpdateHandler', 'run'));

        return ResourceUpdateHandler::run($update['id'], $update['alt']);
    }

    public function parse_update($json) {

        $organization = $json->data->relationships->organization->data->id;

        $alt_representations = array_filter($json->included, function ($item) {
            return $item->type === 'representation' &&
                   $item->attributes->metum === 'Alt';
        });


        $alt = count($alt_representations) ? array_shift($alt_representations)->attributes->text : null;

        return array(
            'id' => $json->data->id,
            'alt' => $alt
        );
    }

    public function check_callback_permission(WP_Rest_Request $request) {
        // perform authentication header checks
        // TODO verify API token in header
        // TODO verify organization ID
        return true;
    }

    public function provide_status(WP_Rest_Request $request) {
        return "Coyote Plugin v{$this->plugin_version} OK";
    }

}
