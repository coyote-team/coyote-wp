<?php

namespace Coyote;

use Coyote\Traits\Logger;
use Coyote\Model\ProfileModel;
use Coyote\Model\ResourceGroupModel;
use Coyote\Model\ResourceModel;
use Coyote\Payload\CreateResourceGroupPayload;
use Coyote\Payload\CreateResourcePayload;
use Coyote\Payload\CreateResourcesPayload;

if (!defined('WPINC')) {
    exit;
}

class WordPressCoyoteApiClient
{
    use Logger;

    /** @return ResourceModel[]|null */
    public static function createResources(CreateResourcesPayload $payload): ?array
    {
        $resources = self::createApiClient()->createResources($payload);

        if (is_null($resources)) {
            self::registerApiError("Null response when attempting to create resources");
        } else {
            self::registerApiSuccess();
        }

        return $resources;
    }

    public static function createResource(CreateResourcePayload $payload): ?ResourceModel
    {
        $resource = self::createApiClient()->createResource($payload);

        if (is_null($resource)) {
            self::registerApiError("Null response when attempting to create resource");
        } else {
            self::registerApiSuccess();
        }

        return $resource;
    }

    public static function getProfile(): ?ProfileModel
    {
        $profile = self::createApiClient()->getProfile();

        if (is_null($profile)) {
            self::registerApiError("Null response when attempting to fetch profile");
        } else {
            self::registerApiSuccess();
        }

        return $profile;
    }

    public static function getResourceGroupByUrl(string $url): ?ResourceGroupModel
    {
        $groups = self::createApiClient()->getResourceGroups();

        if (is_null($groups)) {
            self::registerApiError("Null response when fetching resource groups");
            return null;
        }

        $matches = array_filter($groups, function (ResourceGroupModel $group) use ($url): bool {
            return $group->getUri() === $url;
        });

        if (count($matches) === 1) {
            return array_pop($matches);
        }

        return null;
    }

    public static function createResourceGroup(string $url): ?ResourceGroupModel
    {
        $payload = new CreateResourceGroupPayload(PluginConfiguration::RESOURCE_GROUP_NAME, $url);
        $group = self::createApiClient()->createResourceGroup($payload);

        if (is_null($group)) {
            self::registerApiError("Null response when attempting to create resource group");
        } else {
            self::registerApiSuccess();
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
            self::logDebug('Un-scheduling standalone check');
            wp_clear_scheduled_hook('coyote_check_standalone_hook');
        }

        // clear any existing api error count
        PluginConfiguration::clearApiErrorCount();
    }

    public static function registerApiError(string $message): void
    {
        self::logDebug("Coyote API error: $message");
        PluginConfiguration::raiseApiErrorCount();
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
