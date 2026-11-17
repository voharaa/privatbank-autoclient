<?php

namespace Voharaa\PrivatbankAutoclient\Contracts;

use Generator;

interface TransporterContract
{
    /**
     * Sends a GET request and returns the decoded body.
     *
     * @param  array<string, int|string>  $query
     * @return array<mixed>
     */
    public function get(string $path, array $query = []): array;

    /**
     * Walks all pages (followId) and yields every item of the $key list.
     *
     * @param  array<string, int|string>  $query
     * @return Generator<int, array<mixed>>
     */
    public function paginate(string $path, string $key, array $query = []): Generator;
}
