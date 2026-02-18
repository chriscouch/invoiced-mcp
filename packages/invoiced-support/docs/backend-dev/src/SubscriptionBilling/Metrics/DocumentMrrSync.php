<?php

namespace App\SubscriptionBilling\Metrics;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\LineItem;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\CurrencyExchangerFactory;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Ledger\Repository\CurrencyRepository;
use App\SubscriptionBilling\Models\MrrItem;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\ValueObjects\MrrCalculationState;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DeadlockException;
use Exchanger\CurrencyPair;
use Exchanger\Exchanger;

class DocumentMrrSync
{
    private MrrCalculator $mrrCalculator;
    private Exchanger $exchanger;

    public function __construct(
        private Connection $database,
        private CurrencyExchangerFactory $exchangerFactory,
        private CurrencyRepository $currencyRepository,
    ) {
        $this->mrrCalculator = new MrrCalculator();
        $this->exchanger = $this->exchangerFactory->make();
    }

    /**
     * Syncs all documents of the given type into MRR items. This function
     * will only process documents updated after the sync cursor in the given state.
     *
     * @param class-string<ReceivableDocument> $modelClass
     */
    public function sync(MrrCalculationState $state, string $modelClass): void
    {
        $state->output->writeln('Syncing '.$modelClass::modelName().' records');

        // Sync each document that has been updated since last sync
        $syncCursor = $state->version->last_updated ?? CarbonImmutable::createFromTimestamp(0);
        $query = $modelClass::where('updated_at', $syncCursor->format('Y-m-d H:i:s'), '>')
            ->where('draft', false)
            ->sort('id ASC');

        $hasMore = true;
        $lastId = 0;
        $perPage = 100;
        $n = 0;
        while ($hasMore) {
            $query2 = clone $query;
            $documents = $query2->where('id', $lastId, '>')->first($perPage);
            /** @var ReceivableDocument $document */
            foreach ($documents as $document) {
                try {
                    $this->syncDocument($document, $state);
                    ++$n;
                } catch (DeadlockException) {
                    // do nothing
                }
            }

            $hasMore = count($documents) == $perPage;
            $lastId = $hasMore ? $documents[$perPage - 1]->id : 0;
        }

        $state->output->writeln('Synced '.$n.' '.$modelClass::modelName().' records');
    }

    private function syncDocument(ReceivableDocument $document, MrrCalculationState $state): void
    {
        // calculate the discount on the document
        $totalDiscount = Money::zero($document->currency);
        foreach ($document->discounts() as $discount) {
            $totalDiscount = $totalDiscount->add(Money::fromDecimal($document->currency, $discount['amount']));
        }

        // Handle voids by deleting any previous MRR components
        if ($document->voided) {
            $documentColumn = $document->object.'_id';
            $this->database->delete('MrrItems', [
                'version_id' => $state->version->id,
                $documentColumn => $document->id,
            ]);

            return;
        }

        foreach ($document->items as $lineItem) {
            $this->database->transactional(function () use ($document, $state, $lineItem, $totalDiscount) {
                $this->syncLineItem($document, $state, $lineItem, $totalDiscount);
            });
        }
    }

