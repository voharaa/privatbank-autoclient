<?php

use Voharaa\PrivatbankAutoclient\Contracts\HasRawResponse;
use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;
use Voharaa\PrivatbankAutoclient\Responses\Statements\Balance;

it('maps the API fields to readable properties', function () {
    $balance = new Balance(firstItem('balances'));

    expect($balance)
        ->account->toBe('UA943052990000026100050001037')
        ->currency->toBe('UAH')
        ->openingBalance->toBe('100.50')
        ->openingBalanceUah->toBe('100.50')
        ->closingBalance->toBe('250.75')
        ->closingBalanceUah->toBe('250.75')
        ->debitTurnover->toBe('49.75')
        ->debitTurnoverUah->toBe('49.75')
        ->creditTurnover->toBe('200.00')
        ->creditTurnoverUah->toBe('200.00')
        ->accountName->toBe('Програмiсти та Ko МСБ-ТЕСТ ТОВ')
        ->branch->toBe('DNH0')
        ->date->toBe('09.03.2020 00:00:00')
        ->openedAt->toBe('28.12.2016 00:00:00')
        ->openedInSystemAt->toBe('28.12.2016 00:00:00')
        ->isFinal->toBeTrue()
        ->state->toBe('l')
        ->atp->toBe('D')
        ->flmn->toBe('DN');
});

it('keeps amounts as strings to preserve precision', function () {
    $balance = new Balance([...firstItem('balances'), 'balanceOut' => '0.10']);

    expect($balance->closingBalance)->toBeString()->toBe('0.10');
});

it('treats 01.01.1900 as "not closed"', function () {
    expect((new Balance(firstItem('balances')))->closedAt)->toBeNull();
});

it('keeps a real close date', function () {
    $balance = new Balance([...firstItem('balances'), 'date_close_acc' => '15.06.2024 00:00:00']);

    expect($balance->closedAt)->toBe('15.06.2024 00:00:00');
});

it('turns a blank attracting branch into null', function () {
    expect((new Balance(firstItem('balances')))->attractingBranch)->toBeNull();

    $balance = new Balance([...firstItem('balances'), 'bgfIBrnm' => 'KIEV']);

    expect($balance->attractingBranch)->toBe('KIEV');
});

it('accepts is_final_bal as a string', function (mixed $value, bool $expected) {
    $balance = new Balance([...firstItem('balances'), 'is_final_bal' => $value]);

    expect($balance->isFinal)->toBe($expected);
})->with([
    'bool true' => [true, true],
    'bool false' => [false, false],
    'string "true"' => ['true', true],
    'string "false"' => ['false', false],
]);

it('sets optional fields to null when they are missing', function () {
    $row = firstItem('balances');
    unset($row['nameACC'], $row['brnm'], $row['date_close_acc'], $row['is_final_bal']);

    expect(new Balance($row))
        ->accountName->toBeNull()
        ->branch->toBeNull()
        ->closedAt->toBeNull()
        ->isFinal->toBeFalse();
});

it('requires the date of the balance row', function () {
    $row = firstItem('balances');
    unset($row['dpd']);

    new Balance($row);
})->throws(TransportException::class, 'missing field "dpd"');

it('keeps the row exactly as returned by the API in raw', function () {
    $row = [...firstItem('balances'), 'newField' => 'value'];
    $balance = new Balance($row);

    expect($balance->raw)->toBe($row)
        ->and($balance->raw['newField'])->toBe('value');
});

it('keeps blank and placeholder values in raw while the property is null', function () {
    $balance = new Balance([...firstItem('balances'), 'bgfIBrnm' => '']);

    expect($balance->raw['bgfIBrnm'])->toBe('')
        ->and($balance->attractingBranch)->toBeNull()
        ->and($balance->raw['date_close_acc'])->toBe('01.01.1900 00:00:00')
        ->and($balance->closedAt)->toBeNull();
});

it('implements HasRawResponse', function () {
    $row = firstItem('balances');
    $balance = new Balance($row);

    expect($balance)->toBeInstanceOf(HasRawResponse::class)
        ->and($balance->raw())->toBe($row);
});
