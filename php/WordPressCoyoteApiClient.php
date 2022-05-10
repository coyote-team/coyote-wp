<?php

namespace Coyote;

use Coyote\Model\ResourceModel;
use Coyote\Payload\CreateResourcesPayload;

class WordPressCoyoteApiClient{

    /** @return ResourceModel[]|null */
    public static function createResources(CreateResourcesPayload $payload): ?array{
        return self::createApiClient()->createResources($payload);
    }

    private static function createApiClient():CoyoteApiClient{
        return new CoyoteApiClient(
            PluginConfiguration::getApiEndPoint(),
            PluginConfiguration::getApiToken(),
            PluginConfiguration::getApiOrganizationId()
        );
    }

}
