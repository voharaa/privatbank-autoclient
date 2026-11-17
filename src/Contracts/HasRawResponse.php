<?php

namespace Voharaa\PrivatbankAutoclient\Contracts;

/**
 * A response object that keeps the data exactly as returned by the API.
 *
 * Use it to handle any response object generically, e.g. `$dto instanceof HasRawResponse`.
 * Implemented with the InteractsWithRawResponse trait.
 */
interface HasRawResponse
{
    /**
     * The data exactly as returned by the API, including fields not mapped to properties.
     *
     * @return array<mixed>
     */
    public function raw(): array;
}
