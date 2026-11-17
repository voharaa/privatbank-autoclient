<?php

use Voharaa\PrivatbankAutoclient\Enums\TransactionDirection;
use Voharaa\PrivatbankAutoclient\Enums\TransactionStatus;
use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;
use Voharaa\PrivatbankAutoclient\Responses\Statements\Transaction;

it('maps the core fields', function () {
    $transaction = new Transaction(firstItem('transactions'));

    expect($transaction)
        ->id->toBe('557091731')
        ->technicalId->toBe('557091731_online')
        ->reference->toBe('DNCHK0108B1WKX')
        ->referenceNumber->toBe('1')
        ->amount->toBe('0.01')
        ->amountUah->toBe('0.01')
        ->currency->toBe('UAH')
        ->purpose->toBe('Нарахування вiдсоткiв згiдно з депозитним договором N...')
        ->documentType->toBe('m')
        ->documentNumber->toBe('K0108B1WKX')
        ->clientDate->toBe('07.01.2020')
        ->valueDate->toBe('07.01.2020')
        ->postedTime->toBe('02:58')
        ->postedAt->toBe('07.01.2020 02:58:00')
        ->paymentReference->toBe('J63DNDSM0XHY5');
});

it('maps AUT_MY_* to own* and AUT_CNTR_* to counterparty*', function () {
    expect(new Transaction(firstItem('transactions')))
        ->ownEdrpou->toBe('31451288')
        ->ownAccount->toBe('26184050001514')
        ->ownName->toBe('Програмiсти та Ko МСБ-ТЕСТ ТОВ')
        ->ownBankMfo->toBe('305299')
        ->ownBankName->toBe('АТ КБ "ПРИВАТБАНК"')
        ->ownBankCity->toBe('Київ')
        ->counterpartyEdrpou->toBe('14360570')
        ->counterpartyAccount->toBe('70214924104032')
        ->counterpartyName->toBe('ПРОЦ ВИТР ЗА СТРОК КОШТ СУБ(UAH)')
        ->counterpartyBankMfo->toBe('305299')
        ->counterpartyBankName->toBe('АТ КБ "ПРИВАТБАНК"')
        ->counterpartyBankCity->toBe('Київ');
});

it('maps the tax payment fields', function () {
    expect(new Transaction(firstItem('transactions')))
        ->taxPaymentTypeCode->toBe('101')
        ->budgetClassificationCode->toBe('22080000')
        ->taxNoticeInfo->toBe('Довільний текст');
});

it('turns blank NBU ultimate payer/recipient fields into null', function () {
    expect(new Transaction(firstItem('transactions')))
        ->uetr->toBe('b23aeadc-1ab7-4c34-a005-0f005a059948')
        ->ultimateType->toBe('N')
        ->ultimatePayerEdrpou->toBeNull()
        ->ultimatePayerDocument->toBeNull()
        ->ultimatePayerName->toBeNull()
        ->ultimatePayerCountryCode->toBeNull()
        ->ultimateRecipientEdrpou->toBeNull()
        ->ultimateRecipientDocument->toBeNull()
        ->ultimateRecipientName->toBeNull()
        ->ultimateRecipientCountryCode->toBeNull();
});

it('sets optional fields to null when they are missing', function () {
    $row = firstItem('transactions');
    unset($row['UETR'], $row['STRUCT_CODE'], $row['DLR'], $row['AUT_CNTR_NAM'], $row['TECHNICAL_TRANSACTION_ID']);

    expect(new Transaction($row))
        ->technicalId->toBeNull()
        ->uetr->toBeNull()
        ->taxPaymentTypeCode->toBeNull()
        ->paymentReference->toBeNull()
        ->counterpartyName->toBeNull();
});

it('maps the posting status', function (string $code, TransactionStatus $status) {
    $transaction = new Transaction([...firstItem('transactions'), 'PR_PR' => $code]);

    expect($transaction->status)->toBe($status);
})->with([
    'processing' => ['p', TransactionStatus::Processing],
    'reversed' => ['t', TransactionStatus::Reversed],
    'posted' => ['r', TransactionStatus::Posted],
    'rejected' => ['n', TransactionStatus::Rejected],
]);

it('reports an unknown enum value as a TransportException', function (string $field) {
    new Transaction([...firstItem('transactions'), $field => 'x']);
})->with(['PR_PR', 'TRANTYPE'])->throws(TransportException::class, 'unknown value "x"');

it('maps the direction and its helpers', function () {
    $credit = new Transaction(firstItem('transactions'));
    $debit = new Transaction([...firstItem('transactions'), 'TRANTYPE' => 'D']);

    expect($credit)
        ->direction->toBe(TransactionDirection::Credit)
        ->isCredit()->toBeTrue()
        ->isDebit()->toBeFalse();

    expect($debit)
        ->direction->toBe(TransactionDirection::Debit)
        ->isCredit()->toBeFalse()
        ->isDebit()->toBeTrue();
});

it('marks only FL_REAL = "r" as real', function (?string $value, bool $expected) {
    $row = [...firstItem('transactions'), 'FL_REAL' => $value];

    expect((new Transaction($row))->isReal)->toBe($expected);
})->with([
    'r' => ['r', true],
    'i' => ['i', false],
    'missing' => [null, false],
]);

it('builds the instruction key from REF and REFN', function () {
    $first = new Transaction(firstItem('transactions'));
    $second = new Transaction([...firstItem('transactions'), 'REFN' => '2']);

    expect($first->instructionKey())->toBe('DNCHK0108B1WKX1')
        ->and($second->instructionKey())->toBe('DNCHK0108B1WKX2')
        ->and($first->reference)->toBe($second->reference);
});

it('shares the instruction key between both sides of a transfer between own accounts', function () {
    $row = firstItem('transactions');

    $debit = new Transaction([...$row, 'ID' => '1', 'TRANTYPE' => 'D', 'AUT_MY_ACC' => 'UA_OWN_1', 'AUT_CNTR_ACC' => 'UA_OWN_2']);
    $credit = new Transaction([...$row, 'ID' => '2', 'TRANTYPE' => 'C', 'AUT_MY_ACC' => 'UA_OWN_2', 'AUT_CNTR_ACC' => 'UA_OWN_1']);

    expect($debit->instructionKey())->toBe($credit->instructionKey())
        ->and($debit->id)->not->toBe($credit->id);
});
