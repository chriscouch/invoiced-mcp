<?php

namespace App\SubscriptionBilling\Trait;

use App\AccountsReceivable\Models\Coupon;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\ValueObjects\SubscriptionStatus;

trait ModifySubscriptionTrait
{
    private function verifyPlan(Subscription $subscription, array &$parameters): void
    {
        if (!isset($parameters['plan'])) {
            if ($subscription->persisted()) {
                return;
            }

            throw new OperationException('Plan missing');
        }

        $plan = $parameters['plan'];
        unset($parameters['plan']);

        if ($plan instanceof Plan) {
            $subscription->setPlan($plan);

            return;
        }

        $plan2 = Plan::getCurrent($plan);
        if (!$plan2) {
            throw new OperationException("No such plan: $plan");
        }
        $subscription->setPlan($plan2);
    }

    private function verifyAddons(Subscription $subscription, array &$parameters): void
    {
        if (!isset($parameters['addons'])) {
            return;
        }

        $addons = (array) $parameters['addons'];
        unset($parameters['addons']);

        if (count($addons) > Subscription::ADDON_LIMIT) {
            throw new OperationException('The maximum number of subscription addons allowed is '.Subscription::ADDON_LIMIT);
        }

        // verify any addons with plans match the subscription interval
        $subscriptionPlan = $subscription->plan();
        foreach ($addons as $addon) {
            if ($planId = array_value($addon, 'plan')) {
                $addonPlan = Plan::getCurrent($planId);
                if ($addonPlan && !$subscriptionPlan->interval()->equals($addonPlan->interval())) {
                    throw new OperationException('Billing cycle for addon "'.$addonPlan->id.'" ('.$addonPlan->interval().') does not match the subscription\'s billing cycle ('.$subscriptionPlan->interval().'). You can only use plan addons with the same billing interval as the subscription.');
                }
            }
        }
        $subscription->setSaveAddons($addons);
    }

    private function verifyCoupons(Subscription $subscription, array &$parameters): void
    {
        if (!isset($parameters['discounts'])) {
            return;
        }

        $couponIds = (array) $parameters['discounts'];
        unset($parameters['discounts']);

        $coupons = [];
        foreach ($couponIds as $couponId) {
            $coupon = Coupon::getCurrent($couponId);
            if (!$coupon) {
                throw new OperationException('No such coupon: '.$couponId);
            }
            $coupons[] = $coupon;
        }
        $subscription->setSaveCoupons($coupons);
    }

    private function verifyContractRenewalMethod(Subscription $subscription, array $parameters): void
    {
        if (isset($parameters['contract_renewal_mode']) && Subscription::RENEWAL_MODE_MANUAL === $parameters['contract_renewal_mode'] && !$subscription->tenant()->features->has('subscription_manual_renewal')) {
            throw new OperationException("Invalid value supplied to 'contract_renewal_mode': 'manual'");
        }
    }

    private function verifyBillInAdvanceDays(Subscription $subscription): void
    {
        // only check if bill in advance
        if (Subscription::BILL_IN_ADVANCE !== $subscription->bill_in) {
            return;
        }

        // subscription plan interval should allways be greater than bill in advance
        $subscriptionDays = $subscription->plan()->interval()->numDays();
        if ($subscriptionDays <= $subscription->bill_in_advance_days) {
            throw new OperationException("The invoice can be issued in advance at most the number of days in the billing period ($subscriptionDays).");
        }
    }

    private function calculateContractPeriod(Subscription $subscription): void
    {
        // do not calculate contract period if the subscription
        // has no contract or no further billing
        if (!$subscription->cycles || !$subscription->renews_next) {
            return;
        }

        $subscription->contractPeriods()->update();
    }

    private function verifySubscriptionAmount(Subscription $subscription, array $parameters): void
    {
        $plan = $subscription->plan();
        $amount = $parameters['amount'] ?? $subscription->amount;
        if (Plan::PRICING_CUSTOM !== $plan->pricing_mode && null !== $amount) {
            throw new OperationException('Amounts are only allowed when the plan has a custom pricing mode');
        } elseif (Plan::PRICING_CUSTOM === $plan->pricing_mode && null === $amount) {
            throw new OperationException('An amount is required when the subscription has a custom plan');
        }
    }

    private function setStatus(Subscription $subscription): void
    {
        $subscription->status = (new SubscriptionStatus($subscription))->get();
    }
}
