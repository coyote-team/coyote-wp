<?php

namespace Coyote\ApiModel\Partial;

class ResourceGroupAttributes
{
    public string $name;
    public bool $default;
    public ?string $webhook_uri;
    public ?string $token;
}
