<?php

namespace Voharaa\PrivatbankAutoclient\Responses\Concerns;

use Voharaa\PrivatbankAutoclient\Contracts\HasRawResponse;

/**
 * Implements HasRawResponse. The using class assigns $raw in its constructor,
 * before reading any field, and never changes or normalizes it.
 *
 * @phpstan-require-implements HasRawResponse
 */
trait InteractsWithRawResponse
{
    /**
     * The data exactly as returned by the API, including fields not mapped to properties.
     *
     * @var array<mixed>
     */
    public readonly array $raw;

    /**
     * @return array<mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }
}
