<?php

namespace App\PaymentProcessing\Enums;

enum PaymentFlowSource: int
{
    case Api = 1;
    case AutoPay = 2;
    case Charge = 3;
    case CustomerPortal = 4;
    case Network = 5;
    case VirtualTerminal = 6;

    public function toString(): string
    {
        return match ($this) {
            self::Api => 'api',
            self::AutoPay => 'autopay',
            self::Charge => 'charge',
            self::CustomerPortal => 'customer_portal',
            self::Network => 'network',
            self::VirtualTerminal => 'virtual_terminal',
        };
    }
}
