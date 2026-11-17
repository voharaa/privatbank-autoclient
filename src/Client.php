<?php

namespace Voharaa\PrivatbankAutoclient;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use SensitiveParameter;
use Voharaa\PrivatbankAutoclient\Contracts\ClientContract;
use Voharaa\PrivatbankAutoclient\Contracts\TransporterContract;
use Voharaa\PrivatbankAutoclient\Resources\Statements;

final class Client implements ClientContract
{
    public function __construct(
        private readonly TransporterContract $transporter,
    ) {}

    public static function make(
        #[SensitiveParameter] string $token,
        ?ClientInterface $http = null,
        ?RequestFactoryInterface $requests = null
    ): self {
        return new self(new Transporters\HttpTransporter(
            $http ?? Psr18ClientDiscovery::find(),
            $requests ?? Psr17FactoryDiscovery::findRequestFactory(),
            $token,
        ));
    }

    public function statements(): Statements
    {
        return new Statements($this->transporter);
    }
}
