<?php

namespace Coyote\ApiResponse;

use Coyote\ApiModel\OrganizationApiModel;
use Coyote\ApiModel\Partial\ApiMetaData;
use Coyote\ApiModel\Partial\ResourceLinks;
use Coyote\ApiModel\ResourceApiModel;
use Coyote\ApiModel\ResourceRepresentationApiModel;

class GetResourcesApiResponse
{
    /** @var ResourceApiModel[] */
    public array $data;

    /** @var OrganizationApiModel[]|ResourceRepresentationApiModel[] */
    public array $included;

    public ResourceLinks $links;
    public ApiMetaData $jsonapi;
}
