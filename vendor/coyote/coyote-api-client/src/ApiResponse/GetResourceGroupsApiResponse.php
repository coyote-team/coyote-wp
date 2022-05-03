<?php

namespace Coyote\ApiResponse;

use Coyote\ApiModel\Partial\ApiMetaData;
use Coyote\ApiModel\Partial\ResourceLinks;
use Coyote\ApiModel\ResourceGroupApiModel;

class GetResourceGroupsApiResponse
{
    /** @var ResourceGroupApiModel[]  */
    public array $data;

    public ResourceLinks $links;
    public ApiMetaData $jsonapi;
}
