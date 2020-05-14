<?php

/**
 * Coyote API Client
 * @author Job van Achterberg
 * @category class
 * @package Coyote\ApiClient
 * @since 1.0
 */

namespace Coyote;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use \GuzzleHttp\Client;

class ApiClient {
    /**
     * CoyoteApiCLient constructor
     * 
     * @param string endpoint the endpoint the API is located at
     * @param string authKey API authentication key
     * @param int organizationId API organization Id
     * @param int apiVersion API version to use
     */

    private $organization_id = null;
    private $guzzle_client;

    const HTTP_OK = 200;
    const HTTP_CREATED = 201;

    public function __construct(string $endpoint, string $token, string $organization_id = null, string $api_version = "1") {
        $this->guzzle_client = new \GuzzleHttp\Client([
            'base_uri' => ($endpoint . '/api/' . 'v' . $api_version . '/'),
            'timeout'  => 20.0,
            // disable exceptions, handle http 4xx-5xx internally
            'exceptions' => false,
            'headers' => ['Authorization' => $token]
        ]);

        $this->organization_id = $organization_id;
    }

    private function get_response_json($expected_code, $response) {
        if ($response->getStatusCode() === $expected_code) {
            $json = json_decode($response->getBody());

            if ($json !== null) {
                return $json;
            }
        }

        return null;
    }

    public function create_new_resource(string $source_uri, string $alt) {
        $resource = array(
            "source_uri"    => $source_uri,
            "resource_type" => "still_image"
        );

        $response = $this->guzzle_client->post("organizations/{$this->organization_id}/resources", array('json' => $resource));

        if ($json = $this->get_response_json(self::HTTP_CREATED, $response)) {
            return $json->data->id;
        }

        return null;
    }

    public function get_resource_by_id(int $resource_id) {
        $response = $this->guzzle_client->get('resources/' . $resource_id);
        return $this->get_response_json(self::HTTP_OK, $response);
    }

    public function get_resources_by_source_uris(array $source_uris) {
        $uris = join(" ", array_unique($source_uris));
        $query = array(
            'filter' => array('source_uri_eq_any' => $uris)
        );

        $response = $this->guzzle_client->post("organizations/{$this->organization_id}/resources/get", array('json' => $query));
        $json = $this->get_response_json(self::HTTP_OK, $response);

        if (!$json) {
            return array();
        }

        return $this->json_to_id_and_alt($json);
    }

    private function json_to_id_and_alt($json) {
        $map_representations = function($representations, $included) {
            return array_reduce($representations, function ($carry, $representation) use ($included) {
                if ($representation->type !== "representation") {
                    return $carry;
                }

                $matches = array_filter($included, function($item) use ($representation) {
                    return
                        $item->id === $representation->id &&
                        $item->type === "representation" &&
                        $item->attributes->metum === "Alt" &&
                        $item->attributes->status === "approved"
                    ;
                });

                if (count($matches) === 1) {
                    array_push($carry, array_shift($matches));
                }

                return $carry;
            }, array());
        };

        $list = array();

        foreach ($json->data as $item) {
            if ($item->relationships->organization->data->id != $this->organizationId) {
                continue;
            }

            $alt_representations = $map_representations($item->relationships->representations->data, $json->included);
            $alt = count($alt_representations) ? $alt_representations[0]->attributes->text : null;
            $uri = $item->attributes->source_uri;

            $list[$uri] = (object) array(
                'id' => $item->id,
                'alt' => $alt,
                'source_uri' => $uri
            );
        }

        return $list;
    }

    public function get_profile() {
        $response = $this->guzzle_client->get('profile');
        $json = $this->get_response_json(self::HTTP_OK, $response);

        if (!$json) {
            return null;
        }

        return (object) array(
            "id" => $json->data->id,
            "name" => $this->get_profile_name($json),
            "organizations" => $this->get_profile_organizations($json)
        );
    }

    private function get_profile_name($json) {
        $data = $json->data;
        return
            $data->attributes->first_name .
            " " .
            $data->attributes->last_name;
    }

    private function get_profile_organizations($json) {
        $reducer = function($carry, $item) {
            if ($item->type === "organization") {
                array_push($carry, array(
                    "id" => $item->id,
                    "name" => $item->attributes->title
                ));
            }

            return $carry;
        };

        return array_reduce($json->included, $reducer, array());
    }

}
