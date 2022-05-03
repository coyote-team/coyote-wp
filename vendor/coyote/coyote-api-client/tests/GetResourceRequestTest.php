<?php

namespace Tests;

use Coyote\InternalApiClient;
use Coyote\Model\OrganizationModel;
use Coyote\Model\ResourceModel;
use Coyote\Request\GetResourceRequest;
use GuzzleHttp\Psr7\Response;
use stdClass;

/**
 * @covers \Coyote\Request\GetResourceRequest
 */
class GetResourceRequestTest extends AbstractTestCase
{
    private stdClass $contract;

    public function setUp(): void
    {
        $this->responses = [
            new Response(200, ['Content-Type' => 'application/json'], $this->getApiContractJson('getValidResource'))
        ];

        $this->contract = $this->getApiContract('getValidResource');

        parent::setUp();
    }

    private function doRequest(?array $responses = null): ?ResourceModel
    {
        if (!is_null($responses)) {
            $this->setResponses($responses);
        }

        $client = new InternalApiClient('', '', null, $this->client);
        return (new GetResourceRequest($client, 22780))->data();
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

    public function testResourceIdIsAvailable(): void
    {
        $response = $this->doRequest();
        $this->assertEquals(
            $response->getId(),
            $this->contract->data->id
        );
    }

    public function testResourceUriIsAvailable(): void
    {
        $response = $this->doRequest();
        $this->assertEquals(
            $response->getSourceUri(),
            $this->contract->data->attributes->source_uri
        );
    }

    public function testResourceNameIsAvailable(): void
    {
        $response = $this->doRequest();
        $this->assertEquals(
            $response->getName(),
            $this->contract->data->attributes->name
        );
    }

    public function testResourceRepresentationsAreMapped(): void
    {
        $response = $this->doRequest();
        $representations = $response->getRepresentations();
        $this->assertIsArray($representations);
        $this->assertCount(0, $representations);
    }

    public function testResourceOrganisationIsMapped(): void
    {
        $response = $this->doRequest();
        $organization = $response->getOrganization();
        $this->assertNotNull($organization);
        $this->assertInstanceOf(OrganizationModel::class, $organization);
    }
}
