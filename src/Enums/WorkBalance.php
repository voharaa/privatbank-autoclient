<?php

namespace Voharaa\PrivatbankAutoclient\Enums;

enum WorkBalance: string
{
    case CanMakeRequests = 'N';
    case CannotMakeRequests = 'Y';
}
