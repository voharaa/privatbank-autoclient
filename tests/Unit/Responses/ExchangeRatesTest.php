<?php

use Voharaa\PrivatbankAutoclient\Contracts\HasRawResponse;
use Voharaa\PrivatbankAutoclient\Enums\CurrencyRateType;
use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;
use Voharaa\PrivatbankAutoclient\Responses\Currency\CurrencyRate;
use Voharaa\PrivatbankAutoclient\Responses\Currency\ExchangeRates;
use Voharaa\PrivatbankAutoclient\Responses\Currency\Rate;

it('maps cache info', function () {
    $rates = new ExchangeRates(apiFixture('currency'));

    expect($rates->fromCache)->toBeTrue()
        ->and($rates->cacheTime)->toBe(1526645093874)
        ->and($rates->serverTime)->toBe(1526645134001);
});

it('treats cache_time 0 as not cached', function () {
    $rates = new ExchangeRates(['cache_info' => ['from_cache' => false, 'cache_time' => 0, 'server_time' => 1794949205330]] + apiFixture('currency'));

    expect($rates->fromCache)->toBeFalse()
        ->and($rates->cacheTime)->toBeNull()
        ->and($rates->serverTime)->toBe(1794949205330);
});

it('maps every currency with its buy and sell rates', function () {
    $rates = new ExchangeRates(apiFixture('currency'));

    expect($rates->currencies())->toBe(['USD', 'EUR'])
        ->and($rates->rates)->each->toBeInstanceOf(CurrencyRate::class);

    $usd = $rates->get('usd');

    expect($usd->currency)->toBe('USD')
        ->and($usd->buy)->toBeInstanceOf(Rate::class)
        ->and($usd->buy->type)->toBe(CurrencyRateType::Buy)
        ->and($usd->sell->type)->toBe(CurrencyRateType::Sell)
        ->and($usd->buy->date)->toBe('18-05-2018 14:00:01')
        ->and($usd->buy->rate)->toBe('26.0700000')
        ->and($usd->buy->delta)->toBe('-0.0600000')
        ->and($usd->buy->nbuRate)->toBe('26.1888140')
        ->and($usd->sell->rate)->toBe('26.3200000')
        ->and($rates->get('EUR')->sell->rate)->toBe('31.3000000');
});

it('returns a rate by its type', function () {
    $usd = (new ExchangeRates(apiFixture('currency')))->get('USD');

    expect($usd->rate(CurrencyRateType::Buy))->toBe($usd->buy)
        ->and($usd->rate(CurrencyRateType::Sell))->toBe($usd->sell);
});

it('returns null for a currency the API did not return', function () {
    $rates = new ExchangeRates(apiFixture('currency'));

    expect($rates->get('GBP'))->toBeNull()
        ->and($rates->has('GBP'))->toBeFalse()
        ->and($rates->has('eur'))->toBeTrue();
});

it('ignores non-currency top-level fields', function () {
    $rates = new ExchangeRates(['status' => 'SUCCESS', 'meta' => ['x' => 1]] + apiFixture('currency'));

    expect($rates->currencies())->toBe(['USD', 'EUR']);
});

it('tolerates a missing cache_info and a missing side of the rate', function () {
    $rates = new ExchangeRates(['USD' => ['B' => ['date' => '18-05-2018 14:00:01', 'rate' => '26.07']]]);

    expect($rates->fromCache)->toBeNull()
        ->and($rates->cacheTime)->toBeNull()
        ->and($rates->get('USD')->sell)->toBeNull()
        ->and($rates->get('USD')->buy->delta)->toBeNull()
        ->and($rates->get('USD')->buy->nbuRate)->toBeNull();
});

it('keeps the raw data', function () {
    $data = apiFixture('currency');
    $rates = new ExchangeRates($data);

    expect($rates)->toBeInstanceOf(HasRawResponse::class)
        ->and($rates->raw())->toBe($data)
        ->and($rates->get('USD')->raw())->toBe($data['USD'])
        ->and($rates->get('USD')->buy->raw())->toBe($data['USD']['B']);
});

it('throws TransportException when the response has no currencies', function (array $data) {
    new ExchangeRates($data);
})->throws(TransportException::class, 'no currency rates found')->with([
    'empty' => [[]],
    'cache info only' => [['cache_info' => ['from_cache' => false, 'cache_time' => 0, 'server_time' => 1]]],
    'history format' => [apiFixture('currency-history')],
]);

it('throws TransportException when a rate has no value', function () {
    new ExchangeRates(['USD' => ['B' => ['date' => '18-05-2018 14:00:01']]]);
})->throws(TransportException::class, 'missing field "rate"');
