<?php

namespace App\Enums;

enum ChangeOrderType: string
{
    case None = '';
    case Other = 'other';
    case Entitlements = 'entitlements';
    case InvoicedPayments = 'invoiced_payments';
    case Volume = 'volume';
    case BillingFrequency = 'billing_frequency';
    case Pricing = 'pricing';
    case Refund = 'refund';
    case ServicePeriod = 'service_period';

    public function getName(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Other => 'Other',
            self::Entitlements => 'Entitlements (Products & Features)',
            self::InvoicedPayments => 'Invoiced Payments Setup',
            self::Volume => 'Volume (Users, Invoices, Entities, etc)',
            self::BillingFrequency => 'Billing Frequency',
            self::Pricing => 'Pricing',
            self::Refund => 'Refund',
            self::ServicePeriod => 'Service Period',
        };
    }
}
