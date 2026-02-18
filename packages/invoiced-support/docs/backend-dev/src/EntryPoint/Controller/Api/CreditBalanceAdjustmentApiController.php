<?php

namespace App\EntryPoint\Controller\Api;

use App\CashApplication\Api\CreateCreditBalanceAdjustmentRoute;
use App\CashApplication\Api\DeleteCreditBalanceAdjustmentRoute;
use App\CashApplication\Api\EditCreditBalanceAdjustmentRoute;
use App\CashApplication\Api\ListCreditBalanceAdjustmentsRoute;
use App\CashApplication\Api\RetrieveCreditBalanceAdjustmentRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class CreditBalanceAdjustmentApiController extends AbstractApiController
{
    #[Route(path: '/credit_balance_adjustments', name: 'list_credit_balance_adjustments', methods: ['GET'])]
    public function listAll(ListCreditBalanceAdjustmentsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/credit_balance_adjustments', name: 'create_credit_balance_adjustment', methods: ['POST'])]
    public function create(CreateCreditBalanceAdjustmentRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/credit_balance_adjustments/{model_id}', name: 'retrieve_credit_balance_adjustment', methods: ['GET'])]
    public function retrieve(RetrieveCreditBalanceAdjustmentRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/credit_balance_adjustments/{model_id}', name: 'edit_credit_balance_adjustment', methods: ['PATCH'])]
    public function edit(EditCreditBalanceAdjustmentRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/credit_balance_adjustments/{model_id}', name: 'delete_credit_balance_adjustment', methods: ['DELETE'])]
    public function delete(DeleteCreditBalanceAdjustmentRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
