<?php

namespace App\Statements\Libs;

use App\AccountsReceivable\Libs\CustomerHierarchy;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Transaction;
use App\Core\Utils\ModelUtility;
use App\Statements\Interfaces\BalanceForwardStatementLineInterface;
use App\Statements\StatementLines\BalanceForward\PaymentStatementLine;
use App\Statements\StatementLines\BalanceForwardStatementLineFactory;

final class BalanceForwardStatementData
{
    private static array $typeOrder = [
        'previous_balance',
        'invoice',
        'credit_note',
        'applied_credit',
        'payment',
        'refund',
        'credit_balance_adjustment',
    ];

    public function __construct(
        private BalanceForwardStatementLineFactory $factory,
        private CustomerHierarchy $hierarchy,
    ) {
    }

    /**
     * Gets the customer IDs to be included in the statement.
     * This will include sub-customers.
     *
     * @return int[]
     */
    public function getCustomerIds(Customer $customer): array
    {
        // include sub customer IDs in query
        $customerIds = $this->hierarchy->getSubCustomerIds($customer);
        $customerIds[] = $customer->id;

        return $customerIds;
    }

    /**
     * Gets a sorted list of statement lines based on
     * transactions in the customer's account.
     *
     * @return BalanceForwardStatementLineInterface[]
     */
    public function getLines(?BalanceForwardStatement $previousStatement, array $customerIds, string $currency, int $start, ?int $end): array
    {
        // get all invoices, credit notes, and payments
        $lines = array_merge(
            [$this->factory->makePreviousLine($start - 1, $previousStatement)],
            $this->getInvoices($customerIds, $currency, $start, $end),
            $this->getCreditNotes($customerIds, $currency, $start, $end),
            $this->getTransactions($customerIds, $currency, $start, $end));

        // sort activity by date and type
        usort($lines, [$this, 'lineSort']);

        return $lines;
    }

    /**
     * @return BalanceForwardStatementLineInterface[]
     */
    private function getInvoices(array $customerIds, string $currency, int $start, ?int $end): array
    {
        $query = Invoice::where('customer IN ('.implode(',', $customerIds).')')
            ->where('draft', false)
            ->where('voided', false)
            ->where('currency', $currency)
            ->where('date', $start, '>=');

        if ($end) {
            $query->where('date', $end, '<=');
        }

        return $this->factory->makeFromList(ModelUtility::getAllModels($query));
    }

    /**
     * @return BalanceForwardStatementLineInterface[]
     */
    private function getCreditNotes(array $customerIds, string $currency, int $start, ?int $end): array
    {
        $query = CreditNote::where('customer IN ('.implode(',', $customerIds).')')
            ->where('draft', false)
            ->where('voided', false)
            ->where('currency', $currency)
            ->where('date', $start, '>=');

        if ($end) {
            $query->where('date', $end, '<=');
        }

        return $this->factory->makeFromList(ModelUtility::getAllModels($query));
    }

    /**
     * @return BalanceForwardStatementLineInterface[]
     */
    private function getTransactions(array $customerIds, string $currency, int $start, ?int $end): array
    {
        // must ensure that parent transaction will always be before the children
        $query = Transaction::where('customer IN ('.implode(',', $customerIds).')')
            ->where('status', Transaction::STATUS_SUCCEEDED)
            ->where('currency', $currency)
            ->where('date', $start, '>=');

        if ($end) {
            $query->where('date', $end, '<=');
        }

        $result = [];
        /** @var Transaction $item */
        foreach (ModelUtility::getAllModels($query) as $item) {
            if ($item->isConvenienceFee() && Transaction::TYPE_REFUND !== $item->type) {
                if (isset($result[$item->parent_transaction]) && $result[$item->parent_transaction] instanceof PaymentStatementLine) {
                    $result[$item->parent_transaction]->addConvenienceFee($item->transactionAmount());
                }

                continue;
            }

            if ($line = $this->factory->make($item)) {
                $result[$item->id] = $line;
            }
        }

        return $result;
    }

    private function lineSort(BalanceForwardStatementLineInterface $a, BalanceForwardStatementLineInterface $b): int
    {
        // first, sort by date
        $aDate = $a->getDate();
        $bDate = $b->getDate();
        if ($aDate != $bDate) {
            return ($aDate > $bDate) ? 1 : -1;
        }

        // next, order by type
        $orderA = array_search($a->getType(), self::$typeOrder);
        $orderB = array_search($b->getType(), self::$typeOrder);

        return ($orderA > $orderB) ? 1 : -1;
    }
}
