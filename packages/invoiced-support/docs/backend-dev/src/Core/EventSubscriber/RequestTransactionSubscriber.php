<?php

namespace App\Core\EventSubscriber;

use App\Core\Database\TransactionManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Applies a database transaction to any HTTP request
 * that modifies data, eg POST, PATCH, PUT, or DELETE.
 */
class RequestTransactionSubscriber implements EventSubscriberInterface
{
    private bool $isUsingDatabaseTransaction = false;

    public function __construct(
        private TransactionManager $transactionManager,
    ) {
    }

    public function onControllerArguments(ControllerArgumentsEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Any HTTP verb that can modify data should
        // be wrapped in a database transaction by default.
        $request = $event->getRequest();
        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return;
        }

        // Certain API routes should not use a database transaction
        // and therefore this behavior can be disabled per API endpoint.
        if ($request->attributes->get('no_database_transaction')) {
            return;
        }

        // Start the database transaction
        $this->transactionManager->start();
        $this->isUsingDatabaseTransaction = true;
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Commit the database transaction
        if ($this->isUsingDatabaseTransaction) {
            // If an inner transaction already initiated rollback
            // then we have no choice but to roll back the outer
            // transaction.
            if ($this->transactionManager->isRollbackOnly()) {
                $this->transactionManager->rollBack();
            } else {
                $this->transactionManager->commit();
            }
        }
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Roll back the database transaction
        if ($this->isUsingDatabaseTransaction) {
            $this->transactionManager->rollBack();
            $this->isUsingDatabaseTransaction = false;
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.controller_arguments' => ['onControllerArguments', -1], // should be last
            'kernel.response' => ['onKernelResponse', 512], // should be first
            'kernel.exception' => ['onKernelException', 256], // should be first
        ];
    }
}