    /**
     * Adds the MRR for a line item.
     */
    private function syncLineItem(ReceivableDocument $document, MrrCalculationState $state, LineItem $lineItem, Money $totalDiscount): void
    {
        // find the plan
        $plan = $this->getPlan($state, $lineItem->plan_id);
        if (!$plan) {
            return;
        }

        // delete all entries for the line item
        $this->database->delete('MrrItems', [
            'version_id' => $state->version->id,
            'line_item_id' => $lineItem->id,
        ]);

        // Calculate MRR and discount attributed to this line item.
        [$lineMrr, $lineDiscount] = $this->mrrCalculator->calculateForLineItem($lineItem, $plan, $document, $totalDiscount);

        // Convert to the base currency
        $transactionDate = CarbonImmutable::createFromTimestamp($document->date);
        $lineMrr = $this->convertCurrency($state->version->currency, $transactionDate, $lineMrr);
        $lineDiscount = $this->convertCurrency($state->version->currency, $transactionDate, $lineDiscount);

        // Determine the period start for this line item.
        // Falls back to invoice date as period start
        // in case the period start is not set on the line item.
        $planInterval = $plan->interval();
        $periodStart = CarbonImmutable::createFromTimestamp($lineItem['period_start'] ?? $document->date);
        $periodEnd = CarbonImmutable::createFromTimestamp($lineItem['period_end'] ?? $planInterval->addTo($periodStart->getTimestamp()));

        // Delete any incomplete MRR component for this subscription
        // because it is no longer needed when there is a renewal. This
        // is done to prevent double counting MRR.
        $this->database->delete('MrrItems', [
            'version_id' => $state->version->id,
            'customer_id' => $document->customer,
            'subscription_id' => $lineItem->subscription_id,
            'partial_month' => true,
        ]);

        // Create the MRR component.
        $this->createMrrItem($periodStart, $state, $lineItem, $document, $lineMrr, $lineDiscount, false);

        // Track MRR in any other month that the period end might stretch into.
        // This will be tracked as prepaid revenue.
        $monthStart = $periodStart->addMonthNoOverflow()->startOfMonth();
        $nextPeriodStart = $periodStart->addMonthNoOverflow();
        while ($monthStart->lessThanOrEqualTo($periodEnd)) {
            $nextPeriodEnd = $monthStart->endOfMonth()->min($periodEnd);
            $partialMonth = $nextPeriodEnd->notEqualTo($nextPeriodEnd->endOfMonth());
            $this->createMrrItem($nextPeriodStart, $state, $lineItem, $document, $lineMrr, $lineDiscount, $partialMonth);
            $monthStart = $monthStart->addMonthNoOverflow()->startOfMonth();
            $nextPeriodStart = $nextPeriodStart->addMonthNoOverflow()->min($periodEnd);
        }
    }

    private function getPlan(MrrCalculationState $state, ?int $id): ?Plan
    {
        if (!$id) {
            return null;
        }

        if (!$state->hasPlan($id) && $plan = Plan::find($id)) {
            $state->setPlan($id, $plan);
        }

        return $state->getPlan($id);
    }

    public function convertCurrency(string $baseCurrency, CarbonImmutable $date, Money $amount): Money
    {
        $pair = new CurrencyPair(strtoupper($amount->currency), strtoupper($baseCurrency));
        $exchangeRate = $this->currencyRepository->getExchangeRate($this->exchanger, $pair, $date);

        return new Money($baseCurrency, (int) round($amount->amount * $exchangeRate->getValue()));
    }

    private function createMrrItem(CarbonImmutable $date, MrrCalculationState $state, LineItem $lineItem, ReceivableDocument $document, Money $mrr, Money $discount, bool $partialMonth): void
    {
        // Do not record $0 line items as MRR
        if ($mrr->isZero()) {
            return;
        }

        $movement = new MrrItem();
        $movement->version = $state->version;
        $movement->line_item = $lineItem;
        $movement->customer_id = $document->customer;
        $movement->subscription_id = $lineItem->subscription_id ?? ($document instanceof Invoice ? $document->subscription_id : null);
        if ($document instanceof Invoice) {
            $movement->invoice = $document;
        } elseif ($document instanceof CreditNote) {
            $movement->credit_note = $document;
        }
        $movement->plan_id = $lineItem->plan_id;
        $movement->item_id = $lineItem->catalog_item_id;
        $movement->month = (int) $date->format('Ym');
        $movement->date = $date;
        $movement->mrr = $mrr->toDecimal();
        $movement->discount = $discount->toDecimal();
        $movement->partial_month = $partialMonth;
        $movement->saveOrFail();

        $state->setEarliestDate($date);
    }
}
