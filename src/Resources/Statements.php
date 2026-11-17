<?php

namespace Voharaa\PrivatbankAutoclient\Resources;

use DateTimeInterface;
use Generator;
use Voharaa\PrivatbankAutoclient\Responses\Statements\Balance;
use Voharaa\PrivatbankAutoclient\Responses\Statements\Settings;
use Voharaa\PrivatbankAutoclient\Responses\Statements\Transaction;
use Voharaa\PrivatbankAutoclient\Support\Cursor;
use Voharaa\PrivatbankAutoclient\Support\Payload;

class Statements
{
    use Concerns\Transportable;

    public const string PREFIX = 'statements';

    private const string DATE_FORMAT = 'd-m-Y';

    /** The interim endpoints accept at most 100 records per page. */
    private const int INTERIM_MAX_LIMIT = 100;

    public function settings(): Settings
    {
        $response = $this->transporter->get(self::PREFIX.'/settings');

        return new Settings(Payload::array($response, 'settings'));
    }

    /**
     * Balances for a date range. Without $account, returns all active accounts.
     * Pages (followId) are fetched lazily during iteration.
     *
     * @return Cursor<Balance>
     */
    public function balances(
        DateTimeInterface $startDate,
        ?DateTimeInterface $endDate = null,
        ?string $account = null,
        int $limit = 100,
    ): Cursor {
        return $this->paginateBalances('balance', $this->rangeQuery($startDate, $endDate, $account, $limit));
    }

    /**
     * Interim balances (from lastday to today).
     *
     * @return Cursor<Balance>
     */
    public function interimBalances(?string $account = null, int $limit = 100): Cursor
    {
        return $this->paginateBalances('balance/interim', $this->query($account, min($limit, self::INTERIM_MAX_LIMIT)));
    }

    /**
     * Balances for the last final (closed) day.
     *
     * @return Cursor<Balance>
     */
    public function finalBalances(?string $account = null, int $limit = 100): Cursor
    {
        return $this->paginateBalances('balance/final', $this->query($account, $limit));
    }

    /**
     * Transactions for a date range. Without $account, returns all active accounts.
     * Pages (followId) are fetched lazily during iteration.
     *
     * @return Cursor<Transaction>
     */
    public function transactions(
        DateTimeInterface $startDate,
        ?DateTimeInterface $endDate = null,
        ?string $account = null,
        int $limit = 100,
    ): Cursor {
        return $this->paginateTransactions('transactions', $this->rangeQuery($startDate, $endDate, $account, $limit));
    }

    /**
     * Interim transactions (from lastday to today).
     *
     * @return Cursor<Transaction>
     */
    public function interimTransactions(?string $account = null, int $limit = 100): Cursor
    {
        return $this->paginateTransactions('transactions/interim', $this->query($account, min($limit, self::INTERIM_MAX_LIMIT)));
    }

    /**
     * Transactions for the last final (closed) day.
     *
     * @return Cursor<Transaction>
     */
    public function finalTransactions(?string $account = null, int $limit = 100): Cursor
    {
        return $this->paginateTransactions('transactions/final', $this->query($account, $limit));
    }

    /**
     * @param  array<string, int|string>  $query
     * @return Cursor<Balance>
     */
    private function paginateBalances(string $path, array $query): Cursor
    {
        return new Cursor(function () use ($path, $query): Generator {
            foreach ($this->transporter->paginate(self::PREFIX.'/'.$path, 'balances', $query) as $row) {
                yield new Balance($row);
            }
        });
    }

    /**
     * @param  array<string, int|string>  $query
     * @return Cursor<Transaction>
     */
    private function paginateTransactions(string $path, array $query): Cursor
    {
        return new Cursor(function () use ($path, $query): Generator {
            foreach ($this->transporter->paginate(self::PREFIX.'/'.$path, 'transactions', $query) as $row) {
                yield new Transaction($row);
            }
        });
    }

    /**
     * @return array<string, int|string>
     */
    private function rangeQuery(
        DateTimeInterface $startDate,
        ?DateTimeInterface $endDate,
        ?string $account,
        int $limit,
    ): array {
        return $this->query($account, $limit, [
            'startDate' => $startDate->format(self::DATE_FORMAT),
            'endDate' => $endDate?->format(self::DATE_FORMAT),
        ]);
    }

    /**
     * Builds query parameters, dropping null values.
     *
     * @param  array<string, int|string|null>  $extra
     * @return array<string, int|string>
     */
    private function query(?string $account, int $limit, array $extra = []): array
    {
        return array_filter([
            'acc' => $account,
            'limit' => $limit,
            ...$extra,
        ], fn ($value) => $value !== null);
    }
}
