<?php

namespace Coyote\Request;

use Coyote\ApiResponse\GetResourceRepresentationsApiResponse;
use Coyote\InternalApiClient;
use Coyote\Model\RepresentationModel;
use Coyote\RequestLogger;
use JsonMapper\JsonMapperFactory;
use Monolog\Logger;
use stdClass;

class GetResourceRepresentationsRequest
{
    private const PATH = '/resources/%s/representations';

    private InternalApiClient $client;
    private RequestLogger $logger;
    private string $resource_id;

    public function __construct(InternalApiClient $client, string $resource_id, int $logLevel = Logger::INFO)
    {
        $this->client = $client;
        $this->resource_id = $resource_id;
        $this->logger = new RequestLogger('GetRepresentationsRequest', $logLevel);
    }

    /** @return RepresentationModel[]|null */
    public function data(): ?array
    {
        $this->logger->debug("Fetching representations for {$this->resource_id}");

        try {
            $json = $this->client->get(sprintf(self::PATH, $this->resource_id));
        } catch (\Exception $error) {
            $this->logger->error(
                "Error fetching resource {$this->resource_id} representations: " . $error->getMessage()
            );
            return null;
        }

        if (is_null($json)) {
            $this->logger->warn(
                "Unexpected null response when fetching representations for resource {$this->resource_id}"
            );
            return null;
        }

        return $this->mapResponseToRepresentationModels($json);
    }

    /** @return RepresentationModel[] */
    private function mapResponseToRepresentationModels(stdClass $json): array
    {
        $mapper = (new JsonMapperFactory())->bestFit();
        $response = new GetResourceRepresentationsApiResponse();
        $mapper->mapObject($json, $response);

        return $this->mapRepresentationApiModelsToRepresentationModels($response);
    }

    /**
     * @param GetResourceRepresentationsApiResponse $response
     * @return RepresentationModel[]
     */
    private function mapRepresentationApiModelsToRepresentationModels(
        GetResourceRepresentationsApiResponse $response
    ): array {
        return array_map(function ($apiModel) {
            return new RepresentationModel($apiModel);
        }, $response->data);
    }
}
