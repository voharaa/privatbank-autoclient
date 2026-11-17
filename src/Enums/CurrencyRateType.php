<?php

namespace Voharaa\PrivatbankAutoclient\Enums;

/**
 * Exchange rate type from the bank's point of view (the "B" and "S" keys of proxy/currency).
 */
enum CurrencyRateType: string
{
    /** The rate at which the bank buys the currency. */
    case Buy = 'B';

    /** The rate at which the bank sells the currency. */
    case Sell = 'S';
}
