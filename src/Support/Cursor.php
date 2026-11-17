<?php

namespace Voharaa\PrivatbankAutoclient\Support;

use Closure;
use Generator;
use IteratorAggregate;

/**
 * Lazy, re-iterable list of items fetched page by page.
 *
 * Nothing is requested until iteration starts. Every foreach starts a fresh generator,
 * so iterating twice sends the requests again; use all() to fetch everything once.
 *
 * @template T
 *
 * @implements IteratorAggregate<int, T>
 */
final class Cursor implements IteratorAggregate
{
    /** @param Closure(): Generator<int, T> $factory */
    public function __construct(private readonly Closure $factory) {}

    /** @return Generator<int, T> */
    public function getIterator(): Generator
    {
        return ($this->factory)();
    }

    /** @return list<T> */
    public function all(): array
    {
        return iterator_to_array($this, false);
    }
}
