<?php

namespace App\CashApplication\Traits;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Exceptions\ApplyCreditBalancePaymentException;
use App\CashApplication\Exceptions\ApplyPaymentException;
use App\CashApplication\Libs\ApplyPayment;
use App\CashApplication\Libs\TransactionNormalizer;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\ActivityLog\Libs\EventSpool;
use Doctrine\DBAL\Connection;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Model;

trait HasAppliedToTrait
{
    protected ?array $saveAppliedTo = null;
    private array $modifiedIds;
    private bool $updateBalance;
    private array $unsavedSplits;
    private Money $existingAmountApplied;

    //
    // Accessors
    //

    public function getAppliedToValue(mixed $currentValue): array
    {
        if (is_array($currentValue)) {
            return $currentValue;
        }

        if (!$this->persisted()) {
            return [];
        }

        $normalizer = new TransactionNormalizer();
        $appliedTo = [];
        foreach ($this->getTransactions() as $transaction) {
            if ($split = $normalizer->normalize($transaction)) {
                $appliedTo[] = $split;
            }
        }

        return $appliedTo;
    }

    //
    // Mutators
    //

    public function setAppliedToValue(array $appliedTo): array
    {
        $this->saveAppliedTo = $appliedTo;

        return $appliedTo;
    }

    //
    // Helpers
    //

    /**
     * @throws ListenerException
     * @throws ApplyCreditBalancePaymentException
     */
    protected function saveAppliedTo(bool $isUpdate): void
    {
        /** @var Payment $this */
        if (null === $this->saveAppliedTo) {
            return;
        }

        $this->unsavedSplits = $this->validateAppliedTo();

        if ($isUpdate) {
            $this->deleteOrphanedTransactions();
        }

        if ($this->updateBalance) {
            $this->balance = $this->calculateBalance();
        }

        $this->saveAppliedTo = null;
        if (count($this->unsavedSplits) > 0) {
            $customer = $this->customer();
            if (!$customer) {
                throw new ListenerException('Missing customer', ['field' => 'customer']);
            }

            $this->applied = false;

            try {
                (new ApplyPayment())->apply($this, $customer, $this->unsavedSplits);
            } catch (ApplyCreditBalancePaymentException $e) {
                throw $e;
            } catch (ApplyPaymentException $e) {
                throw new ListenerException($e->getMessage(), ['field' => 'applied_to']);
            }
        } else {
            // Validate the total applied does not exceed payment amount.
            $paymentAmount = $this->getAmount();
            if ($this->existingAmountApplied->greaterThan($paymentAmount)) {
                throw new ListenerException('Total amount applied ('.$this->existingAmountApplied.') cannot exceed the payment amount ('.$paymentAmount.')');
            }

            EventSpool::disablePush();
            if (!$this->save()) {
                throw new ListenerException(); // message is not needed because validation errors are already set on model
            }
            EventSpool::enablePop();
        }
    }

    /**
     * @throws ListenerException
     */
    private function validateAppliedTo(): array
    {
        $result = [];
        $this->modifiedIds = [];
        $this->updateBalance = false;
        $splitKeys = []; // keep track of documents applied to detect duplicates
        $this->existingAmountApplied = new Money($this->currency, 0);

        if (!is_array($this->saveAppliedTo)) {
            return [];
        }

        foreach ($this->saveAppliedTo as $split) {
            $type = PaymentItemType::tryFrom($split['type']);
            if (!in_array($type, [PaymentItemType::AppliedCredit, PaymentItemType::CreditNote, PaymentItemType::DocumentAdjustment])) {
                if (isset($split['invoice']) && PaymentItemType::Invoice != $type) {
                    throw new ListenerException('Cannot supply invoice ID to non-invoice splits.', ['field' => 'applied_to.invoice']);
                }

                if (isset($split['estimate']) && PaymentItemType::Estimate != $type) {
                    throw new ListenerException('Cannot supply estimate ID to non-estimate splits.', ['field' => 'applied_to.estimate']);
                }
            }

            // detect duplicates
            $key = $this->splitKey($split);
            if (in_array($key, $splitKeys)) {
                throw new ListenerException("Each document can only be applied once per payment. Detected duplicate ($key)", ['field' => 'applied_to']);
            }
            $splitKeys[] = $key;

            // attempt to reuse and update the existing transaction if there is one
            $splitKeyMatchesId = false;
            if ($this->persisted() && !isset($split['id'])) {
                foreach ($this->ignoreUnsaved()->applied_to as $split2) {
                    if ($this->splitKey($split) == $this->splitKey($split2)) {
                        $split['id'] = $split2['id'];
                        $splitKeyMatchesId = true;
                        break;
                    }
                }
            }

            if (isset($split['id']) && $existingTransaction = Transaction::find($split['id'])) {
                if (!$splitKeyMatchesId && isset($split['invoice']) && $existingTransaction->invoice != $split['invoice']) {
                    throw new ListenerException('The invoice of an existing payment application cannot be changed', ['field' => 'applied_to.invoice']);
                }

                if (!$splitKeyMatchesId && isset($split['estimate']) && $existingTransaction->estimate != $split['estimate']) {
                    throw new ListenerException('The estimate of an existing payment application cannot be changed', ['field' => 'applied_to.estimate']);
                }

                $this->modifiedIds[] = $split['id'];
                // this is a heuristic to determine the correct sign on the transaction amount
                $isNegative = $existingTransaction->amount < 0;
                $existingTransaction->amount = $isNegative ? -$split['amount'] : $split['amount'];
                try {
                    $existingTransaction->saveOrFail();
                } catch (ModelException $e) {
                    throw new ListenerException($e->getMessage(), ['field' => 'applied_to']);
                }
                $this->updateBalance = true;
                if (!in_array($split['type'], Payment::NON_CASH_SPLIT_TYPES)) {
                    $this->existingAmountApplied = $this->existingAmountApplied->add(Money::fromDecimal($this->currency, $split['amount']));
                }

                continue;
            }

            if (!$this->customer()) {
                if (isset($split['invoice'])) {
                    $invoice = $split['invoice'] instanceof Invoice ? $split['invoice'] : Invoice::find($split['invoice']);
                    if ($invoice) {
                        $this->setCustomer($invoice->customer());
                        $split['invoice'] = $invoice;
                    }
                }

                if (isset($split['estimate'])) {
                    $estimate = $split['estimate'] instanceof Estimate ? $split['estimate'] : Estimate::find($split['estimate']);
                    if ($estimate) {
                        $this->setCustomer($estimate->customer());
                        $split['estimate'] = $estimate;
                    }
                }

                if (isset($split['credit_note'])) {
                    $creditNote = $split['credit_note'] instanceof CreditNote ? $split['credit_note'] : CreditNote::find($split['credit_note']);
                    if ($creditNote) {
                        $this->setCustomer($creditNote->customer());
                        $split['credit_note'] = $creditNote;
                    }
                }
            }

            $result[] = $split;
        }

        return $result;
    }

