<?php

namespace App\Integrations\ChartMogul\Syncs;

use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\LineItem;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\InfuseUtility as Utility;
use App\Core\Utils\ModelUtility;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\ChartMogul\Models\ChartMogulAccount;
use App\SubscriptionBilling\Models\Subscription;
use ChartMogul\CustomerInvoices;
use ChartMogul\Exceptions\ChartMogulException;
use ChartMogul\Invoice as ChartMogulInvoice;
use ChartMogul\LineItems\AbstractLineItem;
use ChartMogul\LineItems\OneTime;
use ChartMogul\LineItems\Subscription as SubscriptionLineItem;
use ChartMogul\Plan as ChartMogulPlan;
use ChartMogul\Resource\Collection;
use ChartMogul\Transactions\Payment;

abstract class DocumentSync extends AbstractSync
{
    /**
     * Gets the class name of the document model.
     */
    abstract protected function getModelClass(): string;

    /**
     * Gets the date a document was marked as paid. This will
     * only be called if it is already known that a document was paid.
     */
    abstract protected function getDatePaid(ReceivableDocument $document): int;

    public function sync(ChartMogulAccount $account): void
    {
        /** @var ReceivableDocument $modelClass */
        $modelClass = $this->getModelClass();
        $this->logger->info('Syncing '.$modelClass::modelName().' records to ChartMogul');

        // Sync each document from Invoiced that has been updated since last sync
        $query = $modelClass::where('updated_at', Utility::unixToDb($account->sync_cursor), '>')
            ->where('draft', false)
            ->where('voided', false);
        $documents = ModelUtility::getAllModelsGenerator($query);
        /** @var ReceivableDocument $document */
        foreach ($documents as $document) {
            try {
                $this->syncDocument($document, $account);
            } catch (ChartMogulException $e) {
                // If the document cannot be synced because it already exists then we can
                // ignore that error message and continue the sync.
                if (str_contains($e->getMessage(), '"taken"')) {
                    continue;
                }

                throw new SyncException($document->number.': '.$e->getMessage(), $e->getCode(), $e);
            }
        }

        // Clear cache once complete
        $this->clearCache();
    }

    /**
     * Builds a ChartMogul invoice from an Invoiced document.
     */
    public function buildDocumentParams(ReceivableDocument $document, ChartMogulAccount $account): ChartMogulInvoice
    {
        // Calculate subtotal discounts
        $currency = $document->currency;
        $totalDiscount = new Money($currency, 0);
        foreach ($document->discounts() as $discount) {
            $totalDiscount = $totalDiscount->add(Money::fromDecimal($currency, $discount['amount']));
        }

        // Determine subtotal amount subjected to discounts.
        // Some line items might not be discountable.
        $discountableSubtotal = new Money($currency, 0);
        $items = $document->items;
        foreach ($items as $item) {
            if ($item->discountable) {
                $discountableSubtotal = $discountableSubtotal->add(Money::fromDecimal($currency, $item->amount));
            }
        }

        // Build line items
        $lineItems = [];
        foreach ($items as $idx => $item) {
            $lineItems[] = $this->buildLineItem($document, $idx, $item, $totalDiscount, $discountableSubtotal, $account);
        }

        $customer = $document->customer();
        $documentDate = $this->timestampToDate($document->date);
        if ($document instanceof Invoice && $document->due_date) {
            $dueDate = $this->timestampToDate($document->due_date);
        } else {
            $dueDate = null;
        }

        return new ChartMogulInvoice([
            'external_id' => $document->number,
            'date' => $documentDate,
            'currency' => strtoupper($currency),
            'due_date' => $dueDate,
            'customer_external_id' => (string) $customer->id,
            'data_source_uuid' => $account->data_source,
            'line_items' => $lineItems,
            'transactions' => $this->buildPayments($document),
        ]);
    }

    /**
     * Builds a ChartMogul line item from an Invoiced line item.
     */
    private function buildLineItem(ReceivableDocument $document, int $idx, LineItem $lineItem, Money $totalDiscount, Money $discountableSubtotal, ChartMogulAccount $account): AbstractLineItem
    {
        // Determines the subtotal discount attributable to this line
        $currency = $document->currency;
        $lineDiscount = new Money($currency, 0);
        if ($totalDiscount->isPositive() and $lineItem->discountable) {
            $lineDiscount = Money::fromDecimal($currency, $totalDiscount->toDecimal() * $lineItem->amount / $discountableSubtotal->toDecimal());
        }

        // Add line item discounts
        foreach ($lineItem->discounts as $discount) {
            $lineDiscount = $lineDiscount->add(Money::fromDecimal($currency, $discount['amount']));
        }

        $lineTotal = Money::fromDecimal($currency, $lineItem->amount);

        if ($lineItem->subscription_id && $planId = $this->getPlan($lineItem, $account)) {
            return new SubscriptionLineItem([
                'external_id' => (string) $lineItem->id,
                // ChartMogul subscriptions can only have a single plan so we
                // have to use subscription sets to group multi-line subscriptions.
                // We also use the line item index to generate a unique subscription external ID.
                'subscription_external_id' => $lineItem->subscription_id.'-'.$idx,
                'subscription_set_external_id' => $lineItem->subscription_id,
                'plan_uuid' => $planId,
                'service_period_start' => $this->timestampToDate($lineItem->period_start ?? $document->date),
                'service_period_end' => $this->timestampToDate($lineItem->period_end ?? $document->date + 86400 * 30),
                'amount_in_cents' => $lineTotal->subtract($lineDiscount)->amount, // ChartMogul expects the line total after discounts
                'discount_amount_in_cents' => $lineDiscount->amount,
                'quantity' => max(1, round($lineItem->quantity)),
                'description' => $lineItem->name,
                'prorated' => $lineItem->prorated,
            ]);
        }

        // Non-subscription line items with a service period are a special case.
        // They are not actual subscriptions on Invoiced but we want to treat them
        // like a subscription on ChartMogul.
        if (!$lineItem->subscription_id && $lineItem->period_start && $lineItem->period_end && $planId = $this->getPlan($lineItem, $account)) {
            $customer = $document->customer();

            return new SubscriptionLineItem([
                'external_id' => (string) $lineItem->id,
                'amount_in_cents' => $lineTotal->subtract($lineDiscount)->amount, // ChartMogul expects the line total after discounts
                'discount_amount_in_cents' => $lineDiscount->amount,
                'quantity' => max(1, round($lineItem->quantity)),
                'description' => $lineItem->name,
                // ChartMogul subscriptions can only have a single plan so we
                // have to use subscription sets to group multi-line subscriptions.
                // We also use the line item index to generate a unique subscription external ID.
                'subscription_external_id' => $customer->number.'-'.$idx,
                'subscription_set_external_id' => $customer->number,
                'plan_uuid' => $planId,
                'service_period_start' => $this->timestampToDate($lineItem->period_start),
                'service_period_end' => $this->timestampToDate($lineItem->period_end),
                'prorated' => $lineItem->prorated,
            ]);
        }

        // Add a one-off line item
        return new OneTime([
            'external_id' => (string) $lineItem->id,
            'description' => $lineItem->name,
            'amount_in_cents' => $lineTotal->subtract($lineDiscount)->amount,
            'discount_amount_in_cents' => $lineDiscount->amount,
            'quantity' => max(1, round($lineItem->quantity)),
        ]);
    }

