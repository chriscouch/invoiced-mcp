<?php

namespace App\SubscriptionBilling\Libs;

use App\AccountsReceivable\Exception\InvoiceCalculationException;
use App\AccountsReceivable\Libs\InvoiceCalculator;
use App\AccountsReceivable\Models\Invoice;
use App\Core\I18n\AddressFormatter;
use App\Core\I18n\MoneyFormatter;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\Enums\ObjectType;
use App\Metadata\Libs\CustomFieldRepository;
use App\SalesTax\Exception\TaxCalculationException;
use App\SalesTax\Libs\TaxCalculatorFactoryFacade;
use App\SalesTax\Models\TaxRate;
use App\SalesTax\ValueObjects\SalesTaxInvoice;
use App\SalesTax\ValueObjects\SalesTaxInvoiceItem;
use App\SubscriptionBilling\Exception\PricingException;
use App\SubscriptionBilling\Models\PendingLineItem;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\Models\Subscription;
use Carbon\CarbonImmutable;
use CommerceGuys\Addressing\Address;

/**
 * This class builds the invoices for a given subscription. The
 * output will from this class will be the next invoice in the series.
 * The invoices are not persisted here. This only performs the calculations
 * and constructs the Invoice model.
 */
final class SubscriptionInvoice
{
    private Plan $plan;

    public function __construct(private Subscription $subscription)
    {
    }

    /**
     * Gets the subscription.
     */
    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    /**
     * Builds the next invoice in the subscription series.
     * This will include any pending line items.
     *
     * @param PendingLineItem[]|null $pendingLineItems
     *
     * @throws TaxCalculationException|PricingException
     */
    public function build(?array $pendingLineItems = null): Invoice
    {
        $invoice = new Invoice();
        $invoice->tenant_id = $this->subscription->tenant_id;
        foreach ($this->getInvoiceParameters(false, true) as $k => $v) {
            $invoice->$k = $v;
        }

        $invoice->setRelation('customer', $this->subscription->customer());
        $invoice->setRelation('subscription_id', $this->subscription);

        if ($pendingLineItems) {
            $invoice->setPendingLineItems($pendingLineItems);
        }
        $invoice->withPending();

        // inherit any subscription payment source
        if ($paymentSource = $this->subscription->payment_source) {
            $invoice->setPaymentSource($paymentSource);
        }

        return $invoice;
    }

    /**
     * Builds the next invoice in the subscription series.
     * This will include any pending line items AND a tax preview.
     *
     * @param PendingLineItem[]|null $pendingLineItems
     *
     * @throws TaxCalculationException|PricingException
     */
    public function buildWithTaxPreview(?array $pendingLineItems = null): Invoice
    {
        $invoice = $this->build($pendingLineItems);

        // perform any tax calculations here so that they include pending line items
        try {
            $calculatedInvoice = InvoiceCalculator::prepare($invoice->currency, $invoice->items, $invoice->discounts, $invoice->taxes);
            InvoiceCalculator::calculateInvoice($calculatedInvoice);
        } catch (InvoiceCalculationException $e) {
            throw new TaxCalculationException($e->getMessage());
        }

        $salesTaxInvoice = $invoice->toSalesTaxDocument($calculatedInvoice, true);
        $taxes = $invoice->getSalesTaxCalculator()->assess($salesTaxInvoice);

        $invoice->taxes = array_merge(
            $taxes,
            $this->subscription->taxes
        );

        return $invoice;
    }

