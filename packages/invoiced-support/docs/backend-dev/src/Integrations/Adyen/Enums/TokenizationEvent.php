<?php

namespace App\Integrations\Adyen\Enums;

enum TokenizationEvent: string
{
    case RECURRING_CONTRACT = 'RECURRING_CONTRACT';
}
