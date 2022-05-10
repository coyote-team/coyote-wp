<?php

namespace Coyote;

class PluginConfiguration{

    public const METUM = 'Alt';

    public static function getApiEndPoint(): ?string {
        return get_option('coyote_api_endpoint', null);
    }

    public static function getApiToken(): ?string {
        return get_option('coyote_api_token', null);
    }

    public static function getApiOrganizationId(): ?int {
        $organizationId = intval(get_option('coyote_api_organization_id', -1));
        return $organizationId > -1 ? $organizationId : null;
    }

    public static function getApiResourceGroupId(): ?int {
        $resourceGroupId = intval(get_option('coyote_api_resource_group_id', -1));
        return $resourceGroupId > -1 ? $resourceGroupId : null;
    }
}