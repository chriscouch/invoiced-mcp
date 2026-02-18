<?php

namespace App\CashApplication\Libs;

use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Models\CreditBalance;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Models\PaymentMethod;
use Carbon\CarbonImmutable;

class CreditBalanceHistory
{
    const UNSAVED_ID = -1;
    /** @var Transaction[] */
    private array $transactions;
    /** @var CreditBalance[] */
    private array $balances;
    private Money $previousBalance;

    public function __construct(private Customer $customer, private string $currency, private int $startTimestamp = 0, array $transactions = null, array $balances = null, Money $previousBalance = null)
    {
        // fetch transactions, if not given
        if (null === $transactions) {
            $this->transactions = $this->fetchTransactions();
        } else {
            $this->transactions = $transactions;
        }

        // fetch credit balances, if not given
        if (null === $balances) {
            $this->balances = $this->fetchBalances();
        } else {
            $this->balances = $balances;
        }

        // fetch previous balance, if not given
        if (!$previousBalance) {
            $this->previousBalance = $this->fetchPreviousBalance();
        } else {
            $this->previousBalance = $previousBalance;
        }

        $this->rebuild();
    }

    //
    // Getters
    //

    /**
     * Gets the customer this history is for.
     */
    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    /**
     * Gets the timestamp this history starts at.
     */
    public function getStartTimestamp(): int
    {
        return $this->startTimestamp;
    }

    /**
     * Gets the previous balance before the start point in this
     * history.
     */
    public function getPreviousBalance(): Money
    {
        return $this->previousBalance;
    }

    /**
     * Gets the map of transactions used by to build history.
     *
     * @return Transaction[]
     */
    public function getTransactions(): array
    {
        return $this->transactions;
    }

    /**
     * Gets the list of ordered CreditBalance objects produced
     * by this history.
     *
     * @return CreditBalance[]
     */
    public function getBalances(): array
    {
        return $this->balances;
    }

    /**
     * Gets the first instance where the balance was overspent
     * (if any).
     */
    public function getOverspend(): ?CreditBalance
    {
        foreach ($this->balances as $balance) {
            if ($balance->balance < 0) {
                return $balance;
            }
        }

        return null;
    }

    //
    // Transaction operations
    //

    /**
     * Adds a transaction to the history and rebuilds it.
     *
     * @return $this
     */
    public function addTransaction(Transaction $transaction)
    {
        $this->transactions[self::UNSAVED_ID] = $transaction;

        $this->rebuild();

        return $this;
    }

    /**
     * Changes the date and amount of a transaction in this
     * history and rebuilds the history.
     *
     * @return $this
     */
    public function changeTransaction(int $id, int $date, float $amount)
    {
        if (isset($this->transactions[$id])) {
            $this->transactions[$id]->date = $date;
            $this->transactions[$id]->amount = $amount;
        }

        $this->rebuild();

        return $this;
    }

    /**
     * Deletes a transaction from the history and rebuilds it.
     *
     * @return $this
     */
    public function deleteTransaction(int $id)
    {
        if (isset($this->transactions[$id])) {
            unset($this->transactions[$id]);
        }

        $this->rebuild();

        return $this;
    }

    /**
     * Sets the ID of the unsaved transaction. Useful after the
     * transaction has been saved.
     *
     * @return $this
     */
    public function setUnsavedId(int $id)
    {
        // look for the matching balance object and update
        // its transaction ID
        foreach ($this->balances as $balance) {
            if (self::UNSAVED_ID === $balance->transaction_id) {
                $balance->transaction_id = $id;

                break;
            }
        }

        // update the ID in the transactions list
        if (isset($this->transactions[self::UNSAVED_ID])) {
            $txn = $this->transactions[self::UNSAVED_ID];
            unset($this->transactions[self::UNSAVED_ID]);
            $txn->id = $id;
            $this->transactions[$id] = $txn;
        }

        return $this;
    }

    //
    // Setters
    //

    /**
     * Writes the balances contained within this history to the DB.
     */
    public function persist(): bool
    {
        // write out each balance entry
        $success = true;
        foreach ($this->balances as $balance) {
            $success = $balance->save() && $success;
        }

        // cache the customer's current credit balance
        $currentBalance = CreditBalance::lookup($this->customer, $this->currency)->toDecimal();
        if ($this->customer->credit_balance != $currentBalance) {
            $this->customer->credit_balance = $currentBalance;
            $this->customer->skipReconciliation();
            $success = $this->customer->save() && $success;
        }

        return $success;
    }