    public function getExistingAmountApplied(): Money
    {
        if (!isset($this->existingAmountApplied)) {
            return new Money($this->currency, 0);
        }

        return $this->existingAmountApplied;
    }

    private function splitKey(array $split): string
    {
        $key = $split['type'];
        if (isset($split[$key])) {
            $doc = $split[$key];
            $id = $doc instanceof Model ? $doc->id() : $doc;
            $key .= ':'.$id;
        }

        if ($docType = $split['document_type'] ?? null) {
            $doc = $split[$docType];
            $id = $doc instanceof Model ? $doc->id() : $doc;
            $key .= ', '.$docType.':'.$id;
        }

        return $key;
    }

    /**
     * @throws ListenerException
     * @throws \Doctrine\DBAL\Exception
     */
    private function deleteOrphanedTransactions(): void
    {
        // keep track of parent deletion and non-deleted transactions
        $deletedParent = null;
        $remainingTransactions = [];

        // delete orphaned transactions
        $transactions = Transaction::where('payment_id', $this->id)->all();
        /** @var Transaction $split */
        foreach ($transactions as $split) {
            if (!in_array($split->id(), $this->modifiedIds)) {
                if (!$split->parent_transaction) {
                    $deletedParent = $split;
                }

                if (!$split->delete()) {
                    throw new ListenerException('Could not delete transaction: '.$split->getErrors(), ['field' => 'applied_to']);
                }
                $this->updateBalance = true;
            } else {
                $remainingTransactions[] = $split;
            }
        }

        // reset the parent transaction
        if ($deletedParent instanceof Transaction) {
            $newParent = $remainingTransactions[0] ?? null;
            if (!($newParent instanceof Transaction)) {
                // all transactions have been deleted
                return;
            }

            $tenant = $newParent->tenant();
            /** @var Connection $database */
            $database = self::getDriver()->getConnection(null);
            $database->executeStatement('UPDATE Transactions SET parent_transaction=null WHERE id=:new_parent_id AND tenant_id=:tenant_id', [
                'new_parent_id' => (int) $newParent->id(),
                'tenant_id' => (int) $tenant->id(),
            ]);
            // NOTE:
            // The deleted parent id is not parameterized because in the case that $deletedParent->id() returns null or false,
            // the string will look like "parent_transaction= AND" which will throw an exception due to invalid SQL. It's important
            // that the exception is thrown to be able to debug any issues. In theory, the $deletedParent->id() call should
            // always return a valid id in this case.
            $database->executeStatement('UPDATE Transactions SET parent_transaction=:new_parent_id WHERE parent_transaction='.$deletedParent->id().' AND tenant_id=:tenant_id', [
                'new_parent_id' => (int) $newParent->id(),
                'tenant_id' => (int) $tenant->id(),
            ]);
        }
    }

    /**
     * Calculates the balance based on the amount
     * and the associated transactions.
     */
    public function calculateBalance(): float
    {
        $transactions = $this->getTransactions();
        $balance = $this->getAmount();
        foreach ($transactions as $transaction) {
            if (Transaction::TYPE_DOCUMENT_ADJUSTMENT == $transaction->type) {
                continue;
            }
            if (Transaction::TYPE_ADJUSTMENT != $transaction->type) {
                $balance = $balance->subtract($transaction->transactionAmount());
            } elseif (!$transaction->credit_note_id) {
                // credit balance adjustments have negative amounts
                $balance = $balance->add($transaction->transactionAmount());
            }
        }

        return max(0, $balance->toDecimal());
    }
}
