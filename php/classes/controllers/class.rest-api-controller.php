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

require_once coyote_plugin_file('classes/class.logger.php');
require_once coyote_plugin_file('classes/class.api-client.php');
require_once coyote_plugin_file('classes/handlers/class.resource-update-handler.php');

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
        $body = $request->get_body();
        $json = json_decode($body);

        Logger::log($json);

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

    public function checkCallbackPermission(WP_Rest_Request $request) {
        // perform authentication header checks
        // TODO verify API token in header
        // TODO verify organization ID
        return true;
    }

    public function provideStatus(WP_Rest_Request $request) {
        return "Coyote Plugin v{$this->pluginVersion} OK";
    }

}
