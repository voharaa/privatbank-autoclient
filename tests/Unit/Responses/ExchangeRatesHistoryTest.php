<?php

use Voharaa\PrivatbankAutoclient\Contracts\HasRawResponse;
use Voharaa\PrivatbankAutoclient\Enums\CurrencyRateType;
use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;
use Voharaa\PrivatbankAutoclient\Responses\Currency\ExchangeRatesHistory;
use Voharaa\PrivatbankAutoclient\Responses\Currency\HistoricalRate;

it('maps every row of data.history', function () {
    $history = new ExchangeRatesHistory(apiFixture('currency-history'));

    expect($history->rates)->toHaveCount(9)->each->toBeInstanceOf(HistoricalRate::class);
});

it('maps a history row', function () {
    $rate = new HistoricalRate(apiFixture('currency-history')['data']['history'][1]);

    expect($rate->date)->toBe('13-11-2026')
        ->and($rate->currency)->toBe('USD')
        ->and($rate->nbuRate)->toBe('45.3849000')
        ->and($rate->buy)->toBe('45.2300000')
        ->and($rate->buyDelta)->toBe('-0.0200000')
        ->and($rate->sell)->toBe('45.6300000')
        ->and($rate->sellDelta)->toBe('-0.0200000')
        ->and($rate->rate(CurrencyRateType::Buy))->toBe('45.2300000')
        ->and($rate->rate(CurrencyRateType::Sell))->toBe('45.6300000')
        ->and($rate->delta(CurrencyRateType::Sell))->toBe('-0.0200000');
});

it('sorts rows by date, oldest first, keeping the API order within a day', function () {
    $history = new ExchangeRatesHistory(apiFixture('currency-history'));

    expect($history->dates())->toBe(['11-11-2026', '13-11-2026', '17-11-2026'])
        ->and(array_map(fn ($r) => $r->currency, array_slice($history->rates, 0, 3)))->toBe(['EUR', 'USD', 'PLZ']);
});

it('filters rows by currency', function () {
    $usd = (new ExchangeRatesHistory(apiFixture('currency-history')))->forCurrency('usd');

    expect($usd)->toHaveCount(3)
        ->and(array_map(fn ($r) => $r->date, $usd))->toBe(['11-11-2026', '13-11-2026', '17-11-2026'])
        ->and(array_map(fn ($r) => $r->sell, $usd))->toBe(['45.5500000', '45.6300000', '45.5500000']);
});

it('filters rows by date', function (DateTimeInterface|string $date) {
    $rows = (new ExchangeRatesHistory(apiFixture('currency-history')))->forDate($date);

    expect(array_map(fn ($r) => $r->currency, $rows))->toBe(['EUR', 'USD', 'PLZ'])
        ->and(array_map(fn ($r) => $r->date, $rows))->each->toBe('13-11-2026');
})->with([
    'DateTimeImmutable' => [new DateTimeImmutable('2026-11-13 18:45')],
    'string' => ['13-11-2026'],
]);

it('returns no rows for a day without rates', function () {
    expect((new ExchangeRatesHistory(apiFixture('currency-history')))->forDate('14-11-2026'))->toBe([]);
});

it('lists currencies as returned by the API', function () {
    expect((new ExchangeRatesHistory(apiFixture('currency-history')))->currencies())->toBe(['EUR', 'USD', 'PLZ']);
});

it('treats cache_time 0 as not cached', function () {
    $history = new ExchangeRatesHistory(apiFixture('currency-history'));

    expect($history->fromCache)->toBeFalse()
        ->and($history->cacheTime)->toBeNull()
        ->and($history->serverTime)->toBe(1794949201268);
});

it('keeps the raw data', function () {
    $data = apiFixture('currency-history');
    $history = new ExchangeRatesHistory($data);

    expect($history)->toBeInstanceOf(HasRawResponse::class)
        ->and($history->raw())->toBe($data)
        ->and($history->rates[0]->raw())->toBe($data['data']['history'][3]);
});

it('returns an empty history when there are no rows', function () {
    $history = new ExchangeRatesHistory(['data' => ['history' => []]]);

    expect($history->rates)->toBe([])
        ->and($history->currencies())->toBe([])
        ->and($history->dates())->toBe([]);
});

it('throws TransportException when data.history is missing', function () {
    new ExchangeRatesHistory(['data' => ['sessionState' => null]]);
})->throws(TransportException::class, 'field "history" is not an object or a list');

it('throws TransportException when a row has no currency code', function () {
    new ExchangeRatesHistory(['data' => ['history' => [['date' => '11-11-2026']]]]);
})->throws(TransportException::class, 'missing field "currencyCode"');
