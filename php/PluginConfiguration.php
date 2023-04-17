<?php

namespace Coyote;

use Coyote\Model\MembershipModel;
use Coyote\Model\OrganizationModel;
use Coyote\Model\ProfileModel;

if (!defined('WPINC')) {
    exit;
}

class PluginConfiguration
{
    /**
     * @var string PLUGIN_VERSION Coyote WordPress Plugin version
     */
    public const PLUGIN_VERSION = '2.0.0';

    /**
     * Set the version of the API to connect to on the endpoint
     * [endpoint]/api/[version] e.g. https://staging.coyote.pics/api/1
     *
     * @var int API_VERSION Coyote API version
     */
    public const API_VERSION = 1;

    /**
     * Set the version of the API to connect to on the endpoint
     * [endpoint]/api/[version] e.g. https://staging.coyote.pics/api/1
     *
     * @var string DEFAULT_ENDPOINT
     */
    public const DEFAULT_ENDPOINT = 'https://staging.coyote.pics';

    public const METUM = 'Alt';
    public const RESOURCE_GROUP_NAME = 'WordPress';
    public const ALLOWED_ROLES = ['editor', 'admin', 'owner'];
    public const PROCESSED_POST_TYPES = ['page', 'post', 'attachment'];
    public const TWIG_TEMPLATES_PATH = COYOTE_PLUGIN_PATH . 'php' . DIRECTORY_SEPARATOR . 'Views';

    /**
     * Update the plugin version in the database
     */
    public static function updatePluginVersion(): void
    {
        WordPressPlugin::checkForUpdates();
        update_option('coyote_plugin_version', self::PLUGIN_VERSION);
    }

    /**
     * @return string plugin version stored in database
     */
    public static function getStoredPluginVersion(): string
    {
        return get_option('coyote_plugin_version', '1');
    }

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

    /**
     * Check if an organization is set
     * @return bool
     */
    public static function hasApiOrganizationId(): bool
    {
        return self::isNonEmptyString(self::getApiOrganizationId());
    }

    public static function getMetum(): string
    {
        $metum = get_option('coyote_api_metum', self::METUM);
        return self::isNonEmptyString($metum) ? $metum : self::METUM;
    }

    public static function setApiOrganizationId(string $id): void
    {
        update_option('coyote_api_organization_id', $id);
    }

    public static function deleteApiProfile(): void
    {
        delete_option('coyote_api_profile');
    }

    public static function deleteApiOrganizationId(): void
    {
        delete_option('coyote_api_organization_id', null);
    }

    public static function getApiResourceGroupId(): ?int
    {
        $resourceGroupId = intval(get_option('coyote_api_resource_group_id', -1));
        return $resourceGroupId > -1 ? $resourceGroupId : null;
    }

    /**
     * Get Membership linked to the API profile
     *
     * @param string|null $organizationId
     *
     * @return ?MembershipModel
     */
    public static function getOrganizationMembership(string $organizationId = null): ?MembershipModel
    {
        // get linked organization id from options when id is omitted
        if (is_null($organizationId)) {
            $organizationId = self::getApiOrganizationId();
        }

        // when no organization id is set, return
        if (is_null($organizationId)) {
            return null;
        }

        // get the profile to retrieve the memberships
        $profile = self::getApiProfile();
        if (is_null($profile)) {
            return null;
        }

        // filter the membership that is linked to the organization id
        $matches = array_filter(
            $profile->getMemberships(),
            function (MembershipModel $mem) use ($organizationId): bool {
                $organization = $mem->getOrganization();
                return !is_null($organization) && $organization->getId() === $organizationId;
            }
        );

        if (count($matches) !== 1) {
            return null;
        }

        return array_shift($matches);
    }

    /**
     * Get Membership role linked to the API profile
     *
     * @param string|null $organizationId
     *
     * @return ?string
     */
    public static function getOrganizationMembershipRole(string $organizationId = null): ?string
    {
        $membershipModel = self::getOrganizationMembership($organizationId);
        return is_null($membershipModel) ? null : $membershipModel->getRole();
    }

    /**
     * Check if organization membership role is allowed to link with the API
     *
     * @param null $organizationId
     *
     * @return bool
     */
    public static function isOrganizationRoleAllowed($organizationId = null): bool
    {
        return in_array(self::getOrganizationMembershipRole($organizationId), self::ALLOWED_ROLES);
    }

