<?php

namespace Coyote\ApiResponse;

use Coyote\ApiModel\Partial\ApiMetaData;
use Coyote\ApiModel\ResourceApiModel;
use Coyote\ApiModel\ResourceRepresentationApiModel;

class CreateResourcesApiResponse
{
    /** @var ResourceApiModel[] */
    public array $data;

    /** @var ResourceRepresentationApiModel[]|null */
    public ?array $included;

    public ApiMetaData $jsonapi;
}
