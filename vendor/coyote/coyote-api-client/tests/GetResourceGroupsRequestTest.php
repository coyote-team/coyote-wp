<?php

namespace Tests;

use Coyote\InternalApiClient;
use Coyote\Model\ProfileModel;
use Coyote\Model\ResourceGroupModel;
use Coyote\Request\GetProfileRequest;
use Coyote\Request\GetResourceGroupsRequest;
use GuzzleHttp\Psr7\Response;
use stdClass;

/**
 * @covers \Coyote\Request\GetResourceGroupsRequest
 */
class GetResourceGroupsRequestTest extends AbstractTestCase
{
    private stdClass $contract;

    public function setUp(): void
    {
        $this->responses = [
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                $this->getApiContractJson('getValidResourceGroups')
            )
        ];

        $this->contract = $this->getApiContract('getValidResourceGroups');

        parent::setUp();
    }

    /** @return ResourceGroupModel[]|null */
    private function doRequest(?array $responses = null): ?array
    {
        if (!is_null($responses)) {
            $this->setResponses($responses);
        }

        $client = new InternalApiClient('', '', null, $this->client);
        return (new GetResourceGroupsRequest($client))->data();
    }


    public function testInvalidResponseMapsToNull(): void
    {
        $response = $this->doRequest([new Response(404)]);
        $this->assertNull($response);
    }

    public function testValidResponseMapsToResourceGroupsModels(): void
    {
        $response = $this->doRequest();
        $this->assertNotNull($response);
        $this->assertIsArray($response);
        $this->assertCount(count($this->contract->data), $response);

        foreach ($response as $model) {
            $this->assertInstanceOf(ResourceGroupModel::class, $model);
        }
    }

    public function testGroupNameIsAvailable(): void
    {
        $group = $this->doRequest()[0];
        $this->assertEquals($group->getName(), $this->contract->data[0]->attributes->name);
    }

    public function testGroupIdIsAvailable(): void
    {
        $group = $this->doRequest()[0];
        $this->assertEquals($group->getId(), $this->contract->data[0]->id);
    }

    public function testGroupUriIsAvailable(): void
    {
        $group = $this->doRequest()[0];
        $this->assertEquals($group->getUri(), $this->contract->data[0]->attributes->webhook_uri);
    }

    public function testGroupDefaultSettingIsAvailable(): void
    {
        $group = $this->doRequest()[0];
        $this->assertEquals($group->isDefault(), $this->contract->data[0]->attributes->default);
    }
}