    /**
     * @param ProfileModel $profile
     *
     * @return OrganizationModel[]
     */
    public static function getAllowedOrganizationsInProfile(ProfileModel $profile): array
    {
        $validMemberships = array_filter(
            $profile->getMemberships() ?? [],
            function (MembershipModel $membership): bool {
                return in_array($membership->getRole(), PluginConfiguration::ALLOWED_ROLES);
            }
        );

        return array_map(function (MembershipModel $membership): OrganizationModel {
            return $membership->getOrganization();
        }, $validMemberships);
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

    /**
     * Check if the plugin has been activated earlier
     *
     * @return bool
     */
    public static function hasBeenInstalledBefore(): bool
    {
        /*
         * if the plugin has been activated before and has been deactivated
         * the option 'coyote_plugin_is_installed' exists and = false
         * if the plugin hasn't been activated before the get_option return 'non-exists'
         */
        return 'not-exists' !== get_option('coyote_plugin_is_installed', 'not-exists');
    }

    /**
     * Get API error count
     * @return int
     */
    public static function getApiErrorCount(): int
    {
        return get_transient('coyote_api_error_count') ?? 0;
    }

    /**
     * Set API error count
     *
     * @param int $count new value to set the error count to
     *
     * @return void
     */
    public static function setApiErrorCount(int $count): void
    {
        set_transient('coyote_api_error_count', $count);
    }

    /**
     * Raise the API error count (current count +1)
     */
    public static function raiseApiErrorCount(): void
    {
        $count = self::getApiErrorCount() + 1;
        WordPressCoyoteApiClient::logDebug("Updating API error count to $count");
        self::setApiErrorCount($count);
    }

    public static function setResourceGroupId(int $id): void
    {
        update_option('coyote_api_resource_group_id', $id);
    }

    public static function getProcessingBatchSize(): int
    {
        return get_option('coyote_processing_batch_size', 50);
    }

    public static function setProcessingBatchSize(int $size): void
    {
        if ($size <= 0) {
            $size = 50;
        }

        update_option('coyote_processing_batch_size', $size);
    }

    public static function getApiProfile(): ?ProfileModel
    {
        return self::possiblyMigrateApiProfile(get_option('coyote_api_profile', null));
    }

    /*
     * Check if ProfileModel is outdated (v1 object)
     * If so, retrieve v2 model and update the object in the database
     */
    public static function possiblyMigrateApiProfile($profile): ?ProfileModel
    {
        if (is_null($profile)) {
            return null;
        }

        if (!$profile instanceof ProfileModel) {
            $profile = WordPressCoyoteApiClient::getProfile();
        }

        if (!is_null($profile)) {
            self::setApiProfile($profile);
        }

        return $profile;
    }

    /**
     * Check if profile has allowed organization roles
     *
     * @param ProfileModel $profile
     *
     * @return bool
     */
    public static function profileHasAllowedOrganizationRoles(ProfileModel $profile): bool
    {
        return !empty(self::getAllowedOrganizationsInProfile($profile));
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

    public static function isProcessingUnpublishedPosts(): bool
    {
        return !self::isNotProcessingUnpublishedPosts();
    }

    public static function isNotProcessingUnpublishedPosts(): bool
    {
        return !!get_option('coyote_skip_unpublished_enabled', true);
    }

    /**
     * get optionally stored post types to process
     * self::PROCESSED_POST_TYPES are always returned!
     *
     * @return array
     */
    public static function getProcessedPostTypes(): array
    {
        $processedPostTypes = get_option('coyote_plugin_processed_post_types', 'not-exists');
        return 'not-exists' === $processedPostTypes
            ? self::PROCESSED_POST_TYPES
            : array_unique(array_merge(self::PROCESSED_POST_TYPES, (array)$processedPostTypes));
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
            'coyote_filters_enabled',
            'coyote_updates_enabled',
            'coyote_plugin_is_installed',
            'coyote_plugin_version',
            'coyote_debugging_enabled'
        ];

        foreach ($options as $option) {
            delete_option($option);
        }
    }

    /**
     * Set plugin is installed in options table
     */
    public static function setInstalled(): void
    {
        update_option('coyote_plugin_is_installed', true);
    }

    /**
     * Unset plugin is installed in options table
     */
    public static function setUnInstalled(): void
    {
        update_option('coyote_plugin_is_installed', false);
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

    public static function isDebuggingEnabled(): bool
    {
        return get_option('coyote_debugging_enabled', false);
    }
}
