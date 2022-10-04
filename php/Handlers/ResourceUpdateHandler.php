<?php

/**
 * Update a specific resource's alt text
 * @category class
 * @package Coyote\Handlers\ResourceUpdateHandler
 * @since 1.0
 */

namespace Coyote\Handlers;

if (!defined('WP_INC')) {
    exit;
}

use Coyote\Traits\Logger;
use Coyote\DB;

class ResourceUpdateHandler
{
    use Logger;

    public static function run(string $id, string $alt): bool
    {
        self::logDebug("Processing resource update", ['id' => $id, 'alt' => $alt]);

        $update = DB::updateResourceAlt($id, $alt);

        if ($update === false) {
            // db error
            self::logDebug("Resource metum update error");
            return false;
        }

        if ($update === 0) {
            self::logDebug("No resources to update?");
            // no updates? That's ok, but leave posts alone
            return true;
        }

        return false;
    }
}
