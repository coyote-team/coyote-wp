<?php

namespace Coyote\ApiResponse;

use Coyote\ApiModel\Partial\ApiMetaData;
use Coyote\ApiModel\Partial\ResourceLinks;
use Coyote\ApiModel\ResourceGroupApiModel;

class CreateResourceGroupApiResponse
{
    public ResourceGroupApiModel $data;
    public ResourceLinks $links;
    public ApiMetaData $jsonapi;
}
