<?php

namespace Coyote;

use Coyote\Model\MembershipModel;
use Coyote\Model\OrganizationModel;
use Coyote\Model\ProfileModel;
use Coyote\Model\ResourceGroupModel;
use Coyote\Model\ResourceModel;
use Coyote\Payload\CreateResourceGroupPayload;
use Coyote\Payload\CreateResourcePayload;
use Coyote\Payload\CreateResourcesPayload;
use Coyote\Request\CreateResourceGroupRequest;
use Coyote\Request\CreateResourcesRequest;
use Coyote\Request\GetMembershipsRequest;
use Coyote\Request\GetProfileRequest;
use Coyote\Request\GetResourceGroupsRequest;
use Coyote\Request\GetResourceRequest;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class CoyoteApiClientHelperFunctions
{
    public static function getResourceGroupByUri(
        string $endpoint,
        string $token,
        int $organizationId,
        string $uri
    ): ?ResourceGroupModel {
        $client = new InternalApiClient($endpoint, $token, $organizationId);
        $groups = (new GetResourceGroupsRequest($client))->data();

        if (empty($groups)) {
            return null;
        }

        $matches = array_filter($groups, function (ResourceGroupModel $group) use ($uri) :bool {
            return $group->getUri() === $uri;
        });

        if (empty($matches)) {
            return null;
        }

        return array_shift($matches);
    }

    public static function getResourceById(
        string $endpoint,
        string $token,
        int $organizationId,
        string $resourceId
    ): ?ResourceModel {
        $client = new InternalApiClient($endpoint, $token, $organizationId);
        return (new GetResourceRequest($client, $resourceId))->data();
    }

    public static function getProfile(string $endpoint, string $token): ?ProfileModel
    {
        $client = new InternalApiClient($endpoint, $token, null);
        return (new GetProfileRequest($client))->data();
    }

    /**
     * @param string $endpoint
     * @param string $token
     * @return MembershipModel[]|null
     */
    public static function getOrganizationMemberships(string $endpoint, string $token, int $organizationId): ?array
    {
        $client = new InternalApiClient($endpoint, $token, $organizationId);
        return (new GetMembershipsRequest($client))->data();
    }

    /** @return OrganizationModel[]|null */
    public static function getOrganizations(string $endpoint, string $token): ?array
    {
        $profile = static::getProfile($endpoint, $token);

        if (is_null($profile)) {
            return null;
        }

        return $profile->getOrganizations();
    }

    public static function getProfileName(string $endpoint, string $token): ?string
    {
        $profile = static::getProfile($endpoint, $token);

        if (is_null($profile)) {
            return null;
        }

        return $profile->getName();
    }

    public static function createResourceGroup(
        string $endpoint,
        string $token,
        int $organizationId,
        string $groupName,
        string $groupUri = null
    ): ?ResourceGroupModel {
        $client = new InternalApiClient($endpoint, $token, $organizationId);
        return (new CreateResourceGroupRequest(
            $client,
            new CreateResourceGroupPayload($groupName, $groupUri)
        ))->perform();
    }

    /**
     * @param string $endpoint
     * @param string $token
     * @param int $organizationId
     * @param CreateResourcePayload[] $resources
     * @return ResourceModel[]|null
     * @throws Exception|GuzzleException
     */
    public static function createResources(
        string $endpoint,
        string $token,
        int $organizationId,
        array $resources
    ): ?array {
        $client = new InternalApiClient($endpoint, $token, $organizationId);
        $payload = new CreateResourcesPayload();

        foreach ($resources as $resource) {
            $payload->addResource($resource);
        }

        return (new CreateResourcesRequest($client, $payload))->perform();
    }
}
