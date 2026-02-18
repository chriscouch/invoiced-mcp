<?php

namespace App\EntryPoint\Controller\Api;

use App\PaymentProcessing\Api\MerchantAccountTransactions\ListMerchantAccountTransactionsRoute;
use App\PaymentProcessing\Api\MerchantAccountTransactions\RetrieveMerchantAccountTransactionRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class MerchantAccountTransactionApiController extends AbstractApiController
{
    #[Route(path: '/merchant_account_transactions', name: 'list_merchant_account_transactions', methods: ['GET'])]
    public function listAll(ListMerchantAccountTransactionsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/merchant_account_transactions/{model_id}', name: 'retrieve_merchant_account_transaction', methods: ['GET'])]
    public function retrieve(RetrieveMerchantAccountTransactionRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
