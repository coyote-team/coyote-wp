<?php

namespace Coyote\Request;

use Coyote\ApiHelper\ResourceRelatedModelInstanceFactory;
use Coyote\ApiModel\AbstractResourceRelatedApiModel;
use Coyote\ApiModel\OrganizationApiModel;
use Coyote\ApiModel\ResourceApiModel;
use Coyote\ApiModel\ResourceRepresentationApiModel;
use Coyote\ApiResponse\GetResourcesApiResponse;
use Coyote\InternalApiClient;
use Coyote\Model\ResourceModel;
use Coyote\RequestLogger;
use JsonMapper\Exception\BuilderException;
use JsonMapper\Exception\ClassFactoryException;
use JsonMapper\Handler\FactoryRegistry;
use JsonMapper\JsonMapperBuilder;
use JsonMapper\JsonMapperFactory;
use JsonMapper\Builders\PropertyMapperBuilder;
use Monolog\Logger;
use stdClass;

class GetResourcesRequest
{
    private const PATH = '/resources/';

    private InternalApiClient $client;
    private RequestLogger $logger;

    public function __construct(InternalApiClient $client, int $logLevel = Logger::INFO)
    {
        $this->client = $client;
        $this->logger = new RequestLogger('GetResourcesRequest', $logLevel);
    }

    /** @return ResourceModel[]|null */
    public function data(
        ?int $pageNumber = null,
        ?int $pageSize = null,
        ?string $filterString = null,
        ?string $filterScope = null
    ): ?array {
        try {
            $json = $this->client->get(self::PATH, [InternalApiClient::INCLUDE_ORG_ID => true]);
        } catch (\Exception $error) {
            $this->logger->error("Error fetching resources: " . $error->getMessage());
            return null;
        }

        if (is_null($json)) {
            $this->logger->warn("Unexpected null response when fetching resources");
            return null;
        }

        return $this->mapResponseToResourceModels($json);
    }

    /** @return ResourceModel[] */
    private function mapResponseToResourceModels(stdClass $json): array
    {
        $mapper = (new JsonMapperFactory())->bestFit();

        $response = new GetResourcesApiResponse();
        $mapper->mapObject($json, $response);

        $organizationApiModel = $this->getOrganizationApiModel($response);
        $representationApiModels = $this->getRepresentationApiModels($response);

        return array_map(function (ResourceApiModel $model) use ($organizationApiModel, $representationApiModels) {
            return new ResourceModel($model, $organizationApiModel, $representationApiModels);
        }, $response->data);
    }

    private function getOrganizationApiModel(GetResourcesApiResponse $response): OrganizationApiModel
    {
        /** @var OrganizationApiModel[] $organizationApiData */
        $organizationApiData = array_filter($response->included, function ($data) {
            return get_class($data) === OrganizationApiModel::class;
        });

        return array_shift($organizationApiData);
    }

    /** @return ResourceRepresentationApiModel[] */
    private function getRepresentationApiModels(GetResourcesApiResponse $response): array
    {
        $mapper = (new JsonMapperFactory())->bestFit();

        return $mapper->mapArray(array_filter($response->included, function ($data) {
            return $data->type === ResourceRepresentationApiModel::TYPE;
        }), new ResourceRepresentationApiModel());
    }
}
