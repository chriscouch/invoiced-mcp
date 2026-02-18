<?php

namespace App\EntryPoint\Controller\Api;

use App\SalesTax\Api\CreateTaxRuleRoute;
use App\SalesTax\Api\DeleteTaxRuleRoute;
use App\SalesTax\Api\EditTaxRuleRoute;
use App\SalesTax\Api\ListTaxRulesRoute;
use App\SalesTax\Api\RetrieveTaxRuleRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class TaxRuleApiController extends AbstractApiController
{
    #[Route(path: '/tax_rules', name: 'list_tax_rules', methods: ['GET'])]
    public function listAll(ListTaxRulesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/tax_rules', name: 'create_tax_rule', methods: ['POST'])]
    public function create(CreateTaxRuleRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/tax_rules/{model_id}', name: 'retrieve_tax_rule', methods: ['GET'])]
    public function retrieve(RetrieveTaxRuleRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/tax_rules/{model_id}', name: 'edit_tax_rule', methods: ['PATCH'])]
    public function edit(EditTaxRuleRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/tax_rules/{model_id}', name: 'delete_tax_rule', methods: ['DELETE'])]
    public function delete(DeleteTaxRuleRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
