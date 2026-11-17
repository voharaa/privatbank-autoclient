<?php

namespace Voharaa\PrivatbankAutoclient\Responses\Currency;

use Voharaa\PrivatbankAutoclient\Contracts\HasRawResponse;
use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;
use Voharaa\PrivatbankAutoclient\Responses\Concerns\InteractsWithRawResponse;
use Voharaa\PrivatbankAutoclient\Responses\Concerns\ReadsCacheInfo;

/**
 * Current exchange rates returned by proxy/currency.
 *
 * The API returns every currency as a top-level key ("USD", "EUR", ...) next to "cache_info".
 */
readonly class ExchangeRates implements HasRawResponse
{
    use InteractsWithRawResponse;
    use ReadsCacheInfo;

    private const string CACHE_INFO = 'cache_info';

    /** @var array<string, CurrencyRate> Keyed by currency code, e.g. "USD" */
    public array $rates;

    /**
     * @param  array<mixed>  $data
     *
     * @throws TransportException
     */
    public function __construct(array $data)
    {
        $this->raw = $data;

        [$this->fromCache, $this->cacheTime, $this->serverTime] = self::readCacheInfo($data);

        $rates = [];

        foreach ($data as $currency => $value) {
            if ($currency === self::CACHE_INFO || ! is_array($value) || ! CurrencyRate::looksLikeRate($value)) {
                continue;
            }

            $rates[(string) $currency] = new CurrencyRate((string) $currency, $value);
        }

        if ($rates === []) {
            throw new TransportException('Unexpected response format: no currency rates found');
        }

        $this->rates = $rates;
    }

    /**
     * Rates of a single currency, or null if the API did not return it.
     */
    public function get(string $currency): ?CurrencyRate
    {
        return $this->rates[strtoupper($currency)] ?? null;
    }

    public function has(string $currency): bool
    {
        return isset($this->rates[strtoupper($currency)]);
    }

    /**
     * @return list<string>
     */
    public function currencies(): array
    {
        return array_keys($this->rates);
    }
}
