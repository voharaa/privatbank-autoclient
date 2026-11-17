<?php

namespace Voharaa\PrivatbankAutoclient\Resources\Concerns;

use Voharaa\PrivatbankAutoclient\Contracts\TransporterContract;

trait Transportable
{
    public function __construct(private readonly TransporterContract $transporter) {}
}