    /**
     * Retrieves or creates a plan from ChartMogul given
     * an Invoiced line item.
     */
    private function getPlan(LineItem $lineItem, ChartMogulAccount $account): ?string
    {
        $plan = $lineItem->plan();
        $planId = $lineItem->plan;

        // This is a legacy configuration
        if (!$plan) {
            $item = $lineItem->item();
            if (!$item) {
                return null;
            }

            $subscription = Subscription::where('id', $lineItem->subscription_id)->oneOrNull();
            if (!$subscription) {
                return null;
            }

            $plan = $subscription->plan();
            $planId = $lineItem->catalog_item;
        }

        if (isset(self::$plans[$planId])) {
            return self::$plans[$planId];
        }

        // Look for an existing plan on ChartMogul
        /** @var Collection $result */
        $result = ChartMogulPlan::all([
            'data_source_uuid' => $account->data_source,
            'external_id' => $planId,
        ]);

        if (1 == count($result)) {
            $planUuid = $result[0]->uuid;
            self::$plans[$planId] = $planUuid;

            return $planUuid;
        }

        // Create a new plan on ChartMogul
        $result = ChartMogulPlan::create([
            'data_source_uuid' => $account->data_source,
            'name' => $lineItem->name,
            'interval_count' => $plan->interval_count,
            'interval_unit' => $plan->interval,
            'external_id' => $planId,
        ]);
        $planUuid = $result->uuid;
        self::$plans[$planId] = $planUuid;

        return $planUuid;
    }

    private function buildPayments(ReceivableDocument $document, ?ChartMogulInvoice $invoice = null): array
    {
        // ChartMogul only supports a paid in full/not paid status.
        // This means that partial payments are not supported and
        // will be treated as unpaid.

        // ChartMogul also supports failed payments which we are not
        // currently syncing.
        if (!$document instanceof Invoice || !$document->paid) {
            return [];
        }

        // If we are syncing an existing ChartMogul invoice then
        // check if it has already been marked as paid. if so, then
        // do not record any payments for this document.
        if ($invoice) {
            foreach ($invoice->transactions as $transaction) {
                if ('successful' == $transaction->result) {
                    return [];
                }
            }
        }

        $params = [
            'date' => $this->timestampToDate($this->getDatePaid($document)),
            'type' => 'payment',
            'result' => 'successful',
        ];

        if ($invoice) {
            $params['invoice_uuid'] = $invoice->uuid;
        }

        return [
            new Payment($params),
        ];
    }

    /**
     * Syncs a document to ChartMogul.
     */
    private function syncDocument(ReceivableDocument $document, ChartMogulAccount $account): void
    {
        // Skip documents that do not have line items because we
        // permit this but ChartMogul does not.
        if (0 == count($document->items)) {
            return;
        }

        // ChartMogul does not support updating invoices
        if ($invoice = $this->lookupInvoice($document->number, $account)) {
            // record any payments that might have happened since the last sync
            $payments = $this->buildPayments($document, $invoice);
            foreach ($payments as $payment) {
                Payment::create($payment->toArray());
            }

            return;
        }

        $this->createInvoice($document, $account);
    }

    /**
     * Creates an invoice on ChartMogul.
     */
    private function createInvoice(ReceivableDocument $document, ChartMogulAccount $account): void
    {
        // Retrieve the customer
        $customer = $this->lookupCustomer($document->customer);
        if (!$customer) {
            return;
        }

        // Build the document
        $newInvoice = $this->buildDocumentParams($document, $account);

        CustomerInvoices::create([
            'customer_uuid' => $customer->uuid,
            'invoices' => [$newInvoice],
        ]);
    }

    /**
     * Looks up an invoice by its invoice # on ChartMogul.
     */
    private function lookupInvoice(string $number, ChartMogulAccount $account): ?ChartMogulInvoice
    {
        /** @var Collection $result */
        $result = ChartMogulInvoice::all([
            'data_source_uuid' => $account->data_source,
            'external_id' => $number,
        ]);

        return count($result) > 0 ? $result[0] : null;
    }
}
