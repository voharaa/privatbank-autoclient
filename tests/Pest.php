<?php

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Voharaa\PrivatbankAutoclient\Transporters\HttpTransporter;

/**
 * Decoded JSON fixture from tests/Fixtures (copied from the API docs).
 */
function apiFixture(string $name): array
{
    return json_decode(file_get_contents(__DIR__."/Fixtures/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * The first item of a list fixture, e.g. firstItem('balances') → one balance row.
 */
function firstItem(string $name): array
{
    return apiFixture($name)[$name][0];
}

function jsonResponse(array $body, int $status = 200): Response
{
    return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
}

/**
 * HttpTransporter backed by Guzzle's MockHandler. Sent requests are collected into $history.
 *
 * @param  list<Response|Throwable>  $responses
 */
function mockTransporter(array $responses, ?array &$history = null, string $token = 'test-token'): HttpTransporter
{
    $history = [];
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    return new HttpTransporter(
        new Guzzle(['handler' => $stack, 'http_errors' => false]),
        new HttpFactory,
        $token,
    );
}
