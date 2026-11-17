<?php

use Voharaa\PrivatbankAutoclient\Client;
use Voharaa\PrivatbankAutoclient\Responses\Statements\Balance;
use Voharaa\PrivatbankAutoclient\Responses\Statements\Settings;
use Voharaa\PrivatbankAutoclient\Responses\Statements\Transaction;
use Voharaa\PrivatbankAutoclient\Support\Cursor;
use Voharaa\PrivatbankAutoclient\Tests\Fakes\FakeTransporter;

beforeEach(function () {
    $this->transporter = new FakeTransporter([
        'statements/settings' => apiFixture('settings'),
        'statements/balance' => apiFixture('balances'),
        'statements/balance/interim' => apiFixture('balances'),
        'statements/balance/final' => apiFixture('balances'),
        'statements/transactions' => apiFixture('transactions'),
        'statements/transactions/interim' => apiFixture('transactions'),
        'statements/transactions/final' => apiFixture('transactions'),
    ]);

    $this->statements = (new Client($this->transporter))->statements();
});

it('fetches settings', function () {
    $settings = $this->statements->settings();

    expect($settings)->toBeInstanceOf(Settings::class)
        ->and($this->transporter->lastCall())->toMatchArray(['method' => 'get', 'path' => 'statements/settings']);
});

it('returns a lazy cursor of balances', function () {
    $cursor = $this->statements->balances(new DateTimeImmutable('2026-11-01'));

    expect($cursor)->toBeInstanceOf(Cursor::class)
        ->and($this->transporter->calls)->toBeEmpty()
        ->and($cursor->all())->toHaveCount(1)->each->toBeInstanceOf(Balance::class);
});

it('returns a lazy cursor of transactions', function () {
    $cursor = $this->statements->transactions(new DateTimeImmutable('2026-11-01'));

    expect($cursor)->toBeInstanceOf(Cursor::class)
        ->and($this->transporter->calls)->toBeEmpty()
        ->and($cursor->all())->toHaveCount(1)->each->toBeInstanceOf(Transaction::class);
});

it('sends the date range in d-m-Y format', function (string $method, string $path, string $key) {
    $this->statements->{$method}(
        new DateTimeImmutable('2026-11-01'),
    new DateTimeImmutable('2026-11-15'),
    'UA943052990000026100050001037',
    50,
    )->all();

    expect($this->transporter->lastCall())->toBe([
        'method' => 'paginate',
        'path' => $path,
        'key' => $key,
        'query' => [
            'acc' => 'UA943052990000026100050001037',
            'limit' => 50,
            'startDate' => '01-11-2026',
            'endDate' => '15-11-2026',
        ],
    ]);
})->with([
    'balances' => ['balances', 'statements/balance', 'balances'],
    'transactions' => ['transactions', 'statements/transactions', 'transactions'],
]);

it('omits endDate and acc when they are not given', function (string $method) {
    $this->statements->{$method}(new DateTimeImmutable('2026-11-01'))->all();

    expect($this->transporter->lastCall()['query'])->toBe([
        'limit' => 100,
        'startDate' => '01-11-2026',
    ]);
})->with(['balances', 'transactions']);

it('calls the interim and final endpoints', function (string $method, string $path, string $key) {
    $this->statements->{$method}('UA943052990000026100050001037', 50)->all();

    expect($this->transporter->lastCall())->toBe([
        'method' => 'paginate',
        'path' => $path,
        'key' => $key,
        'query' => ['acc' => 'UA943052990000026100050001037', 'limit' => 50],
    ]);
})->with([
    'interim balances' => ['interimBalances', 'statements/balance/interim', 'balances'],
    'final balances' => ['finalBalances', 'statements/balance/final', 'balances'],
    'interim transactions' => ['interimTransactions', 'statements/transactions/interim', 'transactions'],
    'final transactions' => ['finalTransactions', 'statements/transactions/final', 'transactions'],
]);

it('caps the interim limit at 100', function (string $method) {
    $this->statements->{$method}(limit: 500)->all();

    expect($this->transporter->lastCall()['query'])->toBe(['limit' => 100]);
})->with(['interimBalances', 'interimTransactions']);

it('does not cap the final limit', function (string $method) {
    $this->statements->{$method}(limit: 500)->all();

    expect($this->transporter->lastCall()['query'])->toBe(['limit' => 500]);
})->with(['finalBalances', 'finalTransactions']);
