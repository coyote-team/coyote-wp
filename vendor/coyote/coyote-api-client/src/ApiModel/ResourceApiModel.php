<?php

namespace Coyote\ApiModel;

use Coyote\ApiModel\Partial\ResourceAttributes;
use Coyote\ApiModel\Partial\ResourceLinks;
use Coyote\ApiModel\Partial\ResourceRelationships;

class ResourceApiModel
{
    public string $id;
    public string $type;
    public ResourceAttributes $attributes;
    public ResourceRelationships $relationships;
    public ResourceLinks $links;
}
