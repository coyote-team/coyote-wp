<?php

namespace Coyote\Payload;

class CreateResourcesPayload
{
    /** @var CreateResourcePayload[] */
    public array $resources;

    public function __construct()
    {
        $this->resources = [];
    }

    public function addResource(CreateResourcePayload $resource): void
    {
        $this->resources[] = $resource;
    }
}
