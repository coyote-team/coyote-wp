<?php

namespace Coyote;

use Coyote\Model\ProfileModel;
use Coyote\Model\ResourceGroupModel;
use Coyote\Model\ResourceModel;
use Coyote\Payload\CreateResourceGroupPayload;
use Coyote\Payload\CreateResourcePayload;
use Coyote\Payload\CreateResourcesPayload;

class WordPressCoyoteApiClient{

    /** @return ResourceModel[]|null */
    public static function createResources(CreateResourcesPayload $payload): ?array
    {
        return self::createApiClient()->createResources($payload);
    }

    public static function createResource(CreateResourcePayload $payload): ?ResourceModel
    {
        return self::createApiClient()->createResource($payload);
    }

    public static function getProfile(): ?ProfileModel
    {
        return self::createApiClient()->getProfile();
    }

    public static function createResourceGroup(string $url): ?ResourceGroupModel
    {
        $payload = new CreateResourceGroupPayload(PluginConfiguration::RESOURCE_GROUP_NAME, $url);
        return self::createApiClient()->createResourceGroup($payload);
    }

    private static function createApiClient():CoyoteApiClient{
        return new CoyoteApiClient(
            PluginConfiguration::getApiEndPoint(),
            PluginConfiguration::getApiToken(),
            PluginConfiguration::getApiOrganizationId()
        );
    }

}
