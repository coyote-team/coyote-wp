<?php

namespace Coyote\ApiModel;

use Coyote\ApiModel\Partial\ResourceGroupAttributes;
use Coyote\ApiModel\Partial\ResourceGroupRelationships;

class ResourceGroupApiModel
{
    public string $id;
    public string $type;
    public ResourceGroupAttributes $attributes;
    public ResourceGroupRelationships $relationships;
}
