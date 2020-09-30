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

use Exception;
use \GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use stdClass;

class ApiClient {
    private $organization_id = null;
    private $resource_group_id = null;
    private $language;
    private $guzzle_client;
    private $metum;

    const HTTP_OK = 200;
    const HTTP_CREATED = 201;

    /**
     * ApiClient constructor.
     *
     * $args parameter consists of:
     *  - string endpoint, the API endpoint
     *  - string token, the API usage token
     *  - int api_version, the api version to use
     *  - (optional) int organization_id, the organization ID to use
     *  - (optional) int resource_group_id, the resource group ID under which to create resources
     *  - (optional) string language, the API language, defaults to "en"
     *  - (optional) string metum, the metum to use for representations, defaults to "Alt"
     *
     * @param $args
     * @throws Exception
     */
    public function __construct($args) {
        if (!isset($args['endpoint']) || !isset($args['token'])) {
            throw new Exception("Provide at least an endpoint and token!");
        }

        $this->guzzle_client = new Client([
            'base_uri' => ($args['endpoint'] . '/api/' . 'v' . (intval($args['api_version'] ?? 1)). '/'),
            'timeout'  => 20.0,
            'headers' => ['Authorization' => $args['token'], 'Accept' => 'application/json'],

            // disable exceptions, handle http 4xx-5xx internally
            'exceptions' => false
        ]);

        $this->organization_id = intval($args['organization_id'] ?? null);
        $this->resource_group_id = intval($args['resource_group_id'] ?? null);

        $this->language = $args['language'] ?? "en";
        $this->metum = $args['metum'] ?? "Alt";
    }

    private function get_response_json(int $expected_code, ResponseInterface $response): ?stdClass {
        if ($response->getStatusCode() === $expected_code) {
            $json = json_decode($response->getBody());

            if ($json !== null) {
                return $json;
            }
        }

        return null;
    }

    public function create_resource_group(string $name, string $url): int {
        $response = $this->guzzle_client->get("organizations/{$this->organization_id}/resource_groups");
        $json = $this->get_response_json(self::HTTP_OK, $response);

        if (!$json) {
            throw new Exception("Failed fetching resource groups");
        }

        return $this->ensure_resource_group_exists($json, $name, $url);
    }

    private function ensure_resource_group_exists(stdClass $json, string $name, string $url): int {
        $matches = array_filter($json->data, function ($group) use($url) {
            return $group->attributes->webhook_uri === $url;
        });

        if (count($matches)) {
            $group = array_shift($matches);
            error_log("Found existing resource_group {$group->id}");
            return $group->id;
        }

        $payload = [
            "name" => $name,
            "webhook_uri" => $url
        ];

        $response = $this->guzzle_client->post("organizations/{$this->organization_id}/resource_groups", ['json' => $payload]);
        $json = $this->get_response_json(self::HTTP_CREATED, $response);

        if (!$json) {
            throw new Exception("Failed to create new resource group");
        }

        error_log("Created new resource group: {$json->data->id}");

        return $json->data->id;
    }

    public function batch_create(array $images): array {
        if (!count($images)) {
            return [];
        }

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

        $response = $this->guzzle_client->post("organizations/{$this->organization_id}/resources/create", ['json' => ['resources' => $resources]]);
        $json = $this->get_response_json(self::HTTP_CREATED, $response);

        if (!$json) {
            throw new Exception('Unexpected response when creating resources');
        }

        return $this->json_to_id_and_alt($json);
    }

    private function json_to_id_and_alt(stdClass $json) {
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
                    array_push($carry, array_shift($matches));
                }

                return $carry;
            }, []);
        };

        $list = [];

        foreach ($json->data as $item) {
            $alt_representations = [];

            if (property_exists($item->relationships->representations, 'data')) {
                $alt_representations = $map_representations($item->relationships->representations->data, $json->included);
            }

            $alt = count($alt_representations) ? $alt_representations[0]->attributes->text : null;
            $uri = $item->attributes->source_uri;

            error_log("New resource {$item->id}: {$uri} => \"{$alt}\"");

            $list[$uri] = (object) array(
                'id' => $item->id,
                'alt' => $alt,
                'source_uri' => $uri
            );
        }

        return $list;
    }

    public function get_profile(): stdClass {
        $response = $this->guzzle_client->get('profile');

        $json = $this->get_response_json(self::HTTP_OK, $response);

        if (!$json) {
            throw new Exception('Unexpected response when loading profile');
        }

        return (object) array(
            "id" => $json->data->id,
            "name" => $this->get_profile_name($json),
            "organizations" => $this->get_profile_organizations($json)
        );
    }

    private function get_profile_name(stdClass $json): string {
        $data = $json->data;
        return
            $data->attributes->first_name .
            " " .
            $data->attributes->last_name;
    }

    private function get_profile_organizations(stdClass $json): array {
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
