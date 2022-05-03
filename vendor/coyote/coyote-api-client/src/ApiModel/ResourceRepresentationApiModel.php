<?php

namespace Coyote\ApiModel;

use Coyote\ApiModel\Partial\ResourceLinks;
use Coyote\ApiModel\Partial\ResourceRepresentationAttributes;
use stdClass;

class ResourceRepresentationApiModel extends AbstractResourceRelatedApiModel
{
    public const TYPE = 'representation';

    public ResourceRepresentationAttributes $attributes;

    // TODO perhaps abstract into class
    public stdClass $relationships;

    public ResourceLinks $links;
}
