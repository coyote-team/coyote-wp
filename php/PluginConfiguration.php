<?php

namespace Coyote;

use Coyote\Model\ProfileModel;

class PluginConfiguration{

    public const METUM = 'Alt';
    public const RESOURCE_GROUP_NAME = 'WordPress';

    public static function getApiEndPoint(): ?string {
        return get_option('coyote_api_endpoint', null);
    }

    public static function getApiToken(): ?string {
        return get_option('coyote_api_token', null);
    }

    public static function getApiOrganizationId(): ?string {
        return get_option('coyote_api_organization_id', null);
    }

    public static function setApiOrganizationId(string $id): void
    {
        update_option('coyote_api_organization_id', $id);
    }

    public static function getApiResourceGroupId(): ?int {
        $resourceGroupId = intval(get_option('coyote_api_resource_group_id', -1));
        return $resourceGroupId > -1 ? $resourceGroupId : null;
    }

    public static function isStandalone(): bool {
        return get_option('coyote_is_standalone', true);
    }

    public static function isEnabled(): bool {
        return !self::isStandalone();
    }

    public static function setResourceGroupId(int $id): void
    {
        update_option('coyote_api_resource_group_id', $id);
    }

    public static function getApiProfile(): ?ProfileModel
    {
        return get_option('coyote_api_profile', null);
    }

    public static function setApiProfile(ProfileModel $profile): void
    {
        update_option('coyote_api_profile', $profile);
    }
}