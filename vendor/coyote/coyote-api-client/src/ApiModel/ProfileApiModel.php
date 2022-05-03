<?php

namespace Coyote\ApiModel;

use Coyote\ApiModel\Partial\ProfileAttributes;
use Coyote\ApiModel\Partial\ProfileRelationships;

class ProfileApiModel
{
    public string $id;
    public string $type;
    public ProfileAttributes $attributes;
    public ProfileRelationships $relationships;
}
