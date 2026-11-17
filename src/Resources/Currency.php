<?php

namespace Voharaa\PrivatbankAutoclient\Resources;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Voharaa\PrivatbankAutoclient\Exceptions\ApiException;
use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;
use Voharaa\PrivatbankAutoclient\Responses\Currency\ExchangeRates;
use Voharaa\PrivatbankAutoclient\Responses\Currency\ExchangeRatesHistory;

class Currency
{
    use Concerns\Transportable;

    public const string PREFIX = 'proxy/currency';

    /** The history endpoint accepts a period of at most this many days. */
    public const int HISTORY_MAX_DAYS = 15;

    private const string DATE_FORMAT = 'd-m-Y';

    /**
     * Current PrivatBank exchange rates (buy/sell) together with the NBU rate.
     *
     * @throws ApiException
     * @throws TransportException
     */
    public function rates(): ExchangeRates
    {
        return new ExchangeRates($this->transporter->get(self::PREFIX));
    }

    /**
     * Exchange rates history for a period of at most 15 days: one row per currency per day.
     *
     * @throws InvalidArgumentException when $endDate is before $startDate or the period is longer than 15 days
     * @throws ApiException
     * @throws TransportException
     */
    public function history(DateTimeInterface $startDate, DateTimeInterface $endDate): ExchangeRatesHistory
    {
        $start = DateTimeImmutable::createFromInterface($startDate)->setTime(0, 0);
        $end = DateTimeImmutable::createFromInterface($endDate)->setTime(0, 0);

        if ($end < $start) {
            throw new InvalidArgumentException('The end date must not be before the start date');
        }

        $days = (int) $start->diff($end)->days + 1;

        if ($days > self::HISTORY_MAX_DAYS) {
            throw new InvalidArgumentException(
                'The period must not be longer than '.self::HISTORY_MAX_DAYS." days, {$days} days given"
            );
        }

        return new ExchangeRatesHistory($this->transporter->get(self::PREFIX.'/history', [
            'startDate' => $startDate->format(self::DATE_FORMAT),
            'endDate' => $endDate->format(self::DATE_FORMAT),
        ]));
    }
}
