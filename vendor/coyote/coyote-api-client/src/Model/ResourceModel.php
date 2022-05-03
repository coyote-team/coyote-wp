<?php

namespace Coyote\Model;

use Coyote\ApiModel\OrganizationApiModel;
use Coyote\ApiModel\ResourceApiModel;
use Coyote\ApiModel\ResourceRepresentationApiModel;

class ResourceModel
{
    private string $id;
    private ?string $canonical_id;
    private string $name;
    private string $type;
    private string $source_uri;

    private ?OrganizationModel $organization;

    /** @var array<RepresentationModel> */
    private array $representations;

    /**
     * @param ResourceApiModel $model
     * @param OrganizationApiModel|null $organizationApiModel
     * @param array<ResourceRepresentationApiModel> $representations
     */
    public function __construct(
        ResourceApiModel $model,
        ?OrganizationApiModel $organizationApiModel,
        array $representations
    ) {
        $this->id = $model->id;
        $this->canonical_id = $model->attributes->canonical_id;
        $this->name = $model->attributes->name;
        $this->type = $model->attributes->resource_type;
        $this->source_uri = $model->attributes->source_uri;
        $this->organization = null;

        if (!is_null($organizationApiModel)) {
            $this->organization = new OrganizationModel($organizationApiModel);
        }

        $this->representations = array_map(function ($resourceRepresentationApiModel) {
            return new RepresentationModel($resourceRepresentationApiModel);
        }, $representations);
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
    public function getCanonicalId(): ?string
    {
        return $this->canonical_id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getSourceUri(): string
    {
        return $this->source_uri;
    }

    /**
     * @return OrganizationModel|null
     */
    public function getOrganization(): ?OrganizationModel
    {
        return $this->organization;
    }

    /**
     * @return RepresentationModel[]
     */
    public function getRepresentations(): array
    {
        return $this->representations;
    }
}
