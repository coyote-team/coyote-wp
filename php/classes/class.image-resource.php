<?php

namespace Coyote;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use Coyote\DB;
use Coyote\Logger;
use Coyote\ApiClient;

class ImageResource {
    public $image;
    private $resource = null;

    public $original_description = '';
    public $coyote_description = null;
    public $coyote_resource_id = null;

    private $api_client;

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
        $images = array_filter($images, function ($image) {
            return $image['coyote_id'] === null;
        });

        $api_client = self::get_api_client();
        $coyote_resources = $api_client->batch_create($images);

        return array_map(function ($image) use ($coyote_resources) {
            $resource = array_key_exists($image['src'], $coyote_resources)
                ? $coyote_resources[$image['src']]
                : null
            ;

            return new ImageResource($image, $resource);
        }, $images);
    }

    public static function get_api_client() {
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
        } else {
            // in case of multiple identical images per post, this can
            // already exist in the database
            $record = DB::get_image_by_hash($hash);

            if ($record === null) {
                Logger::log("No resource could be created for \"" . $this->image['src'] . "\"");
                return;
            }
        }

        $this->coyote_resource_id = $record->coyote_resource_id;
        $this->coyote_description = $record->coyote_description;
        $this->original_description = $record->original_description;
    }
}
