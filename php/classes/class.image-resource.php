<?php

namespace Coyote;

require_once coyote_plugin_file('classes/class.db.php');
require_once coyote_plugin_file('classes/class.api-client.php');

use Coyote\DB;
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
        $record = $this->fetchFromDB($hash);

        $record = $record
            ? $record 
            : $this->createAndInsert($hash, $image["src"], $image["alt"])
        ;

        $this->coyote_resource_id = $record["coyote_resource_id"]; 
        $this->coyote_description = $record["coyote_description"]; 
        $this->original_description = $record["original_description"];
    }

    private function fetchFromDb(string $hash) {
        return DB::get_image_by_hash($hash);
    }

    private function createAndInsert(string $hash, string $src, string $alt) {
        // don't necessarily create a new resource, query by src first

        global $coyote_plugin;
        $client = new ApiClient(
            $coyote_plugin->config["CoyoteApiEndpoint"],
            $coyote_plugin->config["CoyoteApiToken"],
            $coyote_plugin->config["CoyoteOrganizationId"]
        );

        $resourceId = $client->createNewResource($src, $alt);
        DB::insert_image($hash, $src, $alt, $resourceId);

        return [
            "coyote_resource_id" => $resourceId,
            "coyote_description" => null,
            "original_description" => $alt
        ];
    }
}
