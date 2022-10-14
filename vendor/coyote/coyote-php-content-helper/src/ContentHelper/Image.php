<?php

namespace Coyote\ContentHelper;

class Image
{
    public readonly string $src;
    public readonly ?string $alt;
    public readonly ?string $class;
    public readonly ?string $content_before;
    public readonly ?string $content_after;

    public function __construct(
        string $src,
        string $alt,
        string $class,
        string $content_before = '',
        string $content_after = ''
    ) {
        $this->src = $src;
        $this->alt = $alt;
        $this->class = $class;
        $this->content_before = $content_before;
        $this->content_after = $content_after;
    }
}
