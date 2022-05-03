<?php

namespace Coyote\Request;

use Coyote\ApiHelper\ResourceRelatedModelInstanceFactory;
use Coyote\ApiModel\AbstractResourceRelatedApiModel;
use Coyote\ApiModel\MembershipApiModel;
use Coyote\ApiModel\OrganizationApiModel;
use Coyote\ApiModel\Partial\MembershipAttributes;
use Coyote\ApiModel\ProfileApiModel;
use Coyote\ApiResponse\GetProfileApiResponse;
use Coyote\InternalApiClient;
use Coyote\Model\ProfileModel;
use Coyote\RequestLogger;
use JsonMapper\Builders\PropertyMapperBuilder;
use JsonMapper\Handler\FactoryRegistry;
use JsonMapper\JsonMapperBuilder;
use JsonMapper\JsonMapperFactory;

use Monolog\Logger;
use stdClass;

class GetProfileRequest
{
    private const PATH = '/profile/';

    private InternalApiClient $client;
    private RequestLogger $logger;

    public function __construct(InternalApiClient $client, int $logLevel = Logger::INFO)
    {
        $this->client = $client;
        $this->logger = new RequestLogger('GetProfileRequest', $logLevel);
    }

    /** @return ProfileModel|null */
    public function data(): ?ProfileModel
    {
        $this->logger->debug('Fetching profile');

        try {
            $json = $this->client->get(self::PATH);
        } catch (\Exception $error) {
            $this->logger->error('Error fetching profile: ' . $error->getMessage());
            return null;
        }

        if (is_null($json)) {
            $this->logger->warn('Unexpected null response when fetching profile');
            return null;
        }

        return $this->mapResponseToProfileModel($json);
    }

    private function mapResponseToProfileModel(stdClass $json): ProfileModel
    {
        $mapper = (new JsonMapperFactory())->bestFit();

        $response = new GetProfileApiResponse();
        $mapper->mapObject($json, $response);

        $profileApiModel = $this->getProfileApiModel($response);
        $organizationApiModels = $this->getOrganizationApiModels($response);
        $membershipApiModels = $this->getMembershipApiModels($response);

        return new ProfileModel($profileApiModel, $organizationApiModels, $membershipApiModels);
    }

    private function getProfileApiModel(GetProfileApiResponse $response): ProfileApiModel
    {
        return $response->data;
    }

    /** @return MembershipApiModel[] */
    private function getMembershipApiModels(GetProfileApiResponse $response): array
    {
        $memberships = array_filter($response->included, function (stdClass $item) {
            return $item->type === MembershipApiModel::TYPE;
        });

        $mapper = (new JsonMapperFactory())->bestFit();

        return array_map(function (stdClass $item) use ($mapper): MembershipApiModel {
            return $mapper->mapObject($item, new MembershipApiModel());
        }, $memberships);
    }

    /** @return OrganizationApiModel[] */
    private function getOrganizationApiModels(GetProfileApiResponse $response): array
    {
        $organizations = array_filter($response->included, function (stdClass $item) {
            return $item->type === OrganizationApiModel::TYPE;
        });

        $mapper = (new JsonMapperFactory())->bestFit();

        return array_map(function (stdClass $item) use ($mapper): OrganizationApiModel {
            return $mapper->mapObject($item, new OrganizationApiModel());
        }, $organizations);
    }
}
