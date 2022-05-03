<?php

namespace Coyote\ApiResponse;

use Coyote\ApiModel\OrganizationApiModel;
use Coyote\ApiModel\Partial\ApiMetaData;
use Coyote\ApiModel\Partial\ResourceLinks;
use Coyote\ApiModel\ResourceApiModel;
use Coyote\ApiModel\ResourceRepresentationApiModel;

class GetResourceApiResponse
{
    public ResourceApiModel $data;

    /** @var array<OrganizationApiModel|ResourceRepresentationApiModel> */
    public array $included;

    public ResourceLinks $links;
    public ApiMetaData $jsonapi;
}
