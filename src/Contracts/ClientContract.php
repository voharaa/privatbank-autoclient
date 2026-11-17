<?php

namespace Voharaa\PrivatbankAutoclient\Contracts;

use Voharaa\PrivatbankAutoclient\Resources\Currency;
use Voharaa\PrivatbankAutoclient\Resources\Statements;

interface ClientContract
{
    public function statements(): Statements;

    public function currency(): Currency;
}
