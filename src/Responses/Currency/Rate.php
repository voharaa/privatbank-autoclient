<?php

namespace Voharaa\PrivatbankAutoclient\Responses\Currency;

use Voharaa\PrivatbankAutoclient\Contracts\HasRawResponse;
use Voharaa\PrivatbankAutoclient\Enums\CurrencyRateType;
use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;
use Voharaa\PrivatbankAutoclient\Responses\Concerns\InteractsWithRawResponse;
use Voharaa\PrivatbankAutoclient\Support\Payload;

/**
 * A single buy or sell rate. Values are kept as strings to preserve precision.
 */
readonly class Rate implements HasRawResponse
{
    use InteractsWithRawResponse;

    /** Whether this is the bank's buy or sell rate. */
    public CurrencyRateType $type;

    /** Date of the rate, "d-m-Y H:i:s" (local Kyiv time). */
    public string $date;

    public string $rate;

    /** Change of the rate. */
    public ?string $delta;

    /** Official NBU rate. */
    public ?string $nbuRate;

    /**
     * @param  array<mixed>  $data
     *
     * @throws TransportException
     */
    public function __construct(CurrencyRateType $type, array $data)
    {
        $this->raw = $data;
        $this->type = $type;
        $this->date = Payload::string($data, 'date');
        $this->rate = Payload::string($data, 'rate');
        $this->delta = Payload::nonBlankString($data, 'rate_delta');
        $this->nbuRate = Payload::nonBlankString($data, 'nbuRate');
    }
}