    /**
     * Builds the invoice properties for the next subscription invoice
     * in the series.
     *
     * @throws TaxCalculationException|PricingException
     */
    public function getInvoiceParameters(bool $withTaxPreview, bool $withProrations): array
    {
        $this->plan = $this->subscription->plan();

        // shipping address
        if ($shipTo = $this->subscription->ship_to) {
            $shipTo = $shipTo->makeCopy();
        } else {
            $shipTo = null;
        }

        // calculate the current billing period
        $billingPeriods = $this->subscription->billingPeriods();
        $billingPeriod = $billingPeriods->forUpcomingInvoice();

        // prorate the invoice if this is the first in the billing cycle
        // and there is a partial billing period
        $prorated = $withProrations && $this->isProrated($billingPeriod->startDate);
        $proratePercent = 1;
        if ($prorated) {
            $proratePercent = $billingPeriods->percentTimeRemaining($billingPeriod->startDate);
        }

        // apply discounts
        $discounts = [];
        $totalDiscounts = Money::fromDecimal($this->plan->currency, 0);
        foreach ($this->subscription->couponRedemptions() as $redemption) {
            $coupon = $redemption->coupon();
            if ($coupon->is_percent || !$prorated) {
                $discounts[] = [
                    'coupon' => $coupon->toArray(),
                    'rate' => $coupon->id,
                    'rate_id' => $coupon->id(),
                ];
                $amount = $coupon->value;
            } else {
                $amount = round($coupon->value * $proratePercent, 4);
                $discounts[] = [
                    'amount' => $amount,
                ];
            }
            $totalDiscounts = $coupon->is_percent
                ? Money::fromDecimal($this->plan->currency, (float) $this->plan->amount)->percent($amount)
                : Money::fromDecimal($this->plan->currency, $amount);
        }

        // line items
        $lineItems = $this->buildLineItems($billingPeriod->startDate, $billingPeriod->endDate, (int) $this->subscription->id(), $proratePercent);

        // add in taxes
        $company = $this->subscription->tenant();
        if ($withTaxPreview) {
            $customer = $this->subscription->customer();
            $address = $this->getSalesTaxAddress();

            $taxLineItems = [];
            $moneyFormatter = MoneyFormatter::get();
            foreach ($lineItems as $item) {
                $amount = $moneyFormatter->normalizeToZeroDecimal($this->plan->currency, $item['quantity'] * $item['unit_cost']);
                $itemCode = $item['catalog_item'] ?? null;
                $discountable = $item['discountable'] ?? true;
                $taxLineItems[] = new SalesTaxInvoiceItem($item['name'], $item['quantity'], $amount, $itemCode, $discountable);
            }
            $salesTaxInvoice = new SalesTaxInvoice($customer, $address, $this->plan->currency, $taxLineItems, [
                'preview' => true,
                'discounts' => $totalDiscounts->amount,
            ]);

            $taxes = TaxCalculatorFactoryFacade::get()->get($company)->assess($salesTaxInvoice);

            $taxes = array_merge(
                $taxes,
                $this->subscription->taxes
            );
        } else {
            // The subscription taxes have to be added here
            // even if sales tax preview is disabled or else
            // they will not be included on the generated invoice.
            $taxes = $this->subscription->taxes;
        }

        // inherit metadata
        $invoiceMetadata = [];
        $repository = new CustomFieldRepository($company);
        foreach ((array) $this->subscription->metadata as $k => $v) {
            $customField = $repository->getCustomField(ObjectType::Invoice->typeName(), $k);
            if ($customField) {
                $invoiceMetadata[$k] = $v;
            }
        }

        return [
            'subscription_id' => $this->subscription->id(),
            'customer' => $this->subscription->customer,
            'ship_to' => $shipTo,
            'name' => $this->plan->name,
            'currency' => $this->plan->currency,
            'date' => $billingPeriod->getBillDateTimestamp(),
            'items' => $lineItems,
            'discounts' => $discounts,
            'taxes' => $taxes,
            'notes' => $this->plan->notes,
            'metadata' => (object) $invoiceMetadata,
            'draft' => $company->subscription_billing_settings->subscription_draft_invoices,
        ];
    }

    /**
     * Calculates the total for a single cycle of the subscription.
     *
     * @throws TaxCalculationException|InvoiceCalculationException|PricingException
     */
    public function getRecurringTotal(bool $withTaxes = true): Money
    {
        // build the invoice without prorations
        $params = $this->getInvoiceParameters($withTaxes, false);

        // reset the taxes because even with tax preview disabled
        // getInvoiceParameters() can return taxes
        if (!$withTaxes && !$this->subscription->preserveTaxes) {
            // when tax inclusive pricing is used then a special variation is required
            // that deducts tax from the total in order to get an accurate total
            if ($this->hasTaxInclusivePricing()) {
                $calculatedInvoice = InvoiceCalculator::calculate($params['currency'], $params['items'], $params['discounts'], $params['taxes']);

                return Money::fromDecimal($params['currency'], $calculatedInvoice->total - $calculatedInvoice->totalTaxes);
            }

            $params['taxes'] = [];
        }

        // and, compute!
        $calculatedInvoice = InvoiceCalculator::calculate($params['currency'], $params['items'], $params['discounts'], $params['taxes']);

        return Money::fromDecimal($params['currency'], $calculatedInvoice->total);
    }

