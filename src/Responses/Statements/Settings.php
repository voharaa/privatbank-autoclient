<?php

namespace Voharaa\PrivatbankAutoclient\Responses\Statements;

use Voharaa\PrivatbankAutoclient\Contracts\HasRawResponse;
use Voharaa\PrivatbankAutoclient\Enums\WorkBalance;
use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;
use Voharaa\PrivatbankAutoclient\Responses\Concerns\InteractsWithRawResponse;
use Voharaa\PrivatbankAutoclient\Support\Payload;

/**
 * Server dates and status returned by statements/settings.
 */
readonly class Settings implements HasRawResponse
{
    use InteractsWithRawResponse;

    private const string WORKING_PHASE = 'WRK';

    public string $phase;

    /** @var list<string> */
    public array $datesWithoutOperDay;

    public string $today;

    public string $lastDay;

    public WorkBalance $workBalance;

    public string $serverDateTime;

    public string $dateFinalStatement;

    /**
     * @param  array<mixed>  $data
     *
     * @throws TransportException
     */
    public function __construct(array $data)
    {
        $this->raw = $data;
        $this->phase = Payload::string($data, 'phase');
        $this->datesWithoutOperDay = Payload::stringList($data, 'dates_without_oper_day');
        $this->today = Payload::string($data, 'today');
        $this->lastDay = Payload::string($data, 'lastday');
        $this->workBalance = Payload::enum($data, 'work_balance', WorkBalance::class);
        $this->serverDateTime = Payload::string($data, 'server_date_time');
        $this->dateFinalStatement = Payload::string($data, 'date_final_statement');
    }

    /**
     * Whether it is safe to send requests right now: the server is in the working phase
     * and no scheduled maintenance jobs are running. Otherwise, requests may fail.
     */
    public function isApiAvailable(): bool
    {
        return $this->phase === self::WORKING_PHASE
            && $this->workBalance === WorkBalance::CanMakeRequests;
    }
}
