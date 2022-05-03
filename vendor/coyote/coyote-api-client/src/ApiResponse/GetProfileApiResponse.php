<?php

namespace Coyote\ApiResponse;

use Coyote\ApiModel\Partial\ApiMetaData;
use Coyote\ApiModel\ProfileApiModel;
use stdClass;

class GetProfileApiResponse
{
    public ProfileApiModel $data;

    /** @var stdClass[] */
    public array $included;

    public ApiMetaData $jsonapi;
}
