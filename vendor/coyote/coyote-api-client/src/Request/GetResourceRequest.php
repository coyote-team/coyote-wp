<?php

namespace Coyote\Request;

use Coyote\ApiModel\OrganizationApiModel;
use Coyote\ApiModel\ResourceRepresentationApiModel;
use Coyote\ApiResponse\GetResourceApiResponse;
use Coyote\InternalApiClient;
use Coyote\Model\ResourceModel;
use Coyote\RequestLogger;
use JsonMapper\JsonMapperFactory;
use Monolog\Logger;
use stdClass;

class GetResourceRequest
{
    private const PATH = '/resources/%s';

    private InternalApiClient $client;
    private RequestLogger $logger;

    private string $resource_id;

    public function __construct(InternalApiClient $client, string $resource_id, int $logLevel = Logger::INFO)
    {
        $this->client = $client;
        $this->logger = new RequestLogger('GetResourceRequest', $logLevel);
        $this->resource_id = $resource_id;
    }

    public function data(): ?ResourceModel
    {
        $this->logger->debug("Fetching resource {$this->resource_id}");

        try {
            $json = $this->client->get(sprintf(self::PATH, $this->resource_id));
        } catch (\Exception $error) {
            $this->logger->error("Error fetching resource {$this->resource_id}: " . $error->getMessage());
            return null;
        }

        if (is_null($json)) {
            $this->logger->warn("Unexpected null response when fetching resource {$this->resource_id}");
            return null;
        }

        return $this->mapResponseToResourceModel($json);
    }

    private function mapResponseToResourceModel(stdClass $json): ResourceModel
    {
        $mapper = (new JsonMapperFactory())->bestFit();
        $response = new GetResourceApiResponse();
        $mapper->mapObject($json, $response);

        $organizationApiModel = $this->getOrganizationApiModel($response);
        $representationApiModels = $this->getRepresentationApiModels($response);

        return new ResourceModel($response->data, $organizationApiModel, $representationApiModels);
    }

    private function getOrganizationApiModel(GetResourceApiResponse $response): OrganizationApiModel
    {
        $mapper = (new JsonMapperFactory())->bestFit();

        $organizationApiModel = new OrganizationApiModel();

        /** @var stdClass[] $organizationApiData */
        $organizationApiData = array_filter($response->included, function ($data) {
            return $data->type === OrganizationApiModel::TYPE;
        });

        $data = array_shift($organizationApiData) ?? new stdClass();

        $mapper->mapObject($data, $organizationApiModel);

        return $organizationApiModel;
    }

    /** @return ResourceRepresentationApiModel[]
     */
    private function getRepresentationApiModels(GetResourceApiResponse $response): array
    {
        $mapper = (new JsonMapperFactory())->bestFit();

        return $mapper->mapArray(array_filter($response->included, function ($data) {
            return $data->type === ResourceRepresentationApiModel::TYPE;
        }), new ResourceRepresentationApiModel());
    }
}
