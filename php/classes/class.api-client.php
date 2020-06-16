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
     * @param string auth_key API authentication key
     * @param int organization_id API organization Id
     * @param int api_version API version to use
     */

    private $organization_id = null;
    private $language;
    private $guzzle_client;

    const HTTP_OK = 200;
    const HTTP_CREATED = 201;

    public function __construct(string $endpoint, string $token, string $organization_id = null, string $api_version = '1', $language = 'en') {
        $this->guzzle_client = new \GuzzleHttp\Client([
            'base_uri' => ($endpoint . '/api/' . 'v' . $api_version . '/'),
            'timeout'  => 20.0,
            // disable exceptions, handle http 4xx-5xx internally
            'exceptions' => false,
            'headers' => ['Authorization' => $token]
        ]);

        $this->organization_id = $organization_id;
        $this->language = $language;
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

    public function batch_create(array $images) {
        $language = $this->language;

        $resources = array_map(function ($i) use ($language) {
            $name = isset($i['caption']) ? $i['caption'] : $i['src'];
            $resource = [
                'name' => $name,
                'source_uri' => $i['src'],
                'resource_type' => 'still_image',
            ];

            if (isset($i['alt']) && strlen($i['alt'])) {
                $resource['representations'] = [
                    [
                        'text' => $i['alt'],
                        'metum' => 'Alt',
                        'language' => $language
                    ]
                ];
            }

            return $resource;
        }, $images);

        try {
            $response = $this->guzzle_client->post("organizations/{$this->organization_id}/resources/create", ['json' => ['resources' => $resources]]);
            $json = $this->get_response_json(self::HTTP_CREATED, $response);

            if (!$json) {
                return [];
            }

            return $this->json_to_id_and_alt($json);
        } catch (Exception $error) {
            Logger::log("Error batch creating resources: " . $error->get_error_message());
            return array();
        }
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
            }, []);
        };

        $list = [];

        foreach ($json->data as $item) {
            $alt_representations = [];
            if ($item->relationships->representations->meta->included) {
                $alt_representations = $map_representations($item->relationships->representations->data, $json->included);
            }

            $alt = count($alt_representations) ? $alt_representations[0]->attributes->text : null;
            $uri = $item->attributes->source_uri;

            Logger::log("New resource {$item->id}: {$uri} => \"{$alt}\"");

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
                    "name" => $item->attributes->name
                ));
            }

            return $carry;
        };

        return array_reduce($json->included, $reducer, array());
    }

}
