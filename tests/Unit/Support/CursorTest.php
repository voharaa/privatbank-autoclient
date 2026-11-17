<?php

use Voharaa\PrivatbankAutoclient\Support\Cursor;

it('does not run the factory until iterated', function () {
    $calls = 0;

    new Cursor(function () use (&$calls) {
        $calls++;
        yield 1;
    });

    expect($calls)->toBe(0);
});

it('can be iterated more than once', function () {
    $calls = 0;

    $cursor = new Cursor(function () use (&$calls) {
        $calls++;
        yield from [1, 2, 3];
    });

    expect(iterator_to_array($cursor, false))->toBe([1, 2, 3])
        ->and(iterator_to_array($cursor, false))->toBe([1, 2, 3])
        ->and($calls)->toBe(2);
});

it('stops pulling items after break', function () {
    $pulled = 0;

    $cursor = new Cursor(function () use (&$pulled) {
        foreach ([1, 2, 3] as $item) {
            $pulled++;
            yield $item;
        }
    });

    foreach ($cursor as $item) {
        break;
    }

    expect($pulled)->toBe(1);
});

it('returns all items as a list', function () {
    $cursor = new Cursor(fn () => yield from ['a', 'b']);

    expect($cursor->all())->toBe(['a', 'b'])->toBeList();
});

it('keeps every item in all() even when generator keys repeat', function () {
    // yield from over several generators restarts keys at 0 for each one
    $cursor = new Cursor(function () {
        yield from (fn () => yield from ['a', 'b'])();
        yield from (fn () => yield from ['c', 'd'])();
    });

    expect($cursor->all())->toBe(['a', 'b', 'c', 'd']);
});

it('returns an empty list when there is nothing', function () {
    expect((new Cursor(fn () => yield from []))->all())->toBe([]);
});
