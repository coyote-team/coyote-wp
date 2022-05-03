<?php

namespace Coyote\Payload;

class ResourceRepresentationPayload
{
    public string $text;
    public string $metum;

    public string $language = 'en';
    public string $status = 'approved';

    public function __construct(string $text, string $metum)
    {
        $this->text = $text;
        $this->metum = $metum;
    }
}