    //
    // Data fetching
    //

    /**
     * Fetch the Transaction objects needed to build the history.
     */
    private function fetchTransactions(): array
    {
        $query = Transaction::where('customer', $this->customer->id())
            ->where('(`type`="'.Transaction::TYPE_ADJUSTMENT.'" OR `type`="'.Transaction::TYPE_CHARGE.'")')
            ->where('`method`="'.PaymentMethod::BALANCE.'"')
            ->where('currency', $this->currency)
            ->where('date', $this->startTimestamp, '>=')
            ->all();
        $transactions = [];
        foreach ($query as $transaction) {
            $transactions[$transaction->id()] = $transaction;
        }

        return $transactions;
    }

    /**
     * Fetches existing CreditBalance objects.
     */
    private function fetchBalances(): array
    {
        $query = CreditBalance::where('customer_id', $this->customer->id())
            ->where('currency', $this->currency)
            ->where('timestamp', $this->startTimestamp, '>=')
            ->all();

        $balances = [];
        foreach ($query as $balance) {
            $balances[] = $balance;
        }

        return $balances;
    }

    /**
     * Fetches the previous balance from the start of this
     * history.
     */
    private function fetchPreviousBalance(): Money
    {
        $t = $this->startTimestamp - 1;

        return CreditBalance::lookup($this->customer, $this->currency, CarbonImmutable::createFromTimestamp($t));
    }

    //
    // Internal methods
    //

    /**
     * Rebuilds the internal list of credit balances.
     *
     * @return $this
     */
    private function rebuild()
    {
        $this->syncBalances()
            ->sortBalances()
            ->recalculateBalances();

        return $this;
    }

    /**
     * Ensures there is a CreditBalance for each Transaction,
     * and removes any CreditBalance objects without a
     * matching transaction.
     *
     * @return $this
     */
    private function syncBalances()
    {
        // O(N * log(N)) :-/
        $balances = $this->balances;
        foreach ($this->transactions as $id => $transaction) {
            $found = false;
            // look through each balance for a match
            foreach ($balances as $k => $balance) {
                if ($balance->transaction_id == $id) {
                    $found = $k;

                    break;
                }
            }

            // if not found then add a credit balance object
            if (false === $found) {
                $balance = new CreditBalance();
                $balance->transaction_id = $id;
                $balance->customer_id = (int) $this->customer->id();
                $balance->currency = $transaction->currency;
                $balance->timestamp = $transaction->date;
                $this->balances[] = $balance;

                // if found then update balance timestamp and
                // remove from the search space
            } else {
                $balances[$found]->timestamp = $transaction->date;
                unset($balances[$found]);
            }
        }

        // any remaining balances were not found in the
        // transactions list and should be removed
        if ($balances) {
            foreach (array_keys($balances) as $k) {
                unset($this->balances[$k]);
            }
        }

        return $this;
    }

    /**
     * Sorts the credit balances.
     *
     * @return $this
     */
    private function sortBalances()
    {
        usort($this->balances, [self::class, 'sort']);

        return $this;
    }

    /**
     * Sort comparison for two CreditBalance objects. Balances
     * are first sorted by timestamp and then by transaction ID
     * if it's a tie.
     */
    public static function sort(CreditBalance $a, CreditBalance $b): int
    {
        $tsA = $a->timestamp;
        $tsB = $b->timestamp;
        if ($tsA < $tsB) {
            return -1;
        } elseif ($tsA > $tsB) {
            return 1;
        }

        $idA = $a->transaction_id;
        $idB = $b->transaction_id;
        if ($idA === $idB) {
            return 0;
        }

        if (self::UNSAVED_ID === $idA) {
            return 1;
        } elseif (self::UNSAVED_ID === $idB) {
            return -1;
        }

        return ($idA < $idB) ? -1 : 1;
    }

    /**
     * Recalculates the balances on all CreditBalance objects
     * starting from the previous balance.
     *
     * @return $this
     */
    private function recalculateBalances()
    {
        $runningBalance = $this->previousBalance;

        foreach ($this->balances as $balance) {
            $tid = $balance->transaction_id;
            $transaction = $this->transactions[$tid];
            $amount = $transaction->transactionAmount();
            $runningBalance = $runningBalance->subtract($amount);

            if ($balance->balance !== $runningBalance->toDecimal()) {
                $balance->balance = $runningBalance->toDecimal();
            }
        }

        return $this;
    }
}
