<?php

namespace Tests;

use Coyote\InternalApiClient;
use Coyote\Model\ResourceModel;
use Coyote\Payload\CreateResourcePayload;
use Coyote\Payload\CreateResourcesPayload;
use Coyote\Request\CreateResourcesRequest;
use GuzzleHttp\Psr7\Response;
use stdClass;

/**
 * @covers \Coyote\Request\CreateResourcesRequest
 */
class CreateResourcesRequestTest extends AbstractTestCase
{
    private stdClass $contract;

    public function setUp(): void
    {
        $this->responses = [
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                $this->getApiContractJson('createValidResources')
            )
        ];

        $this->contract = $this->getApiContract('createValidResources');

        parent::setUp();
    }

    /**
     * @param array|null $responses
     * @return ResourceModel[]|null
     */
    private function doRequest(array $responses = null, string $uri = 'https://resources-r-us.net/api'): ?array
    {
        if (!is_null($responses)) {
            $this->setResponses($responses);
        }

        $client = new InternalApiClient('', '', null, $this->client);
        $createResourcePayload = new CreateResourcePayload('Testing Resource Group', $uri);
        $payload = new CreateResourcesPayload();
        $payload->addResource($createResourcePayload);

        return (new CreateResourcesRequest($client, $payload))->perform();
    }

    public function testInvalidResponseMapsToNull(): void
    {
        $response = $this->doRequest([new Response(404)]);
        $this->assertNull($response);
    }

    public function testValidResponseMapsToArrayOfResourceModel(): void
    {
        $response = $this->doRequest();
        $this->assertNotNull($response);
        $this->assertIsArray($response);
        $this->assertInstanceOf(ResourceModel::class, $response[0]);
    }

    public function testResourceNameIsCorrect(): void
    {
        $resources = $this->doRequest();
        $this->assertEquals($resources[0]->getName(), $this->contract->data[0]->attributes->name);
    }

    public function testResourceContainsRepresentations(): void
    {
        $resources = $this->doRequest();
        $resource = $resources[0];
        $this->assertIsArray($resource->getRepresentations());
        $this->assertCount(1, $resource->getRepresentations());
    }

    public function testResourceContainsNoOrganization(): void
    {
        $resources = $this->doRequest();
        $resource = $resources[0];
        $this->assertNull($resource->getOrganization());
    }
}
