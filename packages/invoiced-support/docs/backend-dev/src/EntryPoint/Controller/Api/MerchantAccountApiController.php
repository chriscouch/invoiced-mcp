<?php

namespace App\EntryPoint\Controller\Api;

use App\PaymentProcessing\Api\DeleteMerchantAccountRoute;
use App\PaymentProcessing\Api\EditMerchantAccountRoute;
use App\PaymentProcessing\Api\ListMerchantAccountsRoute;
use App\PaymentProcessing\Api\RetrieveMerchantAccountRoute;
use App\PaymentProcessing\Api\TestGatewayCredentialsRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class MerchantAccountApiController extends AbstractApiController
{
    #[Route(path: '/merchant_accounts', name: 'list_merchant_accounts', methods: ['GET'])]
    public function listAll(ListMerchantAccountsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/merchant_accounts/{model_id}', name: 'retrieve_merchant_account', methods: ['GET'])]
    public function retrieve(RetrieveMerchantAccountRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/merchant_accounts/{model_id}', name: 'edit_merchant_account', methods: ['PATCH'])]
    public function edit(EditMerchantAccountRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/merchant_accounts/{model_id}', name: 'delete_merchant_account', methods: ['DELETE'])]
    public function delete(DeleteMerchantAccountRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/merchant_accounts/test', name: 'test_merchant_account', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function test(TestGatewayCredentialsRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
