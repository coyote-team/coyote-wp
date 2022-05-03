<?php

namespace Coyote\Payload;

class CreateResourceGroupPayload
{
    public string $name;
    public ?string $webhook_uri;

    public function __construct(string $name, string $uri = null)
    {
        $this->name = $name;
        $this->webhook_uri = $uri;
    }
}
