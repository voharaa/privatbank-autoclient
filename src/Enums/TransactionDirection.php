<?php

namespace Voharaa\PrivatbankAutoclient\Enums;

/**
 * Transaction direction from the account owner's point of view (TRANTYPE).
 */
enum TransactionDirection: string
{
    case Debit = 'D';
    case Credit = 'C';
}
