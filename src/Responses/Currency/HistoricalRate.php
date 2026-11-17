<?php

namespace Voharaa\PrivatbankAutoclient\Responses\Currency;

use Voharaa\PrivatbankAutoclient\Contracts\HasRawResponse;
use Voharaa\PrivatbankAutoclient\Enums\CurrencyRateType;
use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;
use Voharaa\PrivatbankAutoclient\Responses\Concerns\InteractsWithRawResponse;
use Voharaa\PrivatbankAutoclient\Support\Payload;

/**
 * Rates of a single currency for a single day (an item of "data.history" from proxy/currency/history).
 *
 * Values are kept as strings to preserve precision. Currency codes are as returned by the API,
 * which is not always ISO 4217 (e.g. "PLZ" for the Polish zloty).
 */
readonly class HistoricalRate implements HasRawResponse
{
    use InteractsWithRawResponse;

    /** Day of the rate, "d-m-Y". */
    public string $date;

    /** Currency code, e.g. "USD". */
    public string $currency;

    /** Official NBU rate. */
    public ?string $nbuRate;

    /** The rate at which the bank buys the currency. */
    public ?string $buy;

    /** Change of the buy rate. */
    public ?string $buyDelta;

    /** The rate at which the bank sells the currency. */
    public ?string $sell;

    /** Change of the sell rate. */
    public ?string $sellDelta;

    /**
     * @param  array<mixed>  $data
     *
     * @throws TransportException
     */
    public function __construct(array $data)
    {
        $this->raw = $data;
        $this->date = Payload::string($data, 'date');
        $this->currency = Payload::string($data, 'currencyCode');
        $this->nbuRate = Payload::nonBlankString($data, 'nbuRate');
        $this->buy = Payload::nonBlankString($data, 'rate_b');
        $this->buyDelta = Payload::nonBlankString($data, 'rate_b_delta');
        $this->sell = Payload::nonBlankString($data, 'rate_s');
        $this->sellDelta = Payload::nonBlankString($data, 'rate_s_delta');
    }

    /**
     * The rate of the given type, e.g. rate(CurrencyRateType::Buy).
     */
    public function rate(CurrencyRateType $type): ?string
    {
        return match ($type) {
            CurrencyRateType::Buy => $this->buy,
            CurrencyRateType::Sell => $this->sell,
        };
    }

    /**
     * Change of the rate of the given type.
     */
    public function delta(CurrencyRateType $type): ?string
    {
        return match ($type) {
            CurrencyRateType::Buy => $this->buyDelta,
            CurrencyRateType::Sell => $this->sellDelta,
        };
    }
}
