<?php

namespace Coyote;

require_once coyote_plugin_file('classes/class.db.php');
require_once coyote_plugin_file('classes/class.logger.php');
require_once coyote_plugin_file('classes/class.api-client.php');

use Coyote\DB;
use Coyote\Logger;
use Coyote\ApiClient;

class ImageResource {
    private $image;

    public $original_description = '';
    public $coyote_description = null;
    public $coyote_resource_id = null;

    public function __construct(array $image) {
        $this->image = $image;
        $this->process();
    }

    private function process() {
        $hash = sha1($this->image["src"]);
        $record = DB::get_image_by_hash($hash);

        $alt = $this->image["alt"] ? $this->image["alt"] : "";

        $record = $record
            ? $record 
            : $this->createAndInsert($hash, $this->image["src"], $alt)
        ;

        if ($record === null) {
            return;
        }

        $this->coyote_resource_id = $record->coyote_resource_id;
        $this->coyote_description = $record->coyote_description;
        $this->original_description = $record->original_description;
    }

    private function createAndInsert(string $hash, string $src, string $alt) {
        // don't necessarily create a new resource, query by src first

        global $coyote_plugin;
        $client = new ApiClient(
            $coyote_plugin->config["CoyoteApiEndpoint"],
            $coyote_plugin->config["CoyoteApiToken"],
            $coyote_plugin->config["CoyoteOrganizationId"],
            $coyote_plugin->config["CoyoteApiVersion"]
        );

        $existingResource = $client->getResourceBySourceUri($src);

        $resourceId = $existingResource
            ? $existingResource->id
            : $client->createNewResource($src, $alt)
        ;

        if ($resourceId === null) {
            return null;
        }

        $coyoteAlt = $existingResource ? $existingResource->alt : null;

        DB::insert_image($hash, $src, $alt, $resourceId, $coyoteAlt);

        return (object) array(
            "coyote_resource_id" => $resourceId,
            "coyote_description" => $coyoteAlt,
            "original_description" => $alt
        );
    }
}
