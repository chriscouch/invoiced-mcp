<?php

namespace App\AccountsReceivable\Libs;

use App\AccountsReceivable\Exception\CustomerMergeException;
use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Libs\CreditBalanceHistory;
use App\CashApplication\Models\Transaction;
use App\Core\Database\TransactionManager;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\Enums\ObjectType;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Models\Event;
use App\ActivityLog\ValueObjects\PendingEvent;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;

/**
 * Utility class to merge customer accounts.
 */
class CustomerMerger implements LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    /**
     * @phpstan-var  array<string, string>
     */
    private static array $objects = [
        'BankAccounts' => 'customer_id',
        'Cards' => 'customer_id',
        'Charges' => 'customer_id',
        'CompletedChasingSteps' => 'customer_id',
        'Contacts' => 'customer_id',
        'CreditBalances' => 'customer_id',
        'CreditNotes' => 'customer',
        'EmailThreads' => 'customer_id',
        'Estimates' => 'customer',
        'Invoices' => 'customer',
        'LateFees' => 'customer_id',
        'LineItems' => 'customer_id',
        'Notes' => 'customer_id',
        'Payments' => 'customer',
        'Subscriptions' => 'customer',
        'Tasks' => 'customer_id',
        'Transactions' => 'customer',
        'TokenizationFlows' => 'customer_id',
        'PaymentFlows' => 'customer_id',
        'PaymentLinks' => 'customer_id',
        // NOTE: intentionally missing are:
        // StripeCustomers
        // Metadata
    ];
    /**
     * @phpstan-var  array<string, array<int, string>>
     */
    private static array $polymorphicObjects = [
        'CouponRedemptions' => ['parent_type', 'parent_id'],
        // NOTE: intentionally missing are:
        // DisabledPaymentMethods
        // EventAssociations
        // ImportedObjects
    ];

    public function __construct(private TransactionManager $transaction, private EventSpool $eventSpool, private Connection $database)
    {
    }

    /**
     * This function merges the second customer into the
     * first customer. All the data associated with
     * the second customer will be moved to the first
     * customer. The second customer's profile will be
     * discarded.
     *
     * @throws CustomerMergeException
     */
    public function merge(Customer $customer1, Customer $customer2): void
    {
        if ($customer1->id() == $customer2->id()) {
            throw new CustomerMergeException('A customer cannot be merged into itself.');
        }

        // verify that both customers exist
        $customer1 = Customer::find($customer1->id());
        if (!$customer1) {
            throw new CustomerMergeException('Merge cannot be performed because customer 1 does not exist.');
        }

        $customer2 = Customer::find($customer2->id());
        if (!$customer2) {
            throw new CustomerMergeException('Merge cannot be performed because customer 2 does not exist.');
        }

        // Steps to complete a merge:
        // 1. Start a database transaction
        // 2. Update the customer ID on any associated objects, including events.
        // 3. Recalculate the customer's credit balance.
        // 4. Delete the customer being merged in.
        // 5. Create an event
        // 6. Commit the database transaction

        EventSpool::disable();

        try {
            $this->transaction->perform(function () use ($customer1, $customer2) {
                $eventMetadata = ['original_customer' => $customer2->toArray()];

                $this->updateAssociatedObjects($customer1, $customer2);
                $this->recalculateCreditBalance($customer1);
                $customer2->delete();

                // create an event
                EventSpool::enable();
                $pendingEvent = new PendingEvent(
                    object: $customer1,
                    type: EventType::CustomerMerged,
                    extraObjectData: $eventMetadata
                );
                $this->eventSpool->enqueue($pendingEvent);
            });
        } catch (Throwable $e) {
            EventSpool::enable();
            $this->logger->error('Could not merge customer', ['exception' => $e]);

            throw new CustomerMergeException('The customer merge could not be completed due to an unknown error.');
        }

        // record in statsd
        $this->statsd->increment('merged_customer');
    }

    private function updateAssociatedObjects(Customer $customer1, Customer $customer2): void
    {
        // update associated objects
        foreach (self::$objects as $table => $customerColumn) {
            $this->database->update($table, [
                $customerColumn => $customer1->id(),
            ], [
                'tenant_id' => $customer1->tenant_id,
                $customerColumn => $customer2->id(),
            ]);
        }

        // update polymorphic objects
        foreach (self::$polymorphicObjects as $table => $columns) {
            [$objectTypeColumn, $customerColumn] = $columns;
            $this->database->update($table, [
                $customerColumn => $customer1->id(),
            ], [
                'tenant_id' => $customer1->tenant_id,
                $objectTypeColumn => ObjectType::Customer->typeName(),
                $customerColumn => $customer2->id(),
            ]);
        }

        // update events
        // This is a two-step process in order to maximize index usage
        $eventIds = $this->database->fetchOne('SELECT GROUP_CONCAT(id) FROM Events WHERE tenant_id=:tenantId AND object_type="customer" and object_id=:customerId AND id IN (SELECT `event` FROM EventAssociations WHERE object="customer" and object_id=:customerId)', [
            'tenantId' => $customer1->tenant_id,
            'customerId' => $customer2->id(),
        ]);
        if ($eventIds) {
            $this->database->executeStatement('UPDATE Events SET object_id='.$customer1->id().' WHERE id IN ('.$eventIds.')');
        }

        // update event associations
        $this->database->update('EventAssociations', [
            'object_id' => $customer1->id(),
        ], [
            'object' => ObjectType::Customer->typeName(),
            'object_id' => $customer2->id(),
        ]);
    }

    /**
     * Recalculates the credit balances for a customer in order
     * to account for the merged history.
     *
     * @throws CustomerMergeException
     */
    private function recalculateCreditBalance(Customer $customer): void
    {
        $currencies = $this->getCustomerCurrencies((int) $customer->id());

        foreach ($currencies as $currency) {
            $history = new CreditBalanceHistory($customer, $currency);

            if (!$history->persist()) {
                throw new CustomerMergeException("Could not write credit balance history for customer # {$customer->id()}!");
            }
        }
    }

    /**
     * Gets a list of currencies that a customer has been billed in.
     */
    private function getCustomerCurrencies(int $customerId): array
    {
        return $this->database->createQueryBuilder()
            ->select('currency')
            ->from('Transactions')
            ->andWhere('method = "balance"')
            ->andWhere('type IN ("'.Transaction::TYPE_ADJUSTMENT.'", "'.Transaction::TYPE_CHARGE.'")')
            ->andWhere('customer = '.$customerId)
            ->groupBy('currency')
            ->fetchFirstColumn();
    }
}
