<?php

namespace App\EntryPoint\Controller\Api;

use App\CashApplication\Api\CreateCashApplicationRuleRoute;
use App\CashApplication\Api\DeleteCashApplicationRuleRoute;
use App\CashApplication\Api\EditCashApplicationRuleRoute;
use App\CashApplication\Api\ListCashApplicationMatchesRoute;
use App\CashApplication\Api\ListCashApplicationRulesRoute;
use App\CashApplication\Api\RetrieveCashApplicationRuleRoute;
use App\CashApplication\Api\UnsuccessfulCashApplicationMatchesRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class CashApplicationApiController extends AbstractApiController
{
    #[Route(path: '/cash_application/rules', name: 'list_cash_application_rules', methods: ['GET'])]
    public function listCashApplicationRules(ListCashApplicationRulesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/cash_application/rules', name: 'create_cash_application_rule', methods: ['POST'])]
    public function createCashApplicationRule(CreateCashApplicationRuleRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/cash_application/rules/{model_id}', name: 'retrieve_cash_application_rule', methods: ['GET'])]
    public function retrieveCashApplicationRule(RetrieveCashApplicationRuleRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/cash_application/rules/{model_id}', name: 'edit_cash_application_rule', methods: ['PATCH'])]
    public function editCashApplicationRule(EditCashApplicationRuleRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/cash_application/rules/{model_id}', name: 'delete_cash_application_rule', methods: ['DELETE'])]
    public function deleteCashApplicationRule(DeleteCashApplicationRuleRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payments/{payment_id}/matches', name: 'list_cash_application_matches', methods: ['GET'])]
    public function listCashApplicationMatches(ListCashApplicationMatchesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/cash_application/matches/{group_id}/unsuccessful', name: 'unsuccessful_cash_application_matche', methods: ['GET'])]
    public function unsuccessfulCashApplicationMatches(UnsuccessfulCashApplicationMatchesRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
