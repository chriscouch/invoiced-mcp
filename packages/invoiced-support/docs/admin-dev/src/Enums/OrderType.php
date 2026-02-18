<?php

namespace App\Enums;

enum OrderType: string
{
    case ChangeOrder = 'change_order';
    case Imported = 'imported';
    case NewAccount = 'new_account';
    case NewAccountAndStatementOfWork = 'new_account_sow';
    case NewAccountReseller = 'new_account_reseller';
    case StatementOfWork = 'sow';
    case AddProduct = 'add_product';
    case RemoveProduct = 'remove_product';
    case ChangeUserCount = 'user_count_change';
    case CancelEntity = 'cancel_entity';
    case ChangeUsagePricingPlan = 'usage_pricing_plan_change';
    case ChangeBillingInterval = 'billing_interval_change';

    public function getName(): string
    {
        return match ($this) {
            self::ChangeOrder => 'Change Order',
            self::Imported => 'Imported Order',
            self::NewAccount => 'New Company',
            self::NewAccountAndStatementOfWork => 'New Company + Statement of Work',
            self::NewAccountReseller => 'New Company (Reseller)',
            self::StatementOfWork => 'Statement of Work',
            self::AddProduct => 'Add Product',
            self::RemoveProduct => 'Remove Product',
            self::ChangeUserCount => 'Change User Count',
            self::CancelEntity => 'Cancel Company',
            self::ChangeUsagePricingPlan => 'Change Usage Pricing Plan',
            self::ChangeBillingInterval => 'Change Billing Interval',
        };
    }

    public function hasNewAccount(): bool
    {
        return in_array($this, [OrderType::NewAccount, OrderType::NewAccountReseller, OrderType::NewAccountAndStatementOfWork]);
    }

    public function isChangeOrder(): bool
    {
        return in_array($this, [OrderType::ChangeOrder, OrderType::AddProduct, OrderType::RemoveProduct, OrderType::ChangeUserCount, OrderType::CancelEntity, OrderType::ChangeUsagePricingPlan, OrderType::ChangeBillingInterval]);
    }
}
