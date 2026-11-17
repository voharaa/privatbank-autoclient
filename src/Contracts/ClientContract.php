<?php

namespace Voharaa\PrivatbankAutoclient\Contracts;

use Voharaa\PrivatbankAutoclient\Resources\Statements;

interface ClientContract
{
    public function statements(): Statements;
}
