<?php

namespace App\SubscriptionBilling\Libs;

use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\Customer;
use App\SubscriptionBilling\Models\CouponRedemption;
use App\SubscriptionBilling\Models\Subscription;

final class SubscriptionMock extends Subscription
{
    public Customer $customer;
    public bool $preserveTaxes = true;

    public function lookupCustomer(?int $customerId): void
    {
        if ($customerId > 0) {
            $customer = Customer::find($customerId);
            if ($customer instanceof Customer) {
                $this->customer = $customer;
            }
        }

        if (!isset($this->customer)) {
            $this->customer = new Customer();
            $this->customer->tenant_id = $this->tenant_id;
        }
    }

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function setCouponRedemptions(array $redemptions): void
    {
        $result = [];
        foreach ($redemptions as $item) {
            if ($coupon = Coupon::getCurrent($item)) {
                $redemption = new CouponRedemption();
                $redemption->setCoupon($coupon);
                $result[] = $redemption;
            }
        }

        parent::setCouponRedemptions($result);
    }

    /**
     * Always returns persisted customer
     * for proper task calculation.
     */
    public function customer(): Customer
    {
        return $this->customer;
    }
}
