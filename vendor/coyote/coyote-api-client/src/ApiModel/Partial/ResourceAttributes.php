<?php

namespace Coyote\ApiModel\Partial;

class ResourceAttributes
{
    public int    $id;
    public string $name;
    public string $resource_type;
    public ?string $canonical_id;
    public string $source_uri;
    public string $created_at;
    public string $updated_at;
    public ?string $resource_group;
}
