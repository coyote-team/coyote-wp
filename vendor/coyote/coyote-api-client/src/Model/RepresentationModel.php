<?php

namespace Coyote\Model;

use Coyote\ApiModel\ResourceRepresentationApiModel;

class RepresentationModel
{
    private string $id;
    private string $status;
    private int $ordinality;

    /**
     * @return int
     */
    public function getOrdinality(): int
    {
        return $this->ordinality;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return string|null
     */
    public function getUri(): ?string
    {
        return $this->uri;
    }

    /**
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @return string
     */
    public function getMetum(): string
    {
        return $this->metum;
    }

    /**
     * @return string
     */
    public function getAuthor(): string
    {
        return $this->author;
    }
    private ?string $uri;
    private string $language;
    private string $text;
    private string $metum;
    private string $author;

    public function __construct(ResourceRepresentationApiModel $model)
    {
        $this->id = $model->id;
        $this->status = $model->attributes->status;
        $this->uri = $model->attributes->content_uri;
        $this->language = $model->attributes->language;
        $this->text = $model->attributes->text;
        $this->metum = $model->attributes->metum;
        $this->author = $model->attributes->author;
        $this->ordinality = $model->attributes->ordinality;
    }
}
