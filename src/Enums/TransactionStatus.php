<?php

namespace Voharaa\PrivatbankAutoclient\Enums;

/**
 * Transaction posting status (PR_PR).
 */
enum TransactionStatus: string
{
    case Processing = 'p';
    case Reversed = 't';
    case Posted = 'r';
    case Rejected = 'n';
}
