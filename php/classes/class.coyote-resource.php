<?php

namespace Coyote;

// Exit if accessed directly.
use Exception;
use stdClass;

if (!defined( 'ABSPATH')) {
    exit;
}

class CoyoteResource {
    public $image;
    private $resource = null;

    public $original_description = '';
    public $coyote_description = null;
    public $coyote_resource_id = null;

    public function __construct(array $image, stdClass $resource = null) {
        $this->image = $image;

        if ($resource !== null) {
            $this->resource = $resource;

            $this->coyote_resource_id = $resource->id;
            $this->coyote_description = $resource->alt;
            $this->original_description = $image['alt'] ? $image['alt'] : '';
        }

        $this->process();
    }

    public static function resources_from_images(array $images) {
        try {
            $api_client = self::get_api_client();

            $created_resources = $api_client->batch_create($images);

            $coyote_resources = array_map(function ($image) use ($created_resources) {
                $resource = array_key_exists($image['src'], $created_resources)
                    ? $created_resources[$image['src']]
                    : null
                ;

                return new CoyoteResource($image, $resource);
            }, $images);

            do_action('coyote_api_client_success');

            return $coyote_resources;
        } catch (Exception $e) {
            do_action('coyote_api_client_error', $e);
            return [];
        }
    }

    public static function get_coyote_id_and_alt($image, $create_if_missing) {
        $hash = sha1($image['src']);

        $record = DB::get_image_by_hash($hash);

        if ($record) {
            return [
                'id'    => $record->coyote_resource_id,
                'alt'   => $record->coyote_description
            ];
        }

        if (!$create_if_missing) {
            return null;
        }

        $resources = self::resources_from_images([$image]);

        if (count($resources) !== 1) {
            return null;
        }

        $resource = array_pop($resources);

        return [
            'id'  => $resource->coyote_resource_id,
            'alt' => $resource->coyote_description
        ];
    }

    public static function get_api_client() {
        global $coyote_plugin;
        $cfg = $coyote_plugin->config;

        return new ApiClient([
            'endpoint' => $cfg["CoyoteApiEndpoint"],
            'token' => $cfg["CoyoteApiToken"],
            'organization_id' => $cfg["CoyoteApiOrganizationId"],
            'api_version' => $cfg["CoyoteApiVersion"],
            'language' => 'en',
            'metum' => $cfg["CoyoteApiMetum"],
            'resource_group_id' => $cfg["CoyoteApiResourceGroupId"]
        ]);
    }

    private function process() {
        $alt = $this->image['alt'] ?: '';
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
                    $this->resource->alt ?: $alt
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
