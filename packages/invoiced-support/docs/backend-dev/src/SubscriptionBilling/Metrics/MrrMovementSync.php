<?php

namespace App\SubscriptionBilling\Metrics;

use App\Core\I18n\ValueObjects\Money;
use App\SubscriptionBilling\Enums\MrrMovementType;
use App\SubscriptionBilling\Models\MrrItem;
use App\SubscriptionBilling\Models\MrrMovement;
use App\SubscriptionBilling\Models\MrrVersion;
use App\SubscriptionBilling\ValueObjects\MrrCalculationState;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Connection;

class MrrMovementSync
{
    public function __construct(
        private Connection $database,
    ) {
    }

    /**
     * Generates MRR movements from MRR items.
     */
    public function sync(MrrCalculationState $state): void
    {
        $month = $state->getEarliestDate();

        // No earliest date indicates that no MRR items were created.
        if (!$month) {
            $state->output->writeln('Skipping MRR movement calculation because no MRR items created');

            return;
        }

        $state->output->writeln('Calculating MRR movements starting with month '.$month->format('Y-m'));

        $currency = $state->version->currency;
        $now = CarbonImmutable::now()->endOfMonth();
        $month = $month->startOfMonth();

        while ($month->isBefore($now)) {
            $this->database->transactional(function () use ($state, $month, $currency) {
                // Clear previously calculated MRR movements for the month
                $this->database->delete('MrrMovements', [
                    'version_id' => $state->version->id,
                    'month' => $month->format('Ym'),
                ]);

                // Compare the MRR of each customer against the previous month
                // to indicate that there was an MRR movement.
                $lastMonthByCustomer = $this->getMrrByCustomer($state, $month->subMonth(), $currency);
                $thisMonthByCustomer = $this->getMrrByCustomer($state, $month, $currency);
                foreach (array_keys($lastMonthByCustomer) as $customerId) {
                    if (!isset($thisMonthByCustomer[$customerId])) {
                        $thisMonthByCustomer[$customerId] = Money::zero($currency);
                    }
                }
                foreach (array_keys($thisMonthByCustomer) as $customerId) {
                    if (!isset($lastMonthByCustomer[$customerId])) {
                        $lastMonthByCustomer[$customerId] = Money::zero($currency);
                    }
                }

                foreach ($thisMonthByCustomer as $customerId => $thisMonthCustomerMrr) {
                    $lastMonthCustomerMrr = $lastMonthByCustomer[$customerId];
                    $difference = $thisMonthCustomerMrr->subtract($lastMonthCustomerMrr);
                    // For each customer with a changed MRR, calculate the MRR movement per MRR item.
                    if (!$difference->isZero()) {
                        $this->syncCustomerMrr($state, $month, $customerId, $thisMonthCustomerMrr, $lastMonthCustomerMrr);
                    }
                }
            });

            $month = $month->addMonth();
        }

        $state->output->writeln('Synced MRR movements');
    }

    private function getMrrByCustomer(MrrCalculationState $state, CarbonImmutable $month, string $currency): array
    {
        if ($state->hasMrrByCustomer($month)) {
            return $state->getMrrByCustomer($month);
        }

        $data = $this->database->fetchAllAssociative('SELECT customer_id,SUM(mrr) AS mrr FROM MrrItems WHERE version_id=:versionId AND month=:month AND partial_month=0 GROUP BY customer_id', [
            'versionId' => $state->version->id,
            'month' => (int) $month->format('Ym'),
        ]);

        $result = [];
        foreach ($data as $row) {
            $result[$row['customer_id']] = Money::fromDecimal($currency, $row['mrr']);
        }

        $state->setMrrByCustomer($month, $result);

        return $result;
    }

