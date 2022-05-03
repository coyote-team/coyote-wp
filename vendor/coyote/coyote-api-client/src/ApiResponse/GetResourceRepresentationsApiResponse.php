<?php

namespace Coyote\ApiResponse;

use Coyote\ApiModel\Partial\ApiMetaData;
use Coyote\ApiModel\Partial\ResourceLinks;
use Coyote\ApiModel\ResourceRepresentationApiModel;

class GetResourceRepresentationsApiResponse
{
    /** @var ResourceRepresentationApiModel[]  */
    public array $data;

    public ResourceLinks $links;
    public ApiMetaData $jsonapi;
}
