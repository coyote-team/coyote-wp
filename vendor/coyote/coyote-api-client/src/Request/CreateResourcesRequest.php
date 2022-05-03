<?php

namespace Coyote\Request;

use Coyote\ApiModel\Partial\Relationship;
use Coyote\ApiModel\ResourceApiModel;
use Coyote\ApiModel\ResourceRepresentationApiModel;
use Coyote\ApiResponse\CreateResourcesApiResponse;
use Coyote\InternalApiClient;
use Coyote\Model\ResourceModel;
use Coyote\Payload\CreateResourcePayload;
use Coyote\Payload\CreateResourcesPayload;
use Coyote\RequestLogger;
use JsonMapper\JsonMapperFactory;
use Monolog\Logger;

class CreateResourcesRequest
{
    private const PATH = '/resources/create';

    private CreateResourcesPayload $payload;
    private InternalApiClient $apiClient;
    private RequestLogger $logger;

    public function __construct(
        InternalApiClient $apiClient,
        CreateResourcesPayload $payload,
        int $logLevel = Logger::INFO
    ) {
        $this->apiClient = $apiClient;
        $this->payload = $payload;
        $this->logger = new RequestLogger('CreateResourcesRequest', $logLevel);
    }

    /** @return ResourceModel[]|null */
    public function perform(): ?array
    {
        try {
            $json = $this->apiClient->post(
                self::PATH,
                $this->marshallPayload(),
                [InternalApiClient::INCLUDE_ORG_ID => true]
            );
        } catch (\Exception $error) {
            $this->logger->error("Error creating resources: " . $error->getMessage());
            return null;
        }

        if (is_null($json)) {
            $this->logger->warn("Unexpected null response when creating resources");
            return null;
        }

        $mapper = (new JsonMapperFactory())->bestFit();
        $response = new CreateResourcesApiResponse();

        // Resource batch creation doesn't include its member organization
        $organization = null;

        $mapper->mapObject($json, $response);

        return array_map(function (ResourceApiModel $model) use ($organization, $response): ResourceModel {
            $representations = $this->getRepresentationApiModelsByResourceId(
                $response->included,
                $model->relationships->representations->data
            );
            return new ResourceModel($model, $organization, $representations);
        }, $response->data);
    }

    /**
     * @param ResourceRepresentationApiModel[] $representations
     * @param Relationship[] $relationships
     * @return ResourceRepresentationApiModel[]
     */
    private function getRepresentationApiModelsByResourceId(array $representations, array $relationships): array
    {
        $representationIds = array_map(function (Relationship $relationship): string {
            return $relationship->id;
        }, $relationships);

        return array_filter(
            $representations,
            function (ResourceRepresentationApiModel $model) use ($representationIds): bool {
                return in_array($model->attributes->id, $representationIds);
            },
        );
    }

    /** @return mixed[] */
    private function marshallPayload(): array
    {
        return [
            'resources' => array_map(function (CreateResourcePayload $resource) {
                return [
                    'name' => $resource->name,
                    'source_uri' => $resource->source_uri,
                    'resource_type' => $resource->resource_type,
                    'resource_group_id' => $resource->resource_group_id,
                    'host_uris' => $resource->host_uris,
                    'representations' => is_null($resource->representations)
                        ? null
                        : array_map('get_object_vars', $resource->representations)
                ];
            }, $this->payload->resources)
        ];
    }
}
