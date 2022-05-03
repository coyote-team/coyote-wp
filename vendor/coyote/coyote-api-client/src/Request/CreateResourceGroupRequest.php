<?php

namespace Coyote\Request;

use Coyote\ApiResponse\CreateResourceGroupApiResponse;
use Coyote\InternalApiClient;
use Coyote\Model\ResourceGroupModel;
use Coyote\Payload\CreateResourceGroupPayload;
use Coyote\RequestLogger;
use JsonMapper\JsonMapperFactory;
use Monolog\Logger;

class CreateResourceGroupRequest
{
    private const PATH = '/resource_groups';

    private CreateResourceGroupPayload $payload;
    private InternalApiClient $apiClient;
    private RequestLogger $logger;

    public function __construct(
        InternalApiClient $apiClient,
        CreateResourceGroupPayload $payload,
        int $logLevel = Logger::INFO
    ) {
        $this->apiClient = $apiClient;
        $this->payload = $payload;
        $this->logger = new RequestLogger('CreateResourceGroupRequest', $logLevel);
    }

    public function perform(): ?ResourceGroupModel
    {
        try {
            $json = $this->apiClient->post(
                self::PATH,
                $this->marshallPayload(),
                [InternalApiClient::INCLUDE_ORG_ID => true]
            );
        } catch (\Exception $error) {
            $this->logger->error(
                "Error creating resource group ({$this->payload->name}/{$this->payload->webhook_uri}): "
                . $error->getMessage()
            );
            return null;
        }

        if (is_null($json)) {
            $this->logger->warn(
                "Unexpected null response when creating resource group "
                . "({$this->payload->name}/{$this->payload->webhook_uri})"
            );
            return null;
        }

        $mapper = (new JsonMapperFactory())->bestFit();
        $response = new CreateResourceGroupApiResponse();
        $mapper->mapObject($json, $response);

        return $this->responseToResourceGroup($response);
    }

    private function responseToResourceGroup(CreateResourceGroupApiResponse $response): ResourceGroupModel
    {
        return new ResourceGroupModel($response->data);
    }

    /** @return mixed[] */
    private function marshallPayload(): array
    {
        return get_object_vars($this->payload);
    }
}
