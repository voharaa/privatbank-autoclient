<?php

namespace Voharaa\PrivatbankAutoclient\Tests\Fakes;

use Generator;
use Voharaa\PrivatbankAutoclient\Contracts\TransporterContract;

/**
 * In-memory transporter: returns canned responses by path and records every call.
 */
final class FakeTransporter implements TransporterContract
{
    /** @var list<array{method: string, path: string, key: ?string, query: array}> */
    public array $calls = [];

    /**
     * @param  array<string, array>  $responses  Response bodies keyed by path
     */
    public function __construct(private array $responses = []) {}

    public function respondWith(string $path, array $response): self
    {
        $this->responses[$path] = $response;

        return $this;
    }

    public function get(string $path, array $query = []): array
    {
        $this->calls[] = ['method' => 'get', 'path' => $path, 'key' => null, 'query' => $query];

        return $this->responses[$path] ?? throw new \LogicException("No fake response for \"{$path}\"");
    }

    public function paginate(string $path, string $key, array $query = []): Generator
    {
        $this->calls[] = ['method' => 'paginate', 'path' => $path, 'key' => $key, 'query' => $query];

        $response = $this->responses[$path] ?? throw new \LogicException("No fake response for \"{$path}\"");

        yield from $response[$key] ?? [];
    }

    public function lastCall(): array
    {
        return $this->calls[array_key_last($this->calls)] ?? throw new \LogicException('No calls were made');
    }
}
