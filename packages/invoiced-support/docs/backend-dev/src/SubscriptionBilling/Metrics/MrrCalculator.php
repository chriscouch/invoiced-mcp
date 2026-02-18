<?php

namespace App\SubscriptionBilling\Metrics;

use App\AccountsReceivable\Exception\InvoiceCalculationException;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\LineItem;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\ValueObjects\Interval;
use App\SalesTax\Exception\TaxCalculationException;
use App\SubscriptionBilling\Exception\PricingException;
use App\SubscriptionBilling\Libs\SubscriptionInvoice;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\Models\Subscription;
use RuntimeException;

/**
 * Handles MRR calculation for subscriptions.
 */
final class MrrCalculator
{
    /**
     * Calculates the recurring total and MRR for a subscription.
     *
     * @throws TaxCalculationException|InvoiceCalculationException|PricingException
     *
     * @return Money[] Recurring Total, MRR
     */
    public function calculateForSubscription(Subscription $subscription, bool $withTaxPreview): array
    {
        $invoice = new SubscriptionInvoice($subscription);

        // calculate the recurring total (with taxes if requested)
        $recurringTotal = $invoice->getRecurringTotal($withTaxPreview);

        // calculate MRR (excludes taxes)
        if ($withTaxPreview) {
            $recurringTotalWithoutTaxes = $invoice->getRecurringTotal(false);
        } else {
            $recurringTotalWithoutTaxes = $recurringTotal;
        }
        $plan = $subscription->plan();
        $mrr = $this->convertToMrr($recurringTotalWithoutTaxes, $plan);

        return [$recurringTotal, $mrr];
    }

    /**
     * Gets the normalized MRR from a line item.
     *
     * The plan and document discount are available on the model, however, they are
     * provided for better performance when this function is called frequently.
     *
     * @return Money[] MRR, discounts
     */
    public function calculateForLineItem(LineItem $lineItem, Plan $plan, ReceivableDocument $document, Money $documentDiscount): array
    {
        $mrr = Money::fromDecimal($document->currency, $lineItem->amount);

        // Attribute % of document discount to line item
        $discounts = $documentDiscount;
        if (!$documentDiscount->isZero() && $document->subtotal > 0) {
            $percent = $lineItem->amount / $document->subtotal;
            $discounts = $documentDiscount->toDecimal() * $percent;
            $discounts = Money::fromDecimal($document->currency, $discounts);
        }

        // Calculate discounts applied to line item
        foreach ($lineItem->discounts as $discount) {
            $lineDiscount = Money::fromDecimal($document->currency, $discount->amount);
            $discounts = $discounts->add($lineDiscount);
        }

        // Subtract discounts from MRR
        $mrr = $mrr->subtract($discounts);

        // Normalize according to the plan interval
        $mrr = $this->convertToMrr($mrr, $plan);
        $discounts = $this->convertToMrr($discounts, $plan);

        // Apply a proration factor to prorated line items.
        // This makes assumptions about the number of days in the full service
        // period that may not equate to the number of days in the month.
        // For example, when prorating a monthly plan this will produce a
        // slightly off result.
        // Proration Factor = prorated service period / full service period
        if ($lineItem->prorated) {
            $servicePeriodSeconds = $lineItem->period_end - $lineItem->period_start;
            $fullServicePeriodSeconds = $plan->interval()->numDays() * 86400;
            $prorationFactor = min(1, $servicePeriodSeconds / $fullServicePeriodSeconds);
            $mrr = Money::fromDecimal($document->currency, $mrr->toDecimal() * $prorationFactor);
            $discounts = Money::fromDecimal($document->currency, $discounts->toDecimal() * $prorationFactor);
        }

        // Credit notes should negate MRR
        if ($document instanceof CreditNote) {
            $mrr = $mrr->negated();
            $discounts = $discounts->negated();
        }

        return [$mrr, $discounts];
    }

    /**
     * Normalizes a money amount to a monthly amount.
     *
     * @throws RuntimeException
     */
    private function convertToMrr(Money $amount, Plan $plan): Money
    {
        if ($amount->isZero()) {
            return $amount;
        }

        // Monthly subscriptions
        if (Interval::MONTH == $plan->interval) {
            return Money::fromDecimal($amount->currency, $amount->toDecimal() / $plan->interval_count);
        }

        // Yearly subscriptions
        if (Interval::YEAR == $plan->interval) {
            return Money::fromDecimal($amount->currency, $amount->toDecimal() / 12 / $plan->interval_count);
        }

        // Daily subscriptions
        if (Interval::DAY == $plan->interval) {
            return Money::fromDecimal($amount->currency, $amount->toDecimal() / $plan->interval_count * (365 / 12));
        }

        // Weekly subscriptions
        if (Interval::WEEK == $plan->interval) {
            return Money::fromDecimal($amount->currency, $amount->toDecimal() / $plan->interval_count * (52 / 12));
        }

        throw new RuntimeException('Invalid plan interval: '.$plan->interval);
    }
}
