<?php

namespace Tests;

use Coyote\InternalApiClient;
use Coyote\Model\OrganizationModel;
use Coyote\Model\ResourceModel;
use Coyote\Request\GetResourceRequest;
use Coyote\Request\GetResourcesRequest;
use GuzzleHttp\Psr7\Response;
use stdClass;

/**
 * @covers \Coyote\Request\GetResourcesRequest
 */
class GetResourcesRequestTest extends AbstractTestCase
{
    private stdClass $contract;

    public function setUp(): void
    {
        $this->responses = [
            new Response(200, ['Content-Type' => 'application/json'], $this->getApiContractJson('getValidResources'))
        ];

        $this->contract = $this->getApiContract('getValidResources');

        parent::setUp();
    }

    /** @return ResourceModel[]|null */
    private function doRequest(?array $responses = null): ?array
    {
        if (!is_null($responses)) {
            $this->setResponses($responses);
        }

        $client = new InternalApiClient('', '', 1, $this->client);
        return (new GetResourcesRequest($client))->data();
    }

    public function testInvalidResponseMapsToNull(): void
    {
        $response = $this->doRequest([new Response(404)]);
        $this->assertNull($response);
    }

    public function testValidResponseMapsToResourcesList(): void
    {
        $response = $this->doRequest();
        $this->assertIsArray($response);
        $this->assertInstanceOf(ResourceModel::class, $response[0]);

        $this->assertCount(count($this->contract->data), $response);

        foreach ($response as $model) {
            $this->assertInstanceOf(ResourceModel::class, $model);
        }
    }

    public function testResourceIdIsAvailable(): void
    {
        $resource = $this->doRequest()[0];
        $this->assertEquals(
            $resource->getId(),
            $this->contract->data[0]->id
        );
    }
}
