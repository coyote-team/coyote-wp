<?php

/**
 * Coyote API Client Class
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

    private $organizationId;
    private $httpClient;

    const HTTP_OK = 200;
    const HTTP_CREATED = 201;

    public function __construct(string $endpoint, string $token, string $organizationId, string $apiVersion) {
        $this->httpClient = new \GuzzleHttp\Client([
            'base_uri' => ($endpoint . '/api/' . 'v' . $apiVersion . '/'),
            'timeout'  => 2.0,
            'headers' => ['Authorization' => $token]
        ]);

        $this->organizationId = $organizationId;
    }

    private function getResponseJson($expected_code, $response) {
        if ($response->getStatusCode() === $expected_code) {
            $json = json_decode($response->getBody());

            if ($json !== null) {
                return $json;
            }
        }

        return null;
    }

    public function createNewResource(string $source_uri, string $alt) {
        $resource = array(
            "title"         => ($alt ? $alt : "Image Title"),
            "source_uri"    => $source_uri,
            "resource_type" => "still_image"
        );

        $response = $this->httpClient->post("organizations/{$this->organizationId}/resources", array('json' => $resource));

        if ($json = $this->getResponseJson(self::HTTP_CREATED, $response)) {
            return $json->data->id;
        }

        return null;
    }

    public function getResourceById(int $resourceId) {
        $response = $this->httpClient->get('resources/' . $resourceId);
        return $this->getResponseJson(self::HTTP_OK, $response);
    }

    public function getResourceBySourceUri(string $sourceUri) {
        $query = array(
            "filter" => array("source_uri_eq_any" => $sourceUri)
        );

        $isAltRepresentation = function($item) {
            return $item->type === "representation" && $item->attributes->metum === "Alt";
        };

        $response = $this->httpClient->post("organizations/{$this->organizationId}/resources/get", array('json' => $query));

        if ($json = $this->getResponseJson(self::HTTP_OK, $response)) {
            $records = $json->data;

            if (count($records) !== 1) {
                return null;
            }

            $record = $records[0];
            $id = $record->id;

            $representations = $record->relationships->representations->data;

            $altRepresentations = array_filter($json->included, $isAltRepresentation);

            if (count($altRepresentations) === 0) {
                return null;
            }

            return (object) array(
                "id" => $id,
                "alt" => array_pop($altRepresentations)->attributes->text
            );
        }

        return null;
    }

}
