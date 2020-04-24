<?php

/**
 * Coyote API Client Class
 * @author Job van Achterberg
 * @category class
 * @package CoyotePlugin/Clients
 * @since 1.0
 */

namespace Coyote;

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

    private $organizationId;
    private $httpClient;

    public function __construct(string $endpoint, string $token, int $organizationId, int $apiVersion = 1) {
        $this->httpClient = new \GuzzleHttp\Client([
            'base_uri' => ($endpoint . '/api/' . 'v' . $apiVersion . '/'),
            'timeout'  => 2.0,
            'headers' => ['Authorization' => $token]
        ]);

        $this->organizationId = $organizationId;
    }

    public function createNewResource(string $source_uri, string $alt) {
        $resource = [
            "title" => ($alt ? $alt : ""),
            "source_uri" => $source_uri,
            "resource_type" => "still_image"
        ];

        $response = $this->httpClient->post('/resource', ['json' => $resource]);
        $json = $response->json();
        return $json["data"]["id"];
    }

    public function getResourceById(int $resourceId) {
        $response = $this->httpClient->get('resources/' . $resourceId);
        $json = $response->json();
    }

    public function getResourceBySourceUri(string $sourceUri) {
        $query = [
            "filter" => ["source_uri_eq_any" => $sourceUri]
        ];
        $response = $this->httpClient->post("organizations/{$this->organizationId}/resources/get", ['json' => $query]);
        $json = json_decode($response->getBody());
        $records = $json->data;

        if (count($records) !== 1) {
            return false;
        }

        $record = $records[0];
        $id = $record->id;

        $representations = $record->relationships->representations->data;

        if (count($representations) !== 1) {
            return false;
        }

        $alt_representations = array_filter($json->included, function($item) {
            return $item->type == "representation" && $item->attributes->metum == "Alt";
        });

        if (count($alt_representations) !== 1) {
            return false;
        }

        return [
            "id" => $id,
            "alt" => array_pop($alt_representations)->attributes->text
        ];

    }
}
