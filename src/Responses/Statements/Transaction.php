<?php

namespace Voharaa\PrivatbankAutoclient\Responses\Statements;

use Voharaa\PrivatbankAutoclient\Enums\TransactionDirection;
use Voharaa\PrivatbankAutoclient\Enums\TransactionStatus;
use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;
use Voharaa\PrivatbankAutoclient\Support\Payload;

/**
 * A single transaction (an item of the "transactions" array from statements/transactions).
 *
 * "own*" fields describe the account the statement is requested for (AUT_MY_*),
 * "counterparty*" fields describe the other side (AUT_CNTR_*). Use $direction to know
 * who paid whom. Amounts are kept as strings to preserve precision; dates are raw strings
 * as returned by the API (local Kyiv time).
 */
readonly class Transaction
{
    /** Transaction ID. Unique per statement row; use it to deduplicate rows. */
    public string $id;

    /** Not always returned by the API. */
    public ?string $technicalId;

    /** Posting reference. Not unique on its own, see instructionKey(). */
    public string $reference;

    /** Sequence number inside the posting. */
    public string $referenceNumber;

    public TransactionDirection $direction;

    public TransactionStatus $status;

    /** Whether the transaction is real (FL_REAL = "r"), as opposed to "i". */
    public bool $isReal;

    public string $amount;

    /** Amount equivalent in UAH. */
    public string $amountUah;

    public string $currency;

    /** Payment purpose. */
    public string $purpose;

    public ?string $documentType;

    public ?string $documentNumber;

    /** Client (document) date, "d.m.Y". */
    public ?string $clientDate;

    /** Value date, "d.m.Y". */
    public ?string $valueDate;

    /** Posting time, "H:i". */
    public ?string $postedTime;

    /** Value date and posting time combined, "d.m.Y H:i:s". */
    public ?string $postedAt;

    /** Reference of the payment in the service it was created with (payment_pack_ref for Autoclient payments). */
    public ?string $paymentReference;

    /** EDRPOU (or individual tax number) of the account owner. */
    public ?string $ownEdrpou;

    public ?string $ownAccount;

    public ?string $ownName;

    public ?string $ownBankMfo;

    public ?string $ownBankName;

    public ?string $ownBankCity;

    /** EDRPOU (or individual tax number) of the counterparty. */
    public ?string $counterpartyEdrpou;

    public ?string $counterpartyAccount;

    public ?string $counterpartyName;

    public ?string $counterpartyBankMfo;

    public ?string $counterpartyBankName;

    public ?string $counterpartyBankCity;

    /** Unique end-to-end transaction reference (NBU requirement). */
    public ?string $uetr;

    /** How the ultimate payer/recipient details are filled (ULTMT). */
    public ?string $ultimateType;

    public ?string $ultimatePayerEdrpou;

    /** Passport series and number of the ultimate payer. */
    public ?string $ultimatePayerDocument;

    public ?string $ultimatePayerName;

    /** Country code of a non-resident ultimate payer. */
    public ?string $ultimatePayerCountryCode;

    public ?string $ultimateRecipientEdrpou;

    /** Passport series and number of the ultimate recipient. */
    public ?string $ultimateRecipientDocument;

    public ?string $ultimateRecipientName;

    /** Country code of a non-resident ultimate recipient. */
    public ?string $ultimateRecipientCountryCode;

    /** Tax payments: payment type code (STRUCT_CODE). */
    public ?string $taxPaymentTypeCode;

    /** Tax payments: budget classification code (STRUCT_TYPE). */
    public ?string $budgetClassificationCode;

    /** Tax payments: tax notice (decision) info (STRUCT_CATEGORY). */
    public ?string $taxNoticeInfo;

    /**
     * @param  array<mixed>  $data
     *
     * @throws TransportException
     */
    public function __construct(array $data)
    {
        $this->id = Payload::string($data, 'ID');
        $this->technicalId = Payload::nonBlankString($data, 'TECHNICAL_TRANSACTION_ID');
        $this->reference = Payload::string($data, 'REF');
        $this->referenceNumber = Payload::string($data, 'REFN');
        $this->direction = Payload::enum($data, 'TRANTYPE', TransactionDirection::class);
        $this->status = Payload::enum($data, 'PR_PR', TransactionStatus::class);
        $this->isReal = Payload::optionalString($data, 'FL_REAL') === 'r';
        $this->amount = Payload::string($data, 'SUM');
        $this->amountUah = Payload::string($data, 'SUM_E');
        $this->currency = Payload::string($data, 'CCY');
        $this->purpose = Payload::string($data, 'OSND');
        $this->documentType = Payload::nonBlankString($data, 'DOC_TYP');
        $this->documentNumber = Payload::nonBlankString($data, 'NUM_DOC');
        $this->clientDate = Payload::nonBlankString($data, 'DAT_KL');
        $this->valueDate = Payload::nonBlankString($data, 'DAT_OD');
        $this->postedTime = Payload::nonBlankString($data, 'TIM_P');
        $this->postedAt = Payload::nonBlankString($data, 'DATE_TIME_DAT_OD_TIM_P');
        $this->paymentReference = Payload::nonBlankString($data, 'DLR');

        $this->ownEdrpou = Payload::nonBlankString($data, 'AUT_MY_CRF');
        $this->ownAccount = Payload::nonBlankString($data, 'AUT_MY_ACC');
        $this->ownName = Payload::nonBlankString($data, 'AUT_MY_NAM');
        $this->ownBankMfo = Payload::nonBlankString($data, 'AUT_MY_MFO');
        $this->ownBankName = Payload::nonBlankString($data, 'AUT_MY_MFO_NAME');
        $this->ownBankCity = Payload::nonBlankString($data, 'AUT_MY_MFO_CITY');

        $this->counterpartyEdrpou = Payload::nonBlankString($data, 'AUT_CNTR_CRF');
        $this->counterpartyAccount = Payload::nonBlankString($data, 'AUT_CNTR_ACC');
        $this->counterpartyName = Payload::nonBlankString($data, 'AUT_CNTR_NAM');
        $this->counterpartyBankMfo = Payload::nonBlankString($data, 'AUT_CNTR_MFO');
        $this->counterpartyBankName = Payload::nonBlankString($data, 'AUT_CNTR_MFO_NAME');
        $this->counterpartyBankCity = Payload::nonBlankString($data, 'AUT_CNTR_MFO_CITY');

        $this->uetr = Payload::nonBlankString($data, 'UETR');
        $this->ultimateType = Payload::nonBlankString($data, 'ULTMT');
        $this->ultimatePayerEdrpou = Payload::nonBlankString($data, 'PAYER_ULTMT_NCEO');
        $this->ultimatePayerDocument = Payload::nonBlankString($data, 'PAYER_ULTMT_DOCUMENT');
        $this->ultimatePayerName = Payload::nonBlankString($data, 'PAYER_ULTMT_NAME');
        $this->ultimatePayerCountryCode = Payload::nonBlankString($data, 'PAYER_ULTMT_COUNTRY_CODE');
        $this->ultimateRecipientEdrpou = Payload::nonBlankString($data, 'RECIPIENT_ULTMT_NCEO');
        $this->ultimateRecipientDocument = Payload::nonBlankString($data, 'RECIPIENT_ULTMT_DOCUMENT');
        $this->ultimateRecipientName = Payload::nonBlankString($data, 'RECIPIENT_ULTMT_NAME');
        $this->ultimateRecipientCountryCode = Payload::nonBlankString($data, 'RECIPIENT_ULTMT_COUNTRY_CODE');

        $this->taxPaymentTypeCode = Payload::nonBlankString($data, 'STRUCT_CODE');
        $this->budgetClassificationCode = Payload::nonBlankString($data, 'STRUCT_TYPE');
        $this->taxNoticeInfo = Payload::nonBlankString($data, 'STRUCT_CATEGORY');
    }

    /**
     * Key of the payment instruction (REF + REFN), as recommended by the API docs.
     *
     * It is NOT unique per row: a transfer between two of your own accounts is one instruction
     * that appears twice, as a debit on one account and a credit on the other. Use $id to tell rows apart.
     */
    public function instructionKey(): string
    {
        return $this->reference.$this->referenceNumber;
    }

    public function isDebit(): bool
    {
        return $this->direction === TransactionDirection::Debit;
    }

    public function isCredit(): bool
    {
        return $this->direction === TransactionDirection::Credit;
    }
}
