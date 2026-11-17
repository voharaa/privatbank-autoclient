<?php

use Voharaa\PrivatbankAutoclient\Contracts\HasRawResponse;
use Voharaa\PrivatbankAutoclient\Enums\WorkBalance;
use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;
use Voharaa\PrivatbankAutoclient\Responses\Statements\Settings;

it('maps the API fields', function () {
    expect(new Settings(apiFixture('settings')['settings']))
        ->phase->toBe('WRK')
        ->datesWithoutOperDay->toBe(['01.01.2018 00:00:00', '30.12.2018 00:00:00', '31.12.2018 00:00:00'])
        ->today->toBe('30.03.2020 00:00:00')
        ->lastDay->toBe('29.03.2020 00:00:00')
        ->workBalance->toBe(WorkBalance::CanMakeRequests)
        ->serverDateTime->toBe('30.03.2020 12:03:51')
        ->dateFinalStatement->toBe('28.03.2020 00:00:00');
});

it('reports whether the API is available', function (string $phase, string $workBalance, bool $expected) {
    $settings = new Settings([...apiFixture('settings')['settings'], 'phase' => $phase, 'work_balance' => $workBalance]);

    expect($settings->isApiAvailable())->toBe($expected);
})->with([
    'working, no maintenance' => ['WRK', 'N', true],
    'working, maintenance running' => ['WRK', 'Y', false],
    'other phase, no maintenance' => ['CLS', 'N', false],
    'other phase, maintenance running' => ['CLS', 'Y', false],
]);

it('reports an unknown work_balance as a TransportException', function () {
    new Settings([...apiFixture('settings')['settings'], 'work_balance' => 'X']);
})->throws(TransportException::class, 'unknown value "X" in field "work_balance"');

it('keeps the settings object exactly as returned by the API in raw', function () {
    $data = [...apiFixture('settings')['settings'], 'new_field' => 'value'];

    expect((new Settings($data))->raw)->toBe($data);
});

it('implements HasRawResponse', function () {
    $data = apiFixture('settings')['settings'];
    $settings = new Settings($data);

    expect($settings)->toBeInstanceOf(HasRawResponse::class)
        ->and($settings->raw())->toBe($data);
});
