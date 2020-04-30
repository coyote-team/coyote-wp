<?php

namespace Coyote;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

require_once coyote_plugin_file('classes/class.db.php');
require_once coyote_plugin_file('classes/class.logger.php');
require_once coyote_plugin_file('classes/class.api-client.php');

use Coyote\DB;
use Coyote\Logger;
use Coyote\ApiClient;

class ImageResource {
    public $image;
    private $resource = null;

    public $original_description = '';
    public $coyote_description = null;
    public $coyote_resource_id = null;

    public function __construct(array $image, object $resource = null) {
        $this->image = $image;

        if ($resource !== null) {
            $this->resource = $resource;

            $this->coyote_resource_id = $resource->id;
            $this->coyote_description = $resource->alt;
            $this->original_description = $image['alt'] ? $image['alt'] : '';
        }

        $this->process();
    }

    public static function map(array $images) {
        $sourceUris = array_reduce($images, function($carry, $image) {
            if ($image['data-coyote-id']) {
                return $carry;
            }

            array_push($carry, $image['src']);
            return $carry;
        }, array());

        // empty array
        if (!count($sourceUris)) {
            return $sourceUris;
        }

        $apiClient = self::getApiClient();
        $coyoteResources = $apiClient->getResourcesBySourceUris($sourceUris);

        return array_map(function ($image) use ($coyoteResources) {
            $resource = array_key_exists($image['src'], $coyoteResources)
                ? $coyoteResources[$image['src']]
                : null
            ;

            return new ImageResource($image, $resource);
        }, $images);
    }

    public static function getApiClient() {
        global $coyote_plugin;
        $client = new ApiClient(
            $coyote_plugin->config["CoyoteApiEndpoint"],
            $coyote_plugin->config["CoyoteApiToken"],
            $coyote_plugin->config["CoyoteOrganizationId"],
            $coyote_plugin->config["CoyoteApiVersion"]
        );

        return $client;
    }

    private function process() {
        $alt = $this->image['alt'] ? $this->image['alt'] : '';
        $hash = sha1($this->image['src']);

        // coyote knows this image source uri
        if ($this->resource) {
            // is it in the db? have we processed it before?
            $record = DB::get_image_by_hash($hash);

            if (!$record) {
                // if not, insert it. Otherwise leave alone
                DB::insert_image(
                    $hash,
                    $this->image['src'],
                    $alt,
                    $this->resource->id,
                    $this->resource->alt
                );

                return;
            }

            return;
        }

        // this image is not in coyote, so it won't be in the db either
        // create a new resource and store the ID in the db
        $record = $this->createAndInsert($hash, $this->image['src'], $alt);

        // couldn't create the record
        if ($record === null) {
            return;
        }

        $this->coyote_resource_id = $record->coyote_resource_id;
        $this->coyote_description = $record->coyote_description;
        $this->original_description = $record->original_description;
    }

    private function createAndInsert(string $hash, string $src, string $alt) {
        // if we get here, no resource for this image existed in coyote

        $resourceId = self::getApiClient()->createNewResource($src, $alt);

        // failed. Client configuration error?
        if ($resourceId === null) {
            return null;
        }

        DB::insert_image($hash, $src, $alt, $resourceId, null);

        return (object) array(
            "coyote_resource_id" => $resourceId,
            "coyote_description" => null,
            "original_description" => $alt
        );
    }
}
