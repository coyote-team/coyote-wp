<?php

namespace Coyote;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use stdClass;

class InternalApiClient
{
    public const INCLUDE_ORG_ID = 'includeOrganizationId';

    private const METHOD_GET = 'GET';
    private const METHOD_POST = 'POST';
    private const METHOD_PUT = 'PUT';

    private Client $client;

    private string $endpoint;
    private string $token;
    private ?int $organizationId;
    private string $locale = 'en';

    public function __construct(string $endpoint, string $token, ?int $organizationId, Client $client = null)
    {
        $this->endpoint = $endpoint;
        $this->token = $token;
        $this->organizationId = $organizationId;

        $this->client = $client ?? new Client();
    }

    /**
     * @param string $url
     * @param array $options
     *
     * @return null|stdClass
     * @throws Exception
     */
    public function get(string $url, array $options = []): ?stdClass
    {
        $includeOrganizationId = array_key_exists(self::INCLUDE_ORG_ID, $options)
            ? $options[self::INCLUDE_ORG_ID]
            : false;

        return self::request(
            $this->makeUrl($url, $includeOrganizationId),
            array_merge($options, ['method' => self::METHOD_GET])
        );
    }

    /**
     * @param string $url
     * @param array $payload
     * @param array $options
     *
     * @return null|stdClass
     * @throws Exception
     */
    public function post(string $url, array $payload, array $options = []): ?stdClass
    {
        return self::request(
            $this->makeUrl($url),
            array_merge($options, ['method' => self::METHOD_POST], ['json' => $payload])
        );
    }

    /**
     * @param string $url
     * @param array $payload
     * @param array $options
     *
     * @return null|stdClass
     * @throws Exception
     */
    public function put(string $url, array $payload, array $options = []): ?stdClass
    {
        return self::request(
            $this->makeUrl($url),
            array_merge($options, ['method' => self::METHOD_PUT], ['json' => $payload])
        );
    }

    private function makeUrl(string $part, bool $includeOrganizationId = false): string
    {
        return $includeOrganizationId
            ? sprintf('%s/organizations/%d/%s', $this->endpoint, $this->organizationId, $part)
            : sprintf('%s/%s', $this->endpoint, $part);
    }

    /**
     * @param string $url
     * @param array $options
     *
     * @return null|stdClass
     * @throws Exception
     */
    private function request(string $url, array $options = []): ?stdClass
    {
        $options = array_merge(
            $options,
            ['headers' => $this->getRequestHeaders()],
            ['http_errors' => false],
        );

        try {
            switch ($options['method']) {
                case self::METHOD_GET:
                    $response = $this->client->get($url, $options);
                    break;

                case self::METHOD_POST:
                    $response = $this->client->post($url, $options);
                    break;

                case self::METHOD_PUT:
                    $response = $this->client->put($url, $options);
                    break;

                default:
                    throw new Exception("Invalid request method {$options['method']}");
            }
        } catch (GuzzleException $e) {
            throw new Exception("Guzzle exception - {$e->getMessage()}");
        }

        $body = (string) $response->getBody();

        if ($this->isResponseOk($response)) {
            $decoded = json_decode($body);

            if (is_object($decoded) && is_a($decoded, stdClass::class)) {
                return $decoded;
            }

            return null;
        }

        $status = $response->getStatusCode();

        throw new Exception("Invalid Coyote API response for $url, status $status");
    }

    /** @return array */
    private function getRequestHeaders(): array
    {
        return [
            'Authorization' => $this->token,
            'Accept-Language' => $this->locale,
            'Content-Type' => 'application/json',
        ];
    }

    private function isResponseOk(ResponseInterface $response): bool
    {
        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 400;
    }
}
