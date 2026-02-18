<?php

namespace App\CashApplication\ValueObjects;

use App\CashApplication\Models\Transaction;

class TransactionTree
{
    /** @var self[] */
    private array $children;

    public function __construct(private Transaction $root)
    {
    }

    /**
     * Gets the root node.
     */
    public function getRoot(): Transaction
    {
        return $this->root;
    }

    /**
     * Gets the children of the transaction tree.
     *
     * @return TransactionTree[]
     */
    public function getChildren(): array
    {
        if (!isset($this->children)) {
            $this->build();
        }

        return $this->children;
    }

    /**
     * Gets the array representation of the transaction tree starting
     * with the immediate children.
     *
     * @param bool $first tracks whether this is the first call
     */
    public function toArray(bool $first = true): array
    {
        if (!isset($this->children)) {
            $this->build();
        }

        // expand children
        $children = [];
        foreach ($this->children as $child) {
            $children[] = $child->toArray(false);
        }

        // for first call we just want to return expanded children
        if ($first) {
            return $children;
        }

        $result = $this->root->toArray();
        $result['children'] = $children;

        $invoice = $this->root->invoice();
        $result['invoice'] = $invoice ? $invoice->toArray() : null;

        $creditNote = $this->root->creditNote();
        $result['credit_note'] = $creditNote ? $creditNote->toArray() : null;

        return $result;
    }

    /**
     * Builds the transaction tree.
     */
    private function build(): void
    {
        $transactions = Transaction::where('parent_transaction', $this->root->id())
            ->sort('id ASC')
            ->all();

        $children = [];
        foreach ($transactions as $transaction) {
            $children[] = $transaction->tree();
        }

        $this->children = $children;
    }
}
