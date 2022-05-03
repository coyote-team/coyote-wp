<?php

namespace Coyote;

use Coyote\Model\ProfileModel;
use Coyote\Model\RepresentationModel;
use Coyote\Model\ResourceGroupModel;
use Coyote\Model\ResourceModel;
use Coyote\Payload\CreateResourcePayload;
use Coyote\Payload\CreateResourcesPayload;
use Coyote\Request\CreateResourceRequest;
use Coyote\Request\CreateResourcesRequest;
use Coyote\Request\GetProfileRequest;
use Coyote\Request\GetResourceGroupsRequest;
use Coyote\Request\GetResourceRepresentationsRequest;
use Coyote\Request\GetResourceRequest;
use Coyote\Request\GetResourcesRequest;
use Exception;

class CoyoteApiClient
{
    private InternalApiClient $apiClient;

    public function __construct(string $endpoint, string $apiToken, int $organizationId)
    {
        $this->apiClient = new InternalApiClient($endpoint, $apiToken, $organizationId);
    }

    public function getProfile(): ?ProfileModel
    {
        return (new GetProfileRequest($this->apiClient))->data();
    }

    /** @return ResourceModel[]|null */
    public function getResources(): ?array
    {
        return (new GetResourcesRequest($this->apiClient))->data();
    }

    public function getResource(string $id): ?ResourceModel
    {
        return (new GetResourceRequest($this->apiClient, $id))->data();
    }

    public function createResource(CreateResourcePayload $payload): ?ResourceModel
    {
        return (new CreateResourceRequest($this->apiClient, $payload))->perform();
    }

    /** @return ResourceModel[]|null */
    public function createResources(CreateResourcesPayload $payload): ?array
    {
        return (new CreateResourcesRequest($this->apiClient, $payload))->perform();
    }

    public function updateResource(string $id): void
    {
        throw new Exception("updateResource is not yet implemented.");
    }

    /** @return RepresentationModel[]|null */
    public function getResourceRepresentations(string $id): ?array
    {
        return (new GetResourceRepresentationsRequest($this->apiClient, $id))->data();
    }

    public function getResourceRepresentation(string $id): void
    {
        throw new Exception("getResourceRepresentation is not yet implemented.");
        //return (new GetResourceRepresentationRequest($this->apiClient, $id))->data();
    }

    /**
     * @return ResourceGroupModel[]|null
     */
    public function getResourceGroups(): ?array
    {
        return (new GetResourceGroupsRequest($this->apiClient))->data();
    }

    public function getResourceGroup(string $id): ?ResourceGroupModel
    {
        throw new Exception("getResourceGroup is not yet implemented.");
    }

    public function createResourceGroup(): ?ResourceGroupModel
    {
        throw new Exception("createResourceGroup is not yet implemented.");
    }
}
