<?php

namespace App\EntryPoint\Controller\Api;

use App\CashApplication\Api\CreateCashApplicationBankAccountRoute;
use App\CashApplication\Api\DeleteCashApplicationBankAccountRoute;
use App\CashApplication\Api\GetCashApplicationBankAccountTransactionsRoute;
use App\CashApplication\Api\ListCashApplicationBankAccountsRoute;
use App\CashApplication\Api\RetrieveCashApplicationBankAccountRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class CashApplicationBankAccountApiController extends AbstractApiController
{
    #[Route(path: '/plaid_links', name: 'list_cash_application_bank_accounts', methods: ['GET'])]
    public function listAll(ListCashApplicationBankAccountsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/plaid_links/{model_id}', name: 'retrieve_cash_application_bank_account', methods: ['GET'])]
    public function retrieve(RetrieveCashApplicationBankAccountRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/plaid_links', name: 'create_cash_application_bank_account', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function create(CreateCashApplicationBankAccountRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/plaid_links/{model_id}', name: 'delete_cash_application_bank_account', methods: ['DELETE'])]
    public function delete(DeleteCashApplicationBankAccountRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/plaid_links/{model_id}/transactions', name: 'get_cash_application_bank_account_transactions', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function getTransactions(GetCashApplicationBankAccountTransactionsRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
