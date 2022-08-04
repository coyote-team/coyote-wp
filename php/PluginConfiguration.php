<?php

namespace Coyote;

use Coyote\Model\ProfileModel;

class PluginConfiguration
{

    public const METUM = 'Alt';
    public const DEFAULT_ENDPOINT = 'https://staging.coyote.pics';
    public const RESOURCE_GROUP_NAME = 'WordPress';
    public const PLUGIN_VERSION = '2.0.0';
    public const API_VERSION = 1;

    public static function getApiEndPoint(): ?string
    {
        return get_option('coyote_api_endpoint', self::DEFAULT_ENDPOINT);
    }

    public static function getApiToken(): ?string
    {
        return get_option('coyote_api_token', null);
    }

    public static function getApiOrganizationId(): ?string
    {
        return get_option('coyote_api_organization_id', null);
    }

    public static function getMetum(): ?string
    {
        $metum = get_option('coyote_api_metum', self::METUM);
        return self::isNonEmptyString( $metum ) ? $metum : self::METUM;
    }

    public static function setApiOrganizationId(string $id): void
    {
        update_option('coyote_api_organization_id', $id);
    }

    public static function getApiResourceGroupId(): ?int
    {
        $resourceGroupId = intval(get_option('coyote_api_resource_group_id', -1));
        return $resourceGroupId > -1 ? $resourceGroupId : null;
    }

    public static function isStandalone(): bool
    {
        return get_option('coyote_is_standalone', false);
    }

    public static function isDisabled(): bool
    {
        return get_option('coyote_standalone_error', false);
    }

    public static function isNotStandalone(): bool
    {
        return self::isEnabled();
    }

    public static function isEnabled(): bool
    {
        return !self::isStandalone();
    }

    public static function isDisabledByPlugin(): bool
    {
        return self::isStandalone() && self::isDisabled();
    }

    public static function isDisabledByUser(): bool
    {
        return self::isStandalone() && !self::isDisabled();
    }

    public static function hasFiltersEnabled(): bool
    {
        return !!get_option('coyote_filters_enabled', false);
    }

    public static function hasUpdatesEnabled(): bool
    {
        return !!get_option('coyote_updates_enabled', false);
    }

    public static function hasStoredApiProfile(): bool
    {
        return !is_null(get_option('coyote_api_profile', null));
    }

    public static function isInstalled(): bool
    {
        return !!get_option('coyote_plugin_is_installed', false);
    }

    /** @return int|bool */
    public static function getApiErrorCount(): int
    {
        return get_transient('coyote_api_error_count') ?? false;
    }

    public static function setApiErrorCount(int $count): void
    {
        set_transient('coyote_api_error_count', $count);
    }

    public static function setResourceGroupId(int $id): void
    {
        update_option('coyote_api_resource_group_id', $id);
    }

    public static function getApiProfile(): ?ProfileModel
    {
        return self::possiblyMigrateApiProfile( get_option('coyote_api_profile', null) );
    }

    /*
     * Check if ProfileModel is outdated (v1 object)
     * If so, retrieve v2 model and update the object in the database
     */
    public static function possiblyMigrateApiProfile($profile): ?ProfileModel
    {
        if (is_null($profile))
            return null;

        if (!$profile instanceof ProfileModel)
            $profile = WordPressCoyoteApiClient::getProfile();

        if(!is_null($profile))
            self::setApiProfile($profile);

        return $profile;
    }

    public static function setApiProfile(ProfileModel $profile): void
    {
        update_option('coyote_api_profile', $profile);
    }

    public static function setDisabledByPlugin(): void
    {
        update_option('coyote_is_standalone', true);
        update_option('coyote_error_standalone', true);
    }

    public static function setEnabledThroughRecovery(): void
    {
        update_option('coyote_is_standalone', false);
        update_option('coyote_error_standalone', false);
    }

    public static function clearApiErrorCount(): void
    {
        delete_transient('coyote_api_error_count');
    }

    /**
     * Check if the current user has administrator privileges
     * @return bool
     */
    public static function userIsAdmin(): bool
    {
        return current_user_can('administrator');
    }

    public static function isProcessingUnpublishedPosts(): bool
    {
        return !self::isNotProcessingUnpublishedPosts();
    }

    public static function isNotProcessingUnpublishedPosts(): bool
    {
        return !!get_option('coyote_skip_unpublished_enabled', true);
    }

    public static function getProcessedPostTypes(): array
    {
        return ['page', 'post', 'attachment'];
    }

    public static function deletePluginOptions(): void
    {
        $options = [
            'coyote_api_version',
            'coyote_api_token',
            'coyote_api_endpoint',
            'coyote_api_metum',
            'coyote_api_organization_id',
            'coyote_api_profile',
            'coyote_filters_enabled', 'coyote_updates_enabled', 'coyote_processor_endpoint',
            'coyote_plugin_is_installed'
        ];

        foreach ($options as $option) {
            delete_option($option);
        }
    }

    public static function setInstalled(): void
    {
        add_option('coyote_plugin_is_installed', true);
    }

    /**
     * @param mixed $arg
     * @return bool
     */
    private static function isNonEmptyString(?string $arg = null): bool
    {
        return is_string($arg) &&
            strlen($arg) >= 1;
    }

    public static function hasApiConfiguration(): bool
    {
        return self::isNonEmptyString(self::getApiEndPoint()) &&
            self::isNonEmptyString(self::getApiToken());
    }
}
