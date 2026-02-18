<?php

namespace App\Enums;

enum BillingSystem: string
{
    case None = '';
    case Invoiced = 'invoiced';
    case Reseller = 'reseller';
    case Stripe = 'stripe';

    public function getName(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Invoiced => 'Invoiced',
            self::Reseller => 'Reseller',
            self::Stripe => 'Stripe',
        };
    }
}
