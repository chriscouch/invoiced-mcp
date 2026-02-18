<?php

namespace App\Integrations\Adyen\Enums;

enum RefundEvent: string
{
    case REFUND = 'REFUND';
    case CANCELLATION = 'CANCELLATION';
    case REFUND_FAILED = 'REFUND_FAILED';
}
