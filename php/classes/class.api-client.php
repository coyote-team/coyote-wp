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

    private $organizationId = null;
    private $httpClient;

    const HTTP_OK = 200;
    const HTTP_CREATED = 201;

    public function __construct(string $endpoint, string $token, string $organizationId = null, string $apiVersion = "1") {
        $this->httpClient = new \GuzzleHttp\Client([
            'base_uri' => ($endpoint . '/api/' . 'v' . $apiVersion . '/'),
            'timeout'  => 20.0,
            // disable exceptions, handle http 4xx-5xx internally
            'exceptions' => false,
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

    public function getResourcesBySourceUris(array $sourceUris) {
        $uris = join(" ", array_unique($sourceUris));
        $query = array(
            'filter' => array('source_uri_eq_any' => $uris)
        );

        $response = $this->httpClient->post("organizations/{$this->organizationId}/resources/get", array('json' => $query));
        $json = $this->getResponseJson(self::HTTP_OK, $response);

        if (!$json) {
            return array();
        }

        return $this->jsonToIdAndAlt($json);
    }

    private function jsonToIdAndAlt($json) {
        $mapRepresentations = function($representations, $included) {
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

            $altRepresentations = $mapRepresentations($item->relationships->representations->data, $json->included);
            $alt = count($altRepresentations) ? $altRepresentations[0]->attributes->text : null;
            $uri = $item->attributes->source_uri;

            $list[$uri] = (object) array(
                'id' => $item->id,
                'alt' => $alt,
                'source_uri' => $uri
            );
        }

        return $list;
    }

    public function getProfile() {
        $response = $this->httpClient->get('profile');
        $json = $this->getResponseJson(self::HTTP_OK, $response);

        if (!$json) {
            return null;
        }

        return (object) array(
            "id" => $json->data->id,
            "name" => $this->getProfileName($json),
            "organizations" => $this->getProfileOrganizations($json)
        );
    }

    private function getProfileName($json) {
        $data = $json->data;
        return
            $data->attributes->first_name .
            " " .
            $data->attributes->last_name;
    }

    private function getProfileOrganizations($json) {
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
