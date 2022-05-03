<?php

namespace Tests;

use Coyote\InternalApiClient;
use Coyote\Model\ProfileModel;
use Coyote\Request\GetProfileRequest;
use GuzzleHttp\Psr7\Response;
use stdClass;

/**
 * @covers \Coyote\Request\GetProfileRequest
 */
class GetProfileRequestTest extends AbstractTestCase
{
    private stdClass $contract;

    public function setUp(): void
    {
        $this->responses = [
            new Response(200, ['Content-Type' => 'application/json'], $this->getApiContractJson('getValidProfile'))
        ];

        $this->contract = $this->getApiContract('getValidProfile');

        parent::setUp();
    }

    private function doRequest(?array $responses = null): ?ProfileModel
    {
        if (!is_null($responses)) {
            $this->setResponses($responses);
        }

        $client = new InternalApiClient('', '', null, $this->client);
        return (new GetProfileRequest($client))->data();
    }

    public function testInvalidResponseMapsToNull(): void
    {
        $response = $this->doRequest([new Response(404)]);
        $this->assertNull($response);
    }

    public function testValidResponseMapsToProfileModel(): void
    {
        $response = $this->doRequest();
        $this->assertNotNull($response);
        $this->assertInstanceOf(ProfileModel::class, $response);
    }

    public function testProfileIdIsAvailable(): void
    {
        $response = $this->doRequest();
        $this->assertEquals(
            $response->getId(),
            $this->contract->data->id
        );
    }

    public function testProfileNameIsAvailable(): void
    {
        $response = $this->doRequest();
        $this->assertEquals(
            $response->getName(),
            implode(' ', [
                $this->contract->data->attributes->first_name,
                $this->contract->data->attributes->last_name])
        );
    }

    public function testOrganizationsAreMapped(): void
    {
        $response = $this->doRequest();

        $this->assertIsArray($response->getOrganizations());

        // one is a memberships
        $this->assertCount(count($this->contract->included) - 1, $response->getOrganizations());

        $this->assertEquals(
            $response->getOrganizations()[0]->getName(),
            $this->contract->included[0]->attributes->name
        );
    }

    public function testMembershipsAreMapped(): void
    {
        $response = $this->doRequest();
        $memberships = $response->getMemberships();

        $this->assertIsArray($memberships);

        // expect a single membership
        $this->assertCount(1, $memberships);

        $membership = array_shift($memberships);

        $this->assertEquals(
            $membership->getEmail(),
            $this->contract->included[11]->attributes->email
        );
    }

}
