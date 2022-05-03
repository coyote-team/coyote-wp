<?php

namespace Tests;

use Coyote\InternalApiClient;
use Coyote\Model\MembershipModel;
use Coyote\Request\GetMembershipsRequest;
use GuzzleHttp\Psr7\Response;
use stdClass;

/**
 * @covers \Coyote\Request\GetMembershipsRequest
 */
class GetMembershipsRequestTest extends AbstractTestCase
{
    private stdClass $contract;

    public function setUp(): void
    {
        $this->responses = [
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                $this->getApiContractJson('getValidMemberships')
            )
        ];

        $this->contract = $this->getApiContract('getValidMemberships');

        parent::setUp();
    }

    /** @return MembershipModel[]|null */
    private function doRequest(?array $responses = null): ?array
    {
        if (!is_null($responses)) {
            $this->setResponses($responses);
        }

        $client = new InternalApiClient('', '', null, $this->client);
        return (new GetMembershipsRequest($client))->data();
    }


    public function testInvalidResponseMapsToNull(): void
    {
        $response = $this->doRequest([new Response(404)]);
        $this->assertNull($response);
    }

    public function testValidResponseMapsToMembershipModels(): void
    {
        $response = $this->doRequest();
        $this->assertNotNull($response);
        $this->assertIsArray($response);
        $this->assertCount(count($this->contract->data), $response);

        foreach ($response as $model) {
            $this->assertInstanceOf(MembershipModel::class, $model);
        }
    }

    public function testNameIsAvailable(): void
    {
        $membership = $this->doRequest()[0];
        $this->assertEquals(
            $membership->getName(),
            join(' ', [
                $this->contract->data[0]->attributes->first_name,
                $this->contract->data[0]->attributes->last_name,
            ])
        );
    }

    public function testEmailIsAvailable(): void
    {
        $membership = $this->doRequest()[0];
        $this->assertEquals($membership->getEmail(), $this->contract->data[0]->attributes->email);
    }

    public function testRoleIsAvailable(): void
    {
        $membership = $this->doRequest()[0];
        $this->assertEquals($membership->getRole(), $this->contract->data[0]->attributes->role);
    }

    public function testIdIsAvailable(): void
    {
        $membership = $this->doRequest()[0];
        $this->assertEquals($membership->getId(), $this->contract->data[0]->id);
    }
}
