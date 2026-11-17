<?php

use Voharaa\PrivatbankAutoclient\Client;
use Voharaa\PrivatbankAutoclient\Responses\Currency\ExchangeRates;
use Voharaa\PrivatbankAutoclient\Tests\Fakes\FakeTransporter;

beforeEach(function () {
    $this->transporter = new FakeTransporter([
        'proxy/currency/' => apiFixture('currency'),
        'proxy/currency/history' => apiFixture('currency'),
    ]);

    $this->currency = (new Client($this->transporter))->currency();
});

it('fetches current rates', function () {
    expect($this->currency->rates())->toBeInstanceOf(ExchangeRates::class)
        ->and($this->transporter->lastCall())->toBe([
            'method' => 'get', 'path' => 'proxy/currency/', 'key' => null, 'query' => [],
        ]);
});

it('fetches the history with the period in d-m-Y format', function () {
    $rates = $this->currency->history(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-15'));

    expect($rates)->toBeInstanceOf(ExchangeRates::class)
        ->and($this->transporter->lastCall())->toBe([
            'method' => 'get',
            'path' => 'proxy/currency/history',
            'key' => null,
            'query' => ['startDate' => '01-11-2026', 'endDate' => '15-11-2026'],
        ]);
});

it('allows a single-day history period', function () {
    $day = new DateTimeImmutable('2026-11-01 18:00');

    expect($this->currency->history($day, $day))->toBeInstanceOf(ExchangeRates::class);
});

it('rejects a history period longer than 15 days without sending a request', function () {
    $this->currency->history(new DateTimeImmutable('2026-11-01 23:00'), new DateTimeImmutable('2026-11-16 00:30'));
})->throws(InvalidArgumentException::class, 'must not be longer than 15 days, 16 days given');

it('rejects an end date before the start date', function () {
    $this->currency->history(new DateTimeImmutable('2026-11-02'), new DateTimeImmutable('2026-11-01'));
})->throws(InvalidArgumentException::class, 'must not be before the start date');

it('sends no request for an invalid period', function () {
    try {
        $this->currency->history(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-12-01'));
    } catch (InvalidArgumentException) {
    }

    expect($this->transporter->calls)->toBeEmpty();
});
