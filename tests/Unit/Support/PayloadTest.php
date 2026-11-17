<?php

use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;
use Voharaa\PrivatbankAutoclient\Support\Payload;

it('reads required strings and casts numbers', function () {
    expect(Payload::string(['a' => 'x'], 'a'))->toBe('x')
        ->and(Payload::string(['a' => 42], 'a'))->toBe('42')
        ->and(Payload::string(['a' => 1.5], 'a'))->toBe('1.5');
});

it('throws when a required string is missing or null', function (array $data) {
    Payload::string($data, 'a');
})->with([
    'missing' => [[]],
    'null' => [['a' => null]],
])->throws(TransportException::class, 'missing field "a"');

it('throws when a string field has another type', function (mixed $value) {
    Payload::optionalString(['a' => $value], 'a');
})->with([
    'array' => [['x']],
    'bool' => [true],
])->throws(TransportException::class, 'field "a" is not a string');

it('reads optional strings', function () {
    expect(Payload::optionalString([], 'a'))->toBeNull()
        ->and(Payload::optionalString(['a' => ' '], 'a'))->toBe(' ')
        ->and(Payload::nonBlankString(['a' => ' '], 'a'))->toBeNull()
        ->and(Payload::nonBlankString(['a' => 'x'], 'a'))->toBe('x');
});

it('reads a list of strings', function () {
    expect(Payload::stringList(['a' => ['x', 'y']], 'a'))->toBe(['x', 'y']);
});

it('throws when a list contains something other than strings', function () {
    Payload::stringList(['a' => ['x', ['y']]], 'a');
})->throws(TransportException::class);
