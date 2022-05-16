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
        $resources = self::createApiClient()->createResources($payload);

        if (is_null($resources)) {
            self::registerApiError("Null response when attempting to create resources");
        }

        return $resources;
    }

    public static function createResource(CreateResourcePayload $payload): ?ResourceModel
    {
        $resource = self::createApiClient()->createResource($payload);

        if (is_null($resource)) {
            self::registerApiError("Null response when attempting to create resource");
        }

        return $resource;
    }

    public static function getProfile(): ?ProfileModel
    {
        $profile = self::createApiClient()->getProfile();

        if (is_null($profile)) {
            self::registerApiError("Null response when attempting to fetch profile");
        }

        return $profile;
    }

    public static function createResourceGroup(string $url): ?ResourceGroupModel
    {
        $payload = new CreateResourceGroupPayload(PluginConfiguration::RESOURCE_GROUP_NAME, $url);
        $group = self::createApiClient()->createResourceGroup($payload);

        if (is_null($group)) {
            self::registerApiError("Null response when attempting to create resource group");
        }

        return $group;
    }

    public static function registerApiSuccess(): void
    {
        if (PluginConfiguration::isDisabledByPlugin()) {
            // plugin is in standalone because of api errors, a success can recover.
            // we don't recover in case of manual standalone.
            PluginConfiguration::setEnabledThroughRecovery();

            // clear the cron recovery attempt logic
            Logger::log('Unscheduling standalone check');
            wp_clear_scheduled_hook('coyote_check_standalone_hook');
        }

        // clear any existing api error count
        PluginConfiguration::clearApiErrorCount();
    }

    public static function registerApiError(string $message): void
    {
        Logger::log("Coyote API error: $message");

        $count = PluginConfiguration::getApiErrorCount();

        if ($count === false) {
            $count = 1;
        } else {
            $count = intval($count) + 1;
        }

        Logger::log("Updating API error count to $count");

        PluginConfiguration::setApiErrorCount($count);
    }

    private static function getVersionedApiURI(): string
    {
        return sprintf("%s/api/v%d", PluginConfiguration::getApiEndPoint(), PluginConfiguration::API_VERSION);
    }

    private static function createApiClient(): CoyoteApiClient
    {
        $organizationId = PluginConfiguration::getApiOrganizationId();

        // ApiClient expects an int as OrganizationId
        if (!is_null($organizationId)) {
            $organizationId = intval($organizationId);
        }

        return new CoyoteApiClient(
            self::getVersionedApiURI(),
            PluginConfiguration::getApiToken(),
            $organizationId
        );
    }

}
