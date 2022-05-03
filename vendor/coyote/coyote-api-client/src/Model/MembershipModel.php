<?php

namespace Coyote\Model;

use Coyote\ApiModel\MembershipApiModel;

class MembershipModel
{
    private string $id;
    private string $name;
    private string $email;

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return string
     */
    public function getRole(): string
    {
        return $this->role;
    }
    private string $role;

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
     * @param MembershipApiModel $model
     */
    public function __construct(MembershipApiModel $model)
    {
        $this->id = $model->id;
        $this->name = join(' ', [$model->attributes->first_name, $model->attributes->last_name]);
        $this->email = $model->attributes->email;
        $this->role = $model->attributes->role;
    }
}
