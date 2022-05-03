<?php

namespace Coyote\Model;

use Coyote\ApiModel\OrganizationApiModel;

class OrganizationModel
{
    private string $id;
    private string $name;

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

    public function __construct(OrganizationApiModel $model)
    {
        $this->id = $model->id;
        $this->name = $model->attributes->name;
    }
}
