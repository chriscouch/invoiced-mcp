<?php

namespace App\CashApplication\Libs;

use App\CashApplication\Models\Transaction;
use Generator;

/**
 * Creates an iterator to visit all nodes in a transaction
 * tree. Uses the BFS (Breadth-First Search) algorithm.
 */
class TransactionTreeIterator
{
    /**
     * @return Generator<Transaction>
     */
    public static function make(Transaction $transaction): Generator
    {
        // seed the search queue with the root node
        $searchQ = [$transaction->tree()];
        while (count($searchQ) > 0) {
            // pop off next node
            $node = array_shift($searchQ);

            // add any child nodes to search queue
            $searchQ = array_merge($searchQ, $node->getChildren());

            yield $node->getRoot();
        }
    }
}
