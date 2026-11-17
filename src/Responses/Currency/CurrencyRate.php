<?php

namespace Voharaa\PrivatbankAutoclient\Responses\Currency;

use Voharaa\PrivatbankAutoclient\Contracts\HasRawResponse;
use Voharaa\PrivatbankAutoclient\Enums\CurrencyRateType;
use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;
use Voharaa\PrivatbankAutoclient\Responses\Concerns\InteractsWithRawResponse;
use Voharaa\PrivatbankAutoclient\Support\Payload;

/**
 * Buy and sell rates of a single currency.
 */
readonly class CurrencyRate implements HasRawResponse
{
    use InteractsWithRawResponse;

    /** Currency code, e.g. "USD". */
    public string $currency;

    /** The rate at which the bank buys the currency. Null if the API did not return it. */
    public ?Rate $buy;

    /** The rate at which the bank sells the currency. Null if the API did not return it. */
    public ?Rate $sell;

    /**
     * @param  array<mixed>  $data
     *
     * @throws TransportException
     */
    public function __construct(string $currency, array $data)
    {
        $this->raw = $data;
        $this->currency = $currency;
        $this->buy = self::read($data, CurrencyRateType::Buy);
        $this->sell = self::read($data, CurrencyRateType::Sell);
    }

    /**
     * The rate of the given type, e.g. rate(CurrencyRateType::Buy). Null if the API did not return it.
     */
    public function rate(CurrencyRateType $type): ?Rate
    {
        return match ($type) {
            CurrencyRateType::Buy => $this->buy,
            CurrencyRateType::Sell => $this->sell,
        };
    }

    /**
     * @param  array<mixed>  $data
     *
     * @internal
     */
    public static function looksLikeRate(array $data): bool
    {
        foreach (CurrencyRateType::cases() as $type) {
            if (isset($data[$type->value])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>  $data
     *
     * @throws TransportException
     */
    private static function read(array $data, CurrencyRateType $type): ?Rate
    {
        return isset($data[$type->value])
            ? new Rate($type, Payload::array($data, $type->value))
            : null;
    }
}
