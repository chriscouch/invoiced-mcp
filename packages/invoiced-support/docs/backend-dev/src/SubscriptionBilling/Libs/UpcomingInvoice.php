<?php

namespace App\SubscriptionBilling\Libs;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Exception\InvoiceCalculationException;
use App\AccountsReceivable\Libs\InvoiceCalculator;
use App\AccountsReceivable\Libs\PaymentTermsFactory;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\LineItem;
use App\SalesTax\Exception\TaxCalculationException;
use App\SubscriptionBilling\Exception\PricingException;
use App\SubscriptionBilling\Models\PendingLineItem;
use App\SubscriptionBilling\Models\Subscription;

class UpcomingInvoice
{
    private ?Subscription $subscription;
    private bool $subscriptionCalculated = false;
    /** @var PendingLineItem[] */
    private array $pendingLineItems = [];

    public function __construct(private Customer $customer)
    {
        // use the company's timezone for php date/time functions
        $customer->tenant()->useTimezone();
    }

    /**
     * Gets the customer.
     */
    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    /**
     * Sets the subscription to build the upcoming invoice off of.
     * NOTE: assumes the subscription belongs to the customer.
     *
     * @return $this
     */
    public function setSubscription(Subscription $subscription)
    {
        $this->subscription = $subscription;

        return $this;
    }

    /**
     * Calculates the upcoming invoice wihtout a subscription.
     *
     * @return $this
     */
    public function withoutSubscription()
    {
        $this->subscriptionCalculated = true;
        $this->subscription = null;

        return $this;
    }

    /**
     * Gets the subscription referenced by this upcoming invoice. If no
     * subscription has been specified then this will get the subscription
     * that renews next.
     */
    public function getSubscription(): ?Subscription
    {
        if (!$this->customer->tenant()->features->has('subscription_billing')) {
            return null;
        }

        if (!isset($this->subscription) && !$this->subscriptionCalculated) {
            $this->subscription = Subscription::where('customer', $this->customer->id())
                ->where('canceled', false)
                ->where('finished', false)
                ->where('renews_next', 0, '>')
                ->where('cancel_at_period_end', false)
                ->sort('renews_next asc')
                ->oneOrNull();

            $this->subscriptionCalculated = true;
        }

        return $this->subscription;
    }

    /**
     * Sets the pending line items to be used with this preview.
     *
     * @param PendingLineItem[] $pendingLineItems
     */
    public function setPendingLineItems(array $pendingLineItems): void
    {
        $this->pendingLineItems = $pendingLineItems;
    }

    /**
     * Builds and calculates the user's upcoming invoice.
     *
     * @throws InvoiceCalculationException|TaxCalculationException|PricingException
     */
    public function build(): Invoice
    {
        // Only perform a tax preview if the customer is persisted OR has an address.
        $withTaxPreview = $this->customer->persisted() || $this->customer->address;

        // If the upcoming invoice is tied to a subscription then
        // we will use the generated subscription invoice. Otherwise
        // the upcoming invoice only includes pending line items.
        $subscription = $this->getSubscription();
        $hasBillingCyclesLeft = false;
        if ($subscription) {
            if (Subscription::BILL_IN_ARREARS == $subscription->bill_in) {
                $hasBillingCyclesLeft = !$subscription->cancel_at_period_end || ($subscription->renews_next <= $subscription->contract_period_end);
            } else {
                $hasBillingCyclesLeft = !$subscription->cancel_at_period_end || ($subscription->renews_next < $subscription->contract_period_end);
            }
        }

        if ($hasBillingCyclesLeft && $subscription?->renews_next > 0) {
            $invoice = $this->buildSubscriptionInvoice($subscription, $withTaxPreview);
        } else {
            $invoice = $this->buildPendingInvoice($withTaxPreview);
        }

        return $this->calculateInvoice($invoice);
    }

    /**
     * Builds an invoice from a subscription.
     *
     * @throws TaxCalculationException|PricingException
     */
    private function buildSubscriptionInvoice(Subscription $subscription, bool $withTaxPreview): Invoice
    {
        $subscriptionInvoice = new SubscriptionInvoice($subscription);
        if ($withTaxPreview) {
            $invoice = $subscriptionInvoice->buildWithTaxPreview($this->pendingLineItems);
        } else {
            $invoice = $subscriptionInvoice->build($this->pendingLineItems);
        }

        // add pending credits
        $invoice->items = array_merge(
            (array) $invoice->items,
            $invoice->getPendingCredits()
        );

        return $invoice;
    }

    /**
     * Builds an invoice from pending line items.
     *
     * @throws TaxCalculationException when sales tax cannot be calculated
     */
    private function buildPendingInvoice(bool $withTaxPreview): Invoice
    {
        $pendingItemInvoice = new PendingItemInvoice($this->customer);

        return $pendingItemInvoice->build($withTaxPreview, $this->pendingLineItems);
    }

    /**
     * Calculates an invoice.
     *
     * @throws InvoiceCalculationException
     */
    private function calculateInvoice(Invoice $invoice): Invoice
    {
        // inherit customer's AutoPay setting and payment terms
        $invoice->autopay = $this->customer->autopay;
        $invoice->payment_terms = $this->customer->payment_terms;

        // calculate due date
        if ($invoice->date && $invoice->payment_terms) {
            $terms = PaymentTermsFactory::get($invoice->payment_terms);
            $date = 'now' == $invoice->date ? null : $invoice->date;
            $invoice->due_date = $terms->getDueDate($date);
        }

        // set invoice status to draft
        $invoice->status = InvoiceStatus::Draft->value;
        $invoice->draft = true;
        $invoice->closed = false;
        $invoice->paid = false;

        // calculate totals
        $calculatedInvoice = InvoiceCalculator::calculate($invoice->currency, $invoice->items(), $invoice->discounts(), $invoice->taxes());

        $invoice->discounts = $calculatedInvoice->discounts;
        $invoice->taxes = $calculatedInvoice->taxes;
        $invoice->subtotal = $calculatedInvoice->subtotal;
        $invoice->total = $calculatedInvoice->total;
        $invoice->balance = 0;

        // build line item objects
        $items = [];
        foreach ($calculatedInvoice->items as $values) {
            $items[] = new LineItem($values);
        }
        $invoice->items = $items;

        return $invoice;
    }
}
