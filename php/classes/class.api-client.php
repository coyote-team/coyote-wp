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
    private $resource_group_id = null;
    private $language;
    private $guzzle_client;
    private $metum;

    const HTTP_OK = 200;
    const HTTP_CREATED = 201;

    public function __construct($args) {
        if (!isset($args['endpoint']) || !isset($args['token'])) {
            throw new \Exception("Provide at least an endpoint and token!");
        }

        Logger::log($args);

        $this->guzzle_client = new \GuzzleHttp\Client([
            'base_uri' => ($args['endpoint'] . '/api/' . 'v' . ($args['api_version'] ?? 1). '/'),
            'timeout'  => 20.0,
            // disable exceptions, handle http 4xx-5xx internally
            'exceptions' => false,
            'headers' => ['Authorization' => $args['token'], 'Accept' => 'application/json']
        ]);

        $this->organization_id = $args['organization_id'] ?? null;
        $this->language = $args['language'] ?? "en";
        $this->metum = $args['metum'] ?? "Alt";
        $this->resource_group_id = $args['resource_group_id'] ?? null;
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

    public function create_resource_group($name, $url) {
        try {
            $response = $this->guzzle_client->get("organizations/{$this->organization_id}/resource_groups");
            $json = $this->get_response_json(self::HTTP_OK, $response);

            if (!$json) {
                Logger::log("Failed fetching resource groups?");
                return null;
            }

            return $this->ensure_resource_group_exists($json, $name, $url);
        } catch (Exception | Error $error) {
            Logger::log("Error fetching resource groups: " . $error->get_error_message());
            return null;
        }
    }

    private function ensure_resource_group_exists($json, $name, $url) {
        $matches = array_filter($json->data, function ($group) use($url) {
            return $group->attributes->webhook_uri === $url;
        });

        if (count($matches)) {
            $group = array_shift($matches);
            Logger::log("Found existing resource_group {$group->id}");
            return $group->id;
        }

        try {
            $payload = [
                "name" => $name,
                "webhook_uri" => $url
            ];

            $response = $this->guzzle_client->post("organizations/{$this->organization_id}/resource_groups", ['json' => $payload]);
            $json = $this->get_response_json(self::HTTP_CREATED, $response);

            if (!$json) {
                Logger::log("Failed creating resource group?");
                return null;
            }

            Logger::log("Created new webhook: {$json->data->id}");

            return $json->data->id;
        } catch (Exception | Error $error) {
            Logger::log("Error fetching resource groups: " . $error->get_error_message());
            return null;
        }
    }

    public function batch_create(array $images) {
        $language = $this->language;

        $resources = array_map(function ($i) use ($language) {
            $name = !empty($i['caption']) ? $i['caption'] : $i['src'];
            $resource = [
                'name' => $name,
                'source_uri' => $i['src'],
                'resource_type' => 'image',
            ];

            if ($this->resource_group_id) {
                $resource['resource_group_id'] = $this->resource_group_id;
            }

            if (!empty($i['host_uri'])) {
                $resource['host_uris'] = [$i['host_uri']];
            }

            if (isset($i['alt']) && strlen($i['alt'])) {
                $resource['representations'] = [
                    [
                        'text' => $i['alt'],
                        'metum' => $this->metum,
                        'language' => $language,
                        'status' => 'approved'
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
        } catch (Exception | Error $error) {
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
                        $item->attributes->metum === $this->metum
                    ;
                });

                if (count($matches)) {
                    // grab lowest ordinality
                    array_push($carry, $matches[0]);
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
        try {
            $response = $this->guzzle_client->get('profile');
        } catch (\Exception $e) {
            Logger::log("Error fetching profile: {$e->getMessage()}");
            return null;
        }

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
