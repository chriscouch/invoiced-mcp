<?php

namespace App\Core\Database;

use Doctrine\DBAL\Connection;
use Exception;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Helper for saving multiple models within a database
 * transaction. It is aware of the domain-logic to handle
 * non-database cleanup in the event of a rollback. For example,
 * this will ensure that the search index spool is cleared
 * in order to prevent a phantom record out of the search index.
 */
class TransactionManager
{
    public function __construct(private Connection $database, private EventDispatcherInterface $dispatcher)
    {
    }

    public function perform(callable $fn): mixed
    {
        $this->start();

        try {
            $result = $fn();
            $this->commit();

            return $result;
        } catch (Throwable $e) {
            $this->rollBack();

            throw $e;
        }
    }

    public function start(): void
    {
        // if we are already in a transaction
        // then no event should be emitted
        // that was already done by the parent transaction
        if (!$this->database->isTransactionActive()) {
            $this->dispatcher->dispatch(new BeginTransactionEvent());
        }

        $this->database->beginTransaction();
    }

    public function commit(): void
    {
        if (!$this->database->commit()) {
            throw new Exception('Database transaction commit failed with no reason');
        }
    }

    public function rollBack(): void
    {
        $this->database->rollBack();

        // if we are still in a transaction
        // then no event should be emitted
        // that will be done by the parent transaction
        if (!$this->database->isTransactionActive()) {
            $this->dispatcher->dispatch(new RollBackTransactionEvent());
        }
    }

    public function isRollbackOnly(): bool
    {
        return $this->database->isRollbackOnly();
    }
}
