<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsPayable\Api\CreateCompanyBankAccountApiRoute;
use App\AccountsPayable\Api\CreatePlaidLinkRoute;
use App\AccountsPayable\Api\DeleteCompanyBankAccountApiRoute;
use App\AccountsPayable\Api\EditCompanyBankAccount;
use App\AccountsPayable\Api\ListCompanyBankAccountsApiRoute;
use App\AccountsPayable\Api\RetrieveCompanyBankAccountsApiRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class CompanyBankAccountApiController extends AbstractApiController
{
    #[Route(path: '/vendor_bank_accounts', name: 'list_company_bank_accounts', methods: ['GET'])]
    public function listAll(ListCompanyBankAccountsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_bank_accounts', name: 'create_company_bank_account', methods: ['POST'])]
    public function create(CreateCompanyBankAccountApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_bank_accounts/{model_id}', name: 'retrieve_company_bank_account', methods: ['GET'])]
    public function retrieve(RetrieveCompanyBankAccountsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_bank_accounts/{model_id}', name: 'edit_company_bank_account', methods: ['PATCH'])]
    public function edit(EditCompanyBankAccount $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_bank_accounts/{model_id}', name: 'delete_company_bank_account', methods: ['DELETE'])]
    public function delete(DeleteCompanyBankAccountApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_bank_accounts/plaid_links', name: 'create_ap_plaid_links', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function createLink(CreatePlaidLinkRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