    /**
     * Checks if a subscription has tax inclusive pricing.
     */
    private function hasTaxInclusivePricing(): bool
    {
        $taxRates = TaxRate::expandList((array) $this->subscription->taxes);
        foreach ($taxRates as $taxRate) {
            if ($taxRate['inclusive']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the subscription should be prorated. This would
     * only be used for prorating a new subscription mid-cycle
     * when calendar billing is enabled. This does not determine if
     * prorations are needed for changes to a subscription mid-cycle.
     */
    public function isProrated(?CarbonImmutable $periodStart = null): bool
    {
        $periodStart ??= new CarbonImmutable();

        if (!$this->subscription->prorate) {
            return false;
        }

        // can only prorate the first billing cycle
        if ($this->subscription->renewed_last) {
            return false;
        }

        $nthDay = $this->subscription->snap_to_nth_day;
        if (!$nthDay) {
            return false;
        }

        // do not prorate if we are already on the Nth day of the cycle
        $interval = $this->subscription->plan()->interval();
        $snapper = new DateSnapper($interval);
        if ($snapper->isNthDay($nthDay, $periodStart)) {
            return false;
        }

        return true;
    }

    /**
     * Gets the shipping address for tax calculation purposes.
     */
    public function getSalesTaxAddress(): Address
    {
        $formatter = new AddressFormatter();

        // look for a shipping address
        if ($shippingDetail = $this->subscription->ship_to) {
            return $formatter->setFrom($shippingDetail)->buildAddress(false);
        }

        // otherwise fallback to the billing address
        $customer = $this->subscription->customer();
        $formatter->setFrom($customer);

        return $formatter->buildAddress(false);
    }

    /**
     * Builds the invoice line items.
     *
     * @throws PricingException
     */
    private function buildLineItems(?CarbonImmutable $start, ?CarbonImmutable $end, int $subscriptionId, float $proratePercent): array
    {
        $ratingEngine = new PricingEngine();

        // add the base line item from the plan
        // taking into account tiered and volume pricing
        $items = $ratingEngine->price($this->plan, $this->subscription->quantity, $this->subscription->amount);

        if ($description = $this->subscription->description) {
            foreach ($items as &$item) {
                $item['description'] = trim($item['description']."\n".$description);
            }
        }

        // add the subscription addons as line items
        foreach ($this->subscription->getAddons() as $addon) {
            if (Subscription::BILL_IN_ARREARS != $this->subscription->bill_in) {
                $items = array_merge($items, $addon->lineItems());
                continue;
            }

            // When billing in arrears, any edits to the subscription during the billing cycle
            // should be reflected at the end of the cycle when the invoice is billed. Hence,
            // we only want to include the proration for any addons on the next invoice.
            // The reason both the addon line item and its proration are included when billing in advance
            // is that the proration is intended to account for the current billing cycle. However,
            // the invoice for the billing cycle has already been issued and cannot be modified.
            $hasProration = PendingLineItem::where('subscription_id', $this->subscription->id)
                ->where('plan_id', $addon->plan_id)
                ->where('prorated', true)
                ->count() > 0;
            if (!$hasProration) {
                $items = array_merge($items, $addon->lineItems());
            }
        }

        $prorated = 1 != $proratePercent;

        foreach ($items as &$item) {
            $item['subscription_id'] = $subscriptionId;
            $item['period_start'] = $start ? $start->getTimestamp() : null;
            $item['period_end'] = $end ? $end->getTimestamp() : null;
            $item['prorated'] = $prorated;

            // prorate the subscription if this is the first billing
            // cycle of a subscription with calendar billing
            if ($prorated) {
                $item['quantity'] = round($item['quantity'] * $proratePercent, 4);
            }
        }

        return $items;
    }
}
