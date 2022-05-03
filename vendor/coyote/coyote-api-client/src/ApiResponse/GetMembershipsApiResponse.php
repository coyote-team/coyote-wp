<?php

namespace Coyote\ApiResponse;

use Coyote\ApiModel\MembershipApiModel;
use Coyote\ApiModel\Partial\ApiMetaData;

class GetMembershipsApiResponse
{
    /** @var MembershipApiModel[] */
    public array $data;

    public ApiMetaData $jsonapi;
}
