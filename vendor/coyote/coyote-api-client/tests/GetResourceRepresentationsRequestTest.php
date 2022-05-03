<?php

namespace Tests;

use Coyote\InternalApiClient;
use Coyote\Model\RepresentationModel;
use Coyote\Request\GetResourceRepresentationsRequest;
use GuzzleHttp\Psr7\Response;
use stdClass;

/**
 * @covers \Coyote\Request\GetResourceRepresentationsRequest
 */
class GetResourceRepresentationsRequestTest extends AbstractTestCase
{
    private stdClass $contract;

    public function setUp(): void
    {
        $this->responses = [
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                $this->getApiContractJson('getValidResourceRepresentations')
            )
        ];

        $this->contract = $this->getApiContract('getValidResourceRepresentations');

        parent::setUp();
    }

    /** @return RepresentationModel[]|null */
    private function doRequest(?array $responses = null): ?array
    {
        if (!is_null($responses)) {
            $this->setResponses($responses);
        }

        $client = new InternalApiClient('', '', null, $this->client);
        return (new GetResourceRepresentationsRequest($client, 12345))->data();
    }

    public function testInvalidResponseMapsToNull(): void
    {
        $response = $this->doRequest([new Response(404)]);
        $this->assertNull($response);
    }

    public function testValidResponseMapsToRepresentationModels(): void
    {
        $response = $this->doRequest();
        $this->assertNotNull($response);

        /** @var RepresentationModel $model */
        foreach ($response as $model) {
            $this->assertInstanceOf(RepresentationModel::class, $model);
        }
    }

    public function testRepresentationIdIsAvailable(): void
    {
        $representation = $this->doRequest()[0];
        $this->assertEquals($representation->getId(), $this->contract->data[0]->id);
    }

    public function testRepresentationTextIsAvailable(): void
    {
        $representation = $this->doRequest()[0];
        $this->assertEquals($representation->getText(), $this->contract->data[0]->attributes->text);
    }

    public function testRepresentationUriIsAvailable(): void
    {
        $representation = $this->doRequest()[0];
        $this->assertEquals($representation->getUri(), $this->contract->data[0]->attributes->content_uri);
    }

    public function testRepresentationLanguageIsAvailable(): void
    {
        $representation = $this->doRequest()[0];
        $this->assertEquals($representation->getLanguage(), $this->contract->data[0]->attributes->language);
    }

    public function testRepresentationStatusIsAvailable(): void
    {
        $representation = $this->doRequest()[0];
        $this->assertEquals($representation->getStatus(), $this->contract->data[0]->attributes->status);
    }

    public function testRepresentationOrdinalityIsAvailable(): void
    {
        $representation = $this->doRequest()[0];
        $this->assertEquals($representation->getOrdinality(), $this->contract->data[0]->attributes->ordinality);
    }

    public function testRepresentationMetumIsAvailable(): void
    {
        $representation = $this->doRequest()[0];
        $this->assertEquals($representation->getMetum(), $this->contract->data[0]->attributes->metum);
    }
}
