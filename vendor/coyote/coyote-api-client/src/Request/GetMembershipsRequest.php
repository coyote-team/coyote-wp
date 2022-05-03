<?php

namespace Coyote\Request;

use Coyote\ApiModel\MembershipApiModel;
use Coyote\ApiResponse\GetMembershipsApiResponse;
use Coyote\InternalApiClient;
use Coyote\Model\MembershipModel;
use Coyote\RequestLogger;
use JsonMapper\JsonMapperFactory;
use Monolog\Logger;
use stdClass;

class GetMembershipsRequest
{
    private const PATH = '/memberships/';

    private InternalApiClient $client;
    private RequestLogger $logger;

    public function __construct(InternalApiClient $client, int $logLevel = Logger::INFO)
    {
        $this->client = $client;
        $this->logger = new RequestLogger('GetMembershipsRequest', $logLevel);
    }

    /** @return MembershipModel[]|null */
    public function data(): ?array
    {
        $this->logger->debug('Fetching memberships');

        try {
            $json = $this->client->get(self::PATH, [InternalApiClient::INCLUDE_ORG_ID => true]);
        } catch (\Exception $error) {
            $this->logger->error('Error fetching memberships: ' . $error->getMessage());
            return null;
        }

        if (is_null($json)) {
            $this->logger->warn('Unexpected null response when fetching memberships');
            return null;
        }

        return $this->mapResponseToMembershipModels($json);
    }

    /** @return MembershipModel[] */
    private function mapResponseToMembershipModels(stdClass $json): array
    {
        $mapper = (new JsonMapperFactory())->bestFit();
        $response = new GetMembershipsApiResponse();
        $mapper->mapObject($json, $response);

        $memberships = $response->data;

        return array_map(function (MembershipApiModel $model): MembershipModel {
            return new MembershipModel($model);
        }, $memberships);
    }
}
