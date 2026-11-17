<?php

namespace Voharaa\PrivatbankAutoclient\Transporters;

use Generator;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Voharaa\PrivatbankAutoclient\Contracts\TransporterContract;
use Voharaa\PrivatbankAutoclient\Exceptions\ApiException;
use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;
use Voharaa\PrivatbankAutoclient\Support\Payload;

class HttpTransporter implements TransporterContract
{
    private const string BASE_URL = 'https://acp.privatbank.ua/api/';

    /** The API requires a User-Agent header identifying the client application. */
    private const string USER_AGENT = 'voharaa/privatbank-autoclient';

    public function __construct(
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requests,
        private readonly string $token,
    ) {}

    /**
     * @throws TransportException
     * @throws ApiException
     */
    public function get(string $path, array $query = []): array
    {
        $uri = self::BASE_URL.$path.($query ? '?'.http_build_query($query) : '');

        $request = $this->requests->createRequest('GET', $uri)
            ->withHeader('User-Agent', self::USER_AGENT)
            ->withHeader('token', $this->token)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Content-Type', 'application/json;charset=utf8');

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TransportException($e->getMessage(), previous: $e);
        }

        $statusCode = $response->getStatusCode();

        try {
            $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            if ($statusCode >= 400) {
                throw ApiException::fromResponse($statusCode, []);
            }

            throw new TransportException('Response body is not valid JSON', previous: $e);
        }

        if (! is_array($data)) {
            throw new TransportException('Response body is not a JSON object');
        }

        if ($statusCode >= 400 || ($data['status'] ?? null) !== 'SUCCESS') {
            throw ApiException::fromResponse($statusCode, $data);
        }

        return $data;
    }

    /**
     * @throws ApiException
     * @throws TransportException
     */
    public function paginate(string $path, string $key, array $query = []): Generator
    {
        $seenPageIds = [];

        while (true) {
            $page = $this->get($path, $query);

            // A page without the key (e.g. no records for the period) is treated as empty.
            $rows = isset($page[$key]) ? Payload::array($page, $key) : [];

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    throw new TransportException("Unexpected response format: \"{$key}\" must be a list of objects");
                }

                yield $row;
            }

            if (! filter_var($page['exist_next_page'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                return;
            }

            $nextPageId = $page['next_page_id'] ?? null;

            if (! is_scalar($nextPageId) || (string) $nextPageId === '') {
                throw new TransportException('API reported a next page but returned no next_page_id');
            }

            $nextPageId = (string) $nextPageId;

            if (isset($seenPageIds[$nextPageId])) {
                throw new TransportException("API returned next_page_id \"{$nextPageId}\" twice, stopping to avoid an infinite loop");
            }

            $seenPageIds[$nextPageId] = true;
            $query['followId'] = $nextPageId;
        }
    }
}
