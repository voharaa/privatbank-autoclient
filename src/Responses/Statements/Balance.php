<?php

namespace Voharaa\PrivatbankAutoclient\Responses\Statements;

use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;
use Voharaa\PrivatbankAutoclient\Support\Payload;

/**
 * Balance of a single account for a single day (an item of the "balances" array from statements/balance).
 *
 * For a date range the API returns one row per account per day; $date tells which day the row is for.
 * Amounts are kept as strings to preserve precision.
 * Dates are raw strings as returned by the API, in "d.m.Y H:i:s" format (local Kyiv time).
 */
readonly class Balance
{
    /** The API uses this date for accounts that have not been closed. */
    private const string NOT_CLOSED_DATE = '01.01.1900 00:00:00';

    public string $account;

    public string $currency;

    public string $openingBalance;

    /** Opening balance equivalent in UAH. */
    public string $openingBalanceUah;

    public string $closingBalance;

    /** Closing balance equivalent in UAH. */
    public string $closingBalanceUah;

    public string $debitTurnover;

    /** Debit turnover equivalent in UAH. */
    public string $debitTurnoverUah;

    public string $creditTurnover;

    /** Credit turnover equivalent in UAH. */
    public string $creditTurnoverUah;

    public ?string $accountName;

    /** Branch of the account. */
    public ?string $branch;

    /** Branch that attracted the client. */
    public ?string $attractingBranch;

    /**
     * Day this balance row is for (DPD). The API docs call it "date of the last movement",
     * but the real API returns one row per account per day with this field set to that day.
     */
    public string $date;

    public ?string $openedAt;

    public ?string $openedInSystemAt;

    /** Null if the account is not closed. */
    public ?string $closedAt;

    /** False while the day is not yet in the final statement (the last operating day and today). */
    public bool $isFinal;

    /** Undocumented, raw value from the API. */
    public ?string $state;

    /** Undocumented, raw value from the API. */
    public ?string $atp;

    /** Undocumented, raw value from the API. */
    public ?string $flmn;

    /**
     * @param  array<mixed>  $data
     *
     * @throws TransportException
     */
    public function __construct(array $data)
    {
        $this->account = Payload::string($data, 'acc');
        $this->currency = Payload::string($data, 'currency');
        $this->openingBalance = Payload::string($data, 'balanceIn');
        $this->openingBalanceUah = Payload::string($data, 'balanceInEq');
        $this->closingBalance = Payload::string($data, 'balanceOut');
        $this->closingBalanceUah = Payload::string($data, 'balanceOutEq');
        $this->debitTurnover = Payload::string($data, 'turnoverDebt');
        $this->debitTurnoverUah = Payload::string($data, 'turnoverDebtEq');
        $this->creditTurnover = Payload::string($data, 'turnoverCred');
        $this->creditTurnoverUah = Payload::string($data, 'turnoverCredEq');
        $this->accountName = Payload::optionalString($data, 'nameACC');
        $this->branch = Payload::optionalString($data, 'brnm');
        $this->attractingBranch = Payload::nonBlankString($data, 'bgfIBrnm');
        $this->date = Payload::string($data, 'dpd');
        $this->openedAt = Payload::optionalString($data, 'date_open_acc_reg');
        $this->openedInSystemAt = Payload::optionalString($data, 'date_open_acc_sys');
        $this->closedAt = ($data['date_close_acc'] ?? null) === self::NOT_CLOSED_DATE
            ? null
            : Payload::optionalString($data, 'date_close_acc');
        $this->isFinal = Payload::bool($data, 'is_final_bal');
        $this->state = Payload::optionalString($data, 'state');
        $this->atp = Payload::optionalString($data, 'atp');
        $this->flmn = Payload::optionalString($data, 'flmn');
    }
}
