<?php

namespace App\SubscriptionBilling\Libs;

use App\AccountsReceivable\Exception\InvoiceCalculationException;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\Item;
use App\Companies\Models\Company;
use App\SalesTax\Exception\TaxCalculationException;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Exception\PricingException;
use App\SubscriptionBilling\Metrics\MrrCalculator;
use App\SubscriptionBilling\Models\PendingLineItem;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\Models\SubscriptionAddon;
use Carbon\CarbonImmutable;

final class SubscriptionPreview
{
    private string $plan;
    private float $quantity = 1;
    private array $addons = [];
    private array $pendingLineItems = [];
    private Invoice $firstInvoice;
    private float $mrr;
    private float $recurringTotal;
    private array $taxes = [];
    private array $discounts = [];
    private ?int $customer = null;
    private ?float $amount = null;
    private ?array $tiers = null;

    public function __construct(private Company $company)
    {
    }

    public function setAmount(?float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function setTiers(?array $tiers): self
    {
        $this->tiers = $tiers;

        return $this;
    }

    public function setPlan(string $plan): self
    {
        $this->plan = $plan;

        return $this;
    }

    public function setQuantity(float $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function setAddons(array $addons): self
    {
        $this->addons = $addons;

        return $this;
    }

    public function setPendingLineItems(array $pendingLineItems): self
    {
        $this->pendingLineItems = $pendingLineItems;

        return $this;
    }

    public function setTaxes(array $taxes): self
    {
        $this->taxes = $taxes;

        return $this;
    }

    public function setCustomer(?int $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function setDiscounts(array $discounts): self
    {
        $this->discounts = $discounts;

        return $this;
    }

    public function getFirstInvoice(): Invoice
    {
        return $this->firstInvoice;
    }

    public function getMrr(): float
    {
        return $this->mrr;
    }

    public function getRecurringTotal(): float
    {
        return $this->recurringTotal;
    }

    /**
     * Generates the subscription preview.
     *
     * @throws OperationException
     *
     * @return $this
     */
    public function generate(?Customer $customer = null): self
    {
        // build the subscription
        $subscription = new SubscriptionMock();
        $subscription->tenant_id = (int) $this->company->id();
        if ($customer) {
            $subscription->setCustomer($customer);
        } else {
            $subscription->lookupCustomer($this->customer);
        }

        $subscription->start_date = CarbonImmutable::now()->getTimestamp();
        $subscription->renews_next = CarbonImmutable::now()->getTimestamp();
        $subscription->taxes = $this->taxes;
        $subscription->setCouponRedemptions($this->discounts);

        // build plan
        $plan = $this->getPlan($this->plan);
        if (Plan::PRICING_CUSTOM === $plan->pricing_mode) {
            $subscription->amount = $this->amount;
            $subscription->setPlan($this->buildPlan($plan, null, null));
        } else {
            $subscription->setPlan($this->buildPlan($plan, $this->amount, $this->tiers));
        }

        if ($this->quantity > 0) {
            $subscription->quantity = $this->quantity;
        }

        // build the addons
        $addons = array_map(function ($addonEntry) {
            $planId = $addonEntry['plan'] ?? '';
            $amount = $addonEntry['amount'] ?? null;
            $tiers = $addonEntry['tiers'] ?? null;

            // build plan
            $plan = $this->getPlan($planId);
            if (Plan::PRICING_CUSTOM === $plan->pricing_mode) {
                $plan = $this->buildPlan($plan, null, null);
            } else {
                $plan = $this->buildPlan($plan, $amount, $tiers);

                // $addonEntry['amount'] should be set if the plan is custom
                // to allow the loop below to hydrate the amount value on the
                // subscription addon.
                unset($addonEntry['amount']);
            }

            unset($addonEntry['plan']);
            unset($addonEntry['tiers']);

            $addon = new SubscriptionAddon();
            $addon->setPlan($plan);
            foreach ($addonEntry as $k => $v) {
                $addon->$k = $v;
            }

            return $addon;
        }, $this->addons);

        if (count($addons) > 0) {
            $subscription->setAddons($addons);
        }

        // build pending line items
        $pendingLineItems = [];
        foreach ($this->pendingLineItems as $lineItemEntry) {
            $pendingLineItem = new PendingLineItem();

            // populate the pending line item from an item
            if (isset($lineItemEntry['catalog_item'])) {
                $item = $this->buildItem($lineItemEntry['catalog_item']);
                foreach ($item->lineItem() as $k => $v) {
                    $pendingLineItem->$k = $v;
                }
            }

            foreach ($lineItemEntry as $k => $v) {
                $pendingLineItem->$k = $v;
            }

            $pendingLineItems[] = $pendingLineItem;
        }

        // build the preview of the first invoice
        $upcomingInvoice = new UpcomingInvoice($subscription->customer());
        $upcomingInvoice->setSubscription($subscription);
        $upcomingInvoice->setPendingLineItems($pendingLineItems);

        try {
            $this->firstInvoice = $upcomingInvoice->build();

            // Only perform a tax preview if the customer is persisted.
            $withTaxPreview = $subscription->customer()->persisted();

            $calculator = new MrrCalculator();
            [$recurringTotal, $mrr] = $calculator->calculateForSubscription($subscription, $withTaxPreview);
            $this->recurringTotal = $recurringTotal->toDecimal();
            $this->mrr = $mrr->toDecimal();
        } catch (InvoiceCalculationException|TaxCalculationException|PricingException $e) {
            throw new OperationException('The subscription preview could not be generated. '.$e->getMessage());
        }

        return $this;
    }

    /**
     * @throws OperationException
     */
    private function getPlan(string $id): Plan
    {
        $plan = Plan::getLatest($id);
        if (!$plan) {
            throw new OperationException('No such plan: '.$id);
        }

        return $plan;
    }

    private function buildPlan(Plan $plan, ?float $amount, ?array $tiers): Plan
    {
        if (null !== $amount) {
            $plan->amount = $amount;
        }

        if ($tiers) {
            $plan->tiers = $tiers;
        }

        return $plan;
    }

    /**
     * @throws OperationException
     */
    private function buildItem(string $id): Item
    {
        $item = Item::getLatest($id);
        if (!$item) {
            throw new OperationException('No such item: '.$id);
        }

        return $item;
    }
}
