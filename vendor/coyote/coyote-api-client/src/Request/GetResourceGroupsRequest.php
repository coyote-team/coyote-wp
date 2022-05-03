<?php

namespace Coyote\Request;

use Coyote\ApiResponse\GetResourceGroupsApiResponse;
use Coyote\InternalApiClient;
use Coyote\Model\ResourceGroupModel;
use Coyote\RequestLogger;
use JsonMapper\JsonMapperFactory;
use Monolog\Logger;
use stdClass;

class GetResourceGroupsRequest
{
    private const PATH = '/resource_groups/';

    private InternalApiClient $client;
    private RequestLogger $logger;

    public function __construct(InternalApiClient $client, int $logLevel = Logger::INFO)
    {
        $this->client = $client;
        $this->logger = new RequestLogger('GetProfileRequest', $logLevel);
    }

    /** @return ResourceGroupModel[]|null */
    public function data(): ?array
    {
        $this->logger->debug('Fetching resource groups');

        try {
            $json = $this->client->get(self::PATH, [InternalApiClient::INCLUDE_ORG_ID => true]);
        } catch (\Exception $error) {
            $this->logger->error('Error fetching resource groups: ' . $error->getMessage());
            return null;
        }

        if (is_null($json)) {
            $this->logger->warn('Unexpected null response when fetching resource groups');
            return null;
        }

        return $this->mapResponseToResourceGroupModels($json);
    }

    /** @return ResourceGroupModel[] */
    private function mapResponseToResourceGroupModels(stdClass $json): array
    {
        $mapper = (new JsonMapperFactory())->bestFit();
        $response = new GetResourceGroupsApiResponse();
        $mapper->mapObject($json, $response);

        return $this->mapResourceGroupApiModelsToResourceGroupModels($response);
    }

    /** @return ResourceGroupModel[] */
    private function mapResourceGroupApiModelsToResourceGroupModels(GetResourceGroupsApiResponse $response): array
    {
        return array_map(function ($apiModel) {
            return new ResourceGroupModel($apiModel);
        }, $response->data);
    }
}
