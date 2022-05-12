<?php

/**
 * Update a specific resource's alt text
 * @category class
 * @package Coyote\Handlers\ResourceUpdateHandler
 * @since 1.0
 */

namespace Coyote\Handlers;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use Coyote\Logger;
use Coyote\DB;

class ResourceUpdateHandler {
    public static function run(string $id, string $alt): bool
    {
        Logger::log("Updating: [id] {$id}, [alt] {$alt}");

        $update = DB::updateResourceAlt($id, $alt);

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

        return false;
    }
}

