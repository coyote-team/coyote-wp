<?php

namespace Coyote\Model;

use Coyote\ApiModel\ResourceGroupApiModel;

class ResourceGroupModel
{
    private string $id;

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
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getUri(): ?string
    {
        return $this->uri;
    }

    /**
     * @return bool
     */
    public function isDefault(): bool
    {
        return $this->isDefault;
    }
    private string $name;
    private ?string $uri;
    private bool $isDefault;

    public function __construct(ResourceGroupApiModel $model)
    {
        $this->id = $model->id;
        $this->name = $model->attributes->name;
        $this->uri = $model->attributes->webhook_uri;
        $this->isDefault = $model->attributes->default;
    }
}
