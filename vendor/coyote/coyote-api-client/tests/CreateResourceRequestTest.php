<?php

namespace Tests;

use Coyote\InternalApiClient;
use Coyote\Model\OrganizationModel;
use Coyote\Model\ResourceModel;
use Coyote\Payload\CreateResourcePayload;
use Coyote\Request\CreateResourceRequest;
use GuzzleHttp\Psr7\Response;
use stdClass;

/**
 * @covers \Coyote\Request\CreateResourceRequest
 */
class CreateResourceRequestTest extends AbstractTestCase
{
    private stdClass $contract;

    public function setUp(): void
    {
        $this->responses = [
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                $this->getApiContractJson('createValidResource')
            )
        ];

        $this->contract = $this->getApiContract('createValidResource');

        parent::setUp();
    }

    /**
     * @param array|null $responses
     * @return ResourceModel|null
     */
    private function doRequest(array $responses = null, string $uri = 'https://resources-r-us.net/api'): ?ResourceModel
    {
        if (!is_null($responses)) {
            $this->setResponses($responses);
        }

        $client = new InternalApiClient('', '', null, $this->client);
        $payload = new CreateResourcePayload('Testing Resource Group', $uri);

        return (new CreateResourceRequest($client, $payload))->perform();
    }

    public function testInvalidResponseMapsToNull(): void
    {
        $response = $this->doRequest([new Response(404)]);
        $this->assertNull($response);
    }

    public function testValidResponseMapsToResourceModel(): void
    {
        $response = $this->doRequest();
        $this->assertNotNull($response);
        $this->assertInstanceOf(ResourceModel::class, $response);
    }

    public function testResourceNameIsCorrect(): void
    {
        $resource = $this->doRequest();
        $this->assertEquals($resource->getName(), $this->contract->data->attributes->name);
    }

    public function testResourceContainsRepresentations(): void
    {
        $resource = $this->doRequest();
        $this->assertIsArray($resource->getRepresentations());
        $this->assertCount(0, $resource->getRepresentations());
    }

    public function testResourceContainsOrganization(): void
    {
        $resource = $this->doRequest();
        $this->assertInstanceOf(OrganizationModel::class, $resource->getOrganization());
    }

    public function testResourceContainsOrganizationWithCorrectId(): void
    {
        $resource = $this->doRequest();
        $this->assertEquals($resource->getOrganization()->getId(), $this->contract->included[0]->id);
    }

}
