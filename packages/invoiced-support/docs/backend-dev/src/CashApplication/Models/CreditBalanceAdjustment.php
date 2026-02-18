<?php

namespace App\CashApplication\Models;

use App\Core\Utils\Enums\ObjectType;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Query;

/**
 * Wraps the transaction model to provide a more convenient way to interact
 * with customer credit balances.
 */
class CreditBalanceAdjustment extends Transaction
{
    protected function initialize(): void
    {
        self::saving([self::class, 'transformParameters']);
        parent::initialize();
    }

    public function getTablename(): string
    {
        return 'Transactions';
    }

    /**
     * Transforms the create parameters into the format for the transaction.
     */
    public static function transformParameters(AbstractEvent $event): void
    {
        /** @var self $adjustment */
        $adjustment = $event->getModel();
        $adjustment->type = self::TYPE_ADJUSTMENT;
        $adjustment->method = PaymentMethod::BALANCE;
        $adjustment->status = self::STATUS_SUCCEEDED;
        $adjustment->amount *= -1; // adjustments are negative
    }

    public function toArray(): array
    {
        // credit balance adjustments offer a simpler schema than transactions
        return [
            'id' => $this->id,
            'object' => 'credit_balance_adjustment',
            'customer' => $this->customer,
            'date' => $this->date,
            'currency' => $this->currency,
            'amount' => $this->amount * -1,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }

    public function getObjectName(): string
    {
        // Needed for metadata storage
        return 'transaction';
    }

    public static function customizeBlankQuery(Query $query): Query
    {
        $query->where('type', self::TYPE_ADJUSTMENT)
            ->where('method', PaymentMethod::BALANCE);

        return $query;
    }

    public function getEventObjectType(): ObjectType
    {
        return ObjectType::Transaction; // Needed for BC
    }
}
