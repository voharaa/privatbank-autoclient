<?php

namespace Voharaa\PrivatbankAutoclient\Responses\Currency;

use Voharaa\PrivatbankAutoclient\Contracts\HasRawResponse;
use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;
use Voharaa\PrivatbankAutoclient\Responses\Concerns\InteractsWithRawResponse;
use Voharaa\PrivatbankAutoclient\Support\Payload;

/**
 * Exchange rates returned by proxy/currency and proxy/currency/history.
 *
 * The API returns every currency as a top-level key ("USD", "EUR", ...) next to "cache_info".
 */
readonly class ExchangeRates implements HasRawResponse
{
    use InteractsWithRawResponse;

    private const string CACHE_INFO = 'cache_info';

    /** Whether the API served the data from its cache. Null if the API did not report it. */
    public ?bool $fromCache;

    /** When the data was cached, Unix time in milliseconds. */
    public ?int $cacheTime;

    /** Current server time, Unix time in milliseconds. */
    public ?int $serverTime;

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

        $cacheInfo = isset($data[self::CACHE_INFO]) ? Payload::array($data, self::CACHE_INFO) : [];
        $this->fromCache = array_key_exists('from_cache', $cacheInfo) ? Payload::bool($cacheInfo, 'from_cache') : null;
        $this->cacheTime = Payload::optionalInt($cacheInfo, 'cache_time');
        $this->serverTime = Payload::optionalInt($cacheInfo, 'server_time');

        $rates = [];

        foreach ($data as $currency => $value) {
            if ($currency === self::CACHE_INFO || ! is_array($value) || ! CurrencyRate::looksLikeRate($value)) {
                continue;
            }

            $rates[(string) $currency] = new CurrencyRate((string) $currency, $value);
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
