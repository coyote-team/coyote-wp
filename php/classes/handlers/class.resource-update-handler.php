<?php

namespace Coyote\Handlers;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use Coyote\Logger;
use Coyote\DB;
use Coyote\Helpers\ContentHelper;

class ResourceUpdateHandler {
    public static function run($id, $alt) {
        Logger::log("Updating: [id] {$id}, [alt] {$alt}");

        $update = DB::update_resource_alt($id, $alt);

        if ($update === false) {
            // db error
            Logger::log("Resource alt update error");
            return false;
        }

        if ($update === 0) {
            Logger::log("No resources to update?");
            // no updates? That's ok, but leave posts alone
            return true;
        }
    }
}

