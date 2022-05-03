<?php

namespace Coyote\Model;

use Coyote\ApiModel\MembershipApiModel;
use Coyote\ApiModel\OrganizationApiModel;
use Coyote\ApiModel\ProfileApiModel;

class ProfileModel
{
    private string $id;
    private string $name;

    /** @var OrganizationModel[] */
    private array $organizations;

    /** @var MembershipModel[] */
    private array $memberships;

    /**
     * @param ProfileApiModel $model
     * @param OrganizationApiModel[] $organizationApiModels
     * @param MembershipApiModel[] $membershipApiModels
     */
    public function __construct(ProfileApiModel $model, array $organizationApiModels, array $membershipApiModels)
    {
        $this->id = $model->id;
        $this->name = join(' ', [$model->attributes->first_name, $model->attributes->last_name]);
        $this->organizations = $this->mapOrganizationApiModelsToOrganizationModels($organizationApiModels);
        $this->memberships = $this->mapMembershipApiModelsToMembershipModels($membershipApiModels);
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
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return OrganizationModel[]
     */
    public function getOrganizations(): array
    {
        return $this->organizations;
    }

    /**
     * @return MembershipModel[]
     */
    public function getMemberships(): array
    {
        return $this->memberships;
    }

    /**
     * @param array<OrganizationApiModel> $apiModels
     * @return array<OrganizationModel>
     */
    private function mapOrganizationApiModelsToOrganizationModels(array $apiModels): array
    {
        return array_map(function (OrganizationApiModel $apiModel): OrganizationModel {
            return new OrganizationModel($apiModel);
        }, $apiModels);
    }

    /**
     * @param array<MembershipApiModel> $apiModels
     * @return array<MembershipModel>
     */
    private function mapMembershipApiModelsToMembershipModels(array $apiModels): array
    {
        return array_map(function (MembershipApiModel $apiModel): MembershipModel {
            return new MembershipModel($apiModel);
        }, $apiModels);
    }
}
