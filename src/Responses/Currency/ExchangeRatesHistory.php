<?php

namespace Voharaa\PrivatbankAutoclient\Responses\Currency;

use DateTimeImmutable;
use DateTimeInterface;
use Voharaa\PrivatbankAutoclient\Contracts\HasRawResponse;
use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;
use Voharaa\PrivatbankAutoclient\Responses\Concerns\InteractsWithRawResponse;
use Voharaa\PrivatbankAutoclient\Responses\Concerns\ReadsCacheInfo;
use Voharaa\PrivatbankAutoclient\Support\Payload;

/**
 * Exchange rates history returned by proxy/currency/history.
 *
 * The API returns one row per currency per day, in no particular order, and skips days
 * without rates (e.g. weekends). $rates is sorted by date, oldest first.
 */
readonly class ExchangeRatesHistory implements HasRawResponse
{
    use InteractsWithRawResponse;
    use ReadsCacheInfo;

    private const string DATE_FORMAT = 'd-m-Y';

    /** @var list<HistoricalRate> */
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

        foreach (Payload::array(Payload::array($data, 'data'), 'history') as $row) {
            if (! is_array($row)) {
                throw new TransportException('Unexpected response format: "history" must be a list of objects');
            }

            $rates[] = new HistoricalRate($row);
        }

        usort($rates, fn (HistoricalRate $a, HistoricalRate $b) => self::sortKey($a) <=> self::sortKey($b));

        $this->rates = $rates;
    }

    /**
     * All days of a single currency, oldest first.
     *
     * @return list<HistoricalRate>
     */
    public function forCurrency(string $currency): array
    {
        $currency = strtoupper($currency);

        return array_values(array_filter($this->rates, fn (HistoricalRate $rate) => $rate->currency === $currency));
    }

    /**
     * All currencies for a single day. A string date must be in the API format, "d-m-Y".
     *
     * @return list<HistoricalRate>
     */
    public function forDate(DateTimeInterface|string $date): array
    {
        if ($date instanceof DateTimeInterface) {
            $date = $date->format(self::DATE_FORMAT);
        }

        return array_values(array_filter($this->rates, fn (HistoricalRate $rate) => $rate->date === $date));
    }

    /**
     * @return list<string>
     */
    public function currencies(): array
    {
        return array_values(array_unique(array_map(fn (HistoricalRate $rate) => $rate->currency, $this->rates)));
    }

    /**
     * Days the history has rates for, "d-m-Y", oldest first.
     *
     * @return list<string>
     */
    public function dates(): array
    {
        return array_values(array_unique(array_map(fn (HistoricalRate $rate) => $rate->date, $this->rates)));
    }

    /**
     * "Y-m-d" for valid dates, so they sort chronologically; unparsable dates keep their order at the end.
     */
    private static function sortKey(HistoricalRate $rate): string
    {
        $date = DateTimeImmutable::createFromFormat('!'.self::DATE_FORMAT, $rate->date);

        return $date === false ? '9999-'.$rate->date : $date->format('Y-m-d');
    }
}
