<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsPayable\Api\CreateCompanyCardApiRoute;
use App\AccountsPayable\Api\DeleteCompanyCardApiRoute;
use App\AccountsPayable\Api\ListCompanyCardsApiRoute;
use App\AccountsPayable\Api\StartCompanyCardApiRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class CompanyCardApiController extends AbstractApiController
{
    #[Route(path: '/cards', name: 'list_company_cards', methods: ['GET'])]
    public function listAll(ListCompanyCardsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/cards/setup_intent', name: 'create_company_card_setup_intent', methods: ['POST'])]
    public function createSetupIntent(StartCompanyCardApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/cards', name: 'create_company_card', methods: ['POST'])]
    public function create(CreateCompanyCardApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/cards/{model_id}', name: 'delete_company_card', methods: ['DELETE'])]
    public function delete(DeleteCompanyCardApiRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
