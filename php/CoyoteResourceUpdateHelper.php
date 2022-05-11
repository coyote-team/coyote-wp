<?php

namespace Coyote;

use Coyote\ApiModel\ResourceApiModel;
use Coyote\ApiResponse\CreateResourceApiResponse;
use Coyote\Model\ResourceModel;
use JsonMapper\JsonMapperFactory;
use stdClass;

class CoyoteResourceUpdateHelper
{
    // RestApiController shouldn't be all that aware of how the Coyote API constructs its updates.
    // This helper deals with that parsing logic.
    public static function getResourceModelFromUpdate(stdClass $json): ?ResourceModel
    {
        // use ApiClient::parseResourceUpdatePayload -> ?ResourceModel
        return null;
    }
}