<?php

namespace Coyote\Payload;

class CreateResourcePayload
{
    public string $name;
    public string $source_uri;

    public ?string $resource_group_id = null;

    /** @var string[]|null */
    public ?array $host_uris = null;

    public string $resource_type = 'image';
    public string $language = 'en';

    /** @var ResourceRepresentationPayload[]|null */
    public ?array $representations = [];

    public function __construct(
        string $name,
        string $uri,
        string $resource_group_id = null,
        string $host_uri = null
    ) {
        $this->name = $name;
        $this->source_uri = $uri;

        if (!is_null($resource_group_id)) {
            $this->resource_group_id = $resource_group_id;
        }

        if (!is_null($host_uri)) {
            $this->host_uris = [$host_uri];
        }
    }

    public function addRepresentation(string $text, string $metum): void
    {
        $representation = new ResourceRepresentationPayload($text, $metum);

        if (is_null($this->representations)) {
            $this->representations = [$representation];
        } else {
            $this->representations[] = $representation;
        }
    }
}
