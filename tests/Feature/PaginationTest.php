<?php

use Voharaa\PrivatbankAutoclient\Client;
use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;
use Voharaa\PrivatbankAutoclient\Responses\Statements\Balance;
use Voharaa\PrivatbankAutoclient\Responses\Statements\Transaction;

/**
 * Full stack: Client → Statements → HttpTransporter → (mocked) HTTP, using two-page fixtures.
 */
it('loads balances from two pages', function () {
    $client = new Client(mockTransporter([
        jsonResponse(apiFixture('balances-page-1')),
        jsonResponse(apiFixture('balances-page-2')),
    ], $history));

    $balances = $client->statements()->balances(new DateTimeImmutable('2026-11-01'))->all();

    expect($balances)->toHaveCount(3)->each->toBeInstanceOf(Balance::class)
        ->and(array_map(fn (Balance $b) => $b->account, $balances))->toBe([
            'UA943052990000026100050001037',
            'UA213052990000026007000000002',
            'UA213052990000026007000000003',
        ])
        ->and($history[1]['request']->getUri()->getQuery())->toContain('followId=26101050000888');
});

it('loads transactions from two pages', function () {
    $client = new Client(mockTransporter([
        jsonResponse(apiFixture('transactions-page-1')),
        jsonResponse(apiFixture('transactions-page-2')),
    ], $history));

    $transactions = $client->statements()->transactions(new DateTimeImmutable('2026-11-01'))->all();

    expect($transactions)->toHaveCount(3)->each->toBeInstanceOf(Transaction::class)
        ->and(array_map(fn (Transaction $t) => $t->instructionKey(), $transactions))->toBe([
            'DNCHK0108B1WKX1',
            'DNCHK0108B1WKY1',
            'DNCHK0108B1WKZ1',
        ])
        ->and($history[1]['request']->getUri()->getQuery())->toContain('followId=620699370_online');
});

it('sends the requests again on every foreach over the same cursor', function () {
    $client = new Client(mockTransporter([
        jsonResponse(apiFixture('balances-page-1')),
        jsonResponse(apiFixture('balances-page-2')),
        jsonResponse(apiFixture('balances-page-1')),
        jsonResponse(apiFixture('balances-page-2')),
    ], $history));

    $cursor = $client->statements()->balances(new DateTimeImmutable('2026-11-01'));

    $first = [];
    foreach ($cursor as $balance) {
        $first[] = $balance->account;
    }

    $second = [];
    foreach ($cursor as $balance) {
        $second[] = $balance->account;
    }

    expect($first)->toHaveCount(3)
        ->and($second)->toBe($first)
        ->and($history)->toHaveCount(4);
});

it('reports a changed response format as TransportException', function () {
    $broken = apiFixture('balances');
    unset($broken['balances'][0]['currency']);

    $client = new Client(mockTransporter([jsonResponse($broken)]));

    $client->statements()->balances(new DateTimeImmutable('2026-11-01'))->all();
})->throws(TransportException::class, 'missing field "currency"');

it('reports settings without the settings object as TransportException', function () {
    $client = new Client(mockTransporter([jsonResponse(['status' => 'SUCCESS'])]));

    $client->statements()->settings();
})->throws(TransportException::class, 'field "settings" is not an object or a list');
