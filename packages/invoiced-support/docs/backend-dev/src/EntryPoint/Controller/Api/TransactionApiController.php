<?php

namespace App\EntryPoint\Controller\Api;

use App\CashApplication\Api\CreateTransactionRoute;
use App\CashApplication\Api\DeleteTransactionRoute;
use App\CashApplication\Api\EditTransactionRoute;
use App\CashApplication\Api\ListTransactionsRoute;
use App\CashApplication\Api\RetrieveTransactionRoute;
use App\Integrations\AccountingSync\Api\TransactionAccountingSyncStatusRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class TransactionApiController extends AbstractApiController
{
    #[Route(path: '/transactions', name: 'list_transactions', methods: ['GET'])]
    public function listAll(ListTransactionsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/transactions', name: 'create_transaction', methods: ['POST'])]
    public function create(CreateTransactionRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/transactions/{model_id}', name: 'retrieve_transaction', methods: ['GET'])]
    public function retrieve(RetrieveTransactionRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/transactions/{model_id}', name: 'edit_transaction', methods: ['PATCH'])]
    public function edit(EditTransactionRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/transactions/{model_id}', name: 'delete_transaction', methods: ['DELETE'])]
    public function delete(DeleteTransactionRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/transactions/{model_id}/accounting_sync_status', name: 'transaction_sync_status', methods: ['GET'])]
    public function accountingSyncStatus(TransactionAccountingSyncStatusRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
