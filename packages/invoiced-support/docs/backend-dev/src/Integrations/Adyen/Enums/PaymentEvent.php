<?php

namespace App\Integrations\Adyen\Enums;

enum PaymentEvent: string
{
    case AUTHORISATION = 'AUTHORISATION';
}
