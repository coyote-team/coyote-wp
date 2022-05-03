<?php

namespace Coyote\ApiModel;

use Coyote\ApiModel\Partial\MembershipAttributes;

class MembershipApiModel extends AbstractResourceRelatedApiModel
{
    public const TYPE = 'membership';
    public MembershipAttributes $attributes;
}
