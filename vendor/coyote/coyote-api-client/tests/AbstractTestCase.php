<?php
declare(strict_types=1);

namespace Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\TestCase;

class AbstractTestCase extends TestCase
{
    protected array $responses = [];
    protected Client $client;

    public function setUp(): void
    {
        $this->setResponses($this->responses);
        parent::setUp();
    }

    public function setResponses(array $responses): void
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $this->client = new Client(['handler' => $handlerStack]);
    }

    public function getApiContractJson(string $name): string
    {
        $jsonFromFile = $this->getJsonFromFile(__DIR__ . '/RawApiResponses/' . $name);

        self::assertNotNull($jsonFromFile);

        return $jsonFromFile ?? '';
    }

    /**
     * @param false $associative
     *
     * @return null|mixed
     */
    public function getApiContract(string $name, bool $associative = false)
    {
        return json_decode($this->getApiContractJson($name), $associative);
    }

    /**
     * @param string $path
     * @return null|string
     */
    public function getJsonFromFile(string $path): ?string
    {
        $contents = file_get_contents($path . '.json');

        if ($contents === false) {
            return null;
        }

        return $contents;
    }
}
