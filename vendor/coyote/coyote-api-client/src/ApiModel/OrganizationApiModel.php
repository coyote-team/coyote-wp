<?php

namespace Coyote\ApiModel;

use Coyote\ApiModel\Partial\OrganizationAttributes;

class OrganizationApiModel extends AbstractResourceRelatedApiModel
{
    public const TYPE = 'organization';
    public OrganizationAttributes $attributes;
}