    private function syncCustomerMrr(MrrCalculationState $state, CarbonImmutable $month, int $customerId, Money $thisMonthCustomerMrr, Money $lastMonthCustomerMrr): void
    {
        $state->output->writeln('Customer '.$customerId.' has different MRR in '.$month->toDateString());

        // Get items from previous month
        $previousItems = MrrItem::where('version_id', $state->version)
            ->where('customer_id', $customerId)
            ->where('month', $month->subMonth()->format('Ym'))
            ->where('partial_month', false)
            ->all()
            ->toArray();

        // Get items from this month
        $currentItems = MrrItem::where('version_id', $state->version)
            ->where('customer_id', $customerId)
            ->where('month', $month->format('Ym'))
            ->where('partial_month', false)
            ->all()
            ->toArray();

        // Match up items from the current and previous month.
        // The matching is done by a generated hash key.
        $matchedItems = [];
        foreach ($previousItems as $item) {
            $hashKey = $this->matchHashKey($item, $matchedItems, 0);
            if (!isset($matchedItems[$hashKey])) {
                $matchedItems[$hashKey] = [null, null];
            }
            $matchedItems[$hashKey][0] = $item;
        }

        foreach ($currentItems as $item) {
            $hashKey = $this->matchHashKey($item, $matchedItems, 1);
            if (!isset($matchedItems[$hashKey])) {
                $matchedItems[$hashKey] = [null, null];
            }
            $matchedItems[$hashKey][1] = $item;
        }

        // Look at each match to generate movements
        foreach ($matchedItems as $row) {
            /**
             * @var MrrItem|null $previousItem
             * @var MrrItem|null $currentItem
             */
            [$previousItem, $currentItem] = $row;
            $this->processMatchedItem($state, $month, $customerId, $previousItem, $currentItem, $thisMonthCustomerMrr, $lastMonthCustomerMrr);
        }
    }

    /**
     * Generate a new match hash key. This matches MRR items by
     * plan or item. If there are multiple unique items with the same
     * hash key then a unique hash key is generated for the item.
     */
    private function matchHashKey(MrrItem $item, array $matchedItems, int $position): string
    {
        $components = ['customer:'.$item->customer_id];
        if ($planId = $item->plan_id) {
            $components[] = 'plan:'.$planId;
        } elseif ($itemId = $item->item_id) {
            $components[] = 'item:'.$itemId;
        }

        $hashKey = implode(',', $components);

        // Keep generating a new hash key if there is
        // an existing item with the same hash key.
        // This happens if a customer is subscribed
        // multiple times to the same plan.
        $counter = 1;
        while (isset($matchedItems[$hashKey][$position])) {
            $hashKey = implode(',', $components).',index:'.$counter;
            ++$counter;
        }

        return $hashKey;
    }

    private function processMatchedItem(MrrCalculationState $state, CarbonImmutable $month, int $customerId, ?MrrItem $previousItem, ?MrrItem $currentItem, Money $thisMonthCustomerMrr, Money $lastMonthCustomerMrr): void
    {
        $currency = $state->version->currency;
        $previousMrr = Money::fromDecimal($currency, $previousItem?->mrr ?? 0);
        $currentMrr = Money::fromDecimal($currency, $currentItem?->mrr ?? 0);
        $difference = $currentMrr->subtract($previousMrr);

        if ($difference->isZero()) {
            return;
        }

        if ($difference->isNegative()) {
            $movementType = $thisMonthCustomerMrr->isZero() ? MrrMovementType::Lost : MrrMovementType::Contraction;
        } else {
            if ($lastMonthCustomerMrr->isZero()) {
                // If this customer ever had previous MRR then it is a reactivation
                $hadPreviousMrr = $this->database->fetchOne('SELECT 1 FROM MrrItems WHERE version_id=:versionId AND `month` < :month AND customer_id=:customerId', [
                    'versionId' => $state->version->id,
                    'month' => $month->format('Ym'),
                    'customerId' => $customerId,
                ]);
                $movementType = $hadPreviousMrr ? MrrMovementType::Reactivation : MrrMovementType::NewBusiness;
            } else {
                $movementType = MrrMovementType::Expansion;
            }
        }

        $date = $currentItem ? $currentItem->date : $month;
        /** @var MrrItem $latestItem */
        $latestItem = $currentItem ?? $previousItem;
        $state->output->writeln($date->format('Y-m').' MRR movement for customer # '.$customerId.' by '.$difference.'. Plan: '.$latestItem->plan_id.'. Category: '.$movementType->name);

        $this->createMrrMovement($date, $state->version, $latestItem, $difference, Money::zero($currency), $movementType);
    }

    private function createMrrMovement(DateTimeInterface $date, MrrVersion $version, MrrItem $latestItem, Money $mrr, Money $discount, MrrMovementType $movementType): void
    {
        $movement = new MrrMovement();
        $movement->version = $version;
        $movement->customer_id = $latestItem->customer_id;
        $movement->subscription_id = $latestItem->subscription_id;
        $movement->invoice_id = $latestItem->invoice_id;
        $movement->credit_note_id = $latestItem->credit_note_id;
        $movement->plan_id = $latestItem->plan_id;
        $movement->item_id = $latestItem->item_id;
        $movement->movement_type = $movementType;
        $movement->month = (int) $date->format('Ym');
        $movement->date = $date;
        $movement->mrr = $mrr->toDecimal();
        $movement->discount = $discount->toDecimal();
        $movement->saveOrFail();
    }
}
