<?php

namespace App\EntryPoint\Controller\Api;

use App\PaymentProcessing\Api\Charges\ListChargesRoute;
use App\PaymentProcessing\Api\Charges\PerformChargeRoute;
use App\PaymentProcessing\Api\Charges\RefundChargeRoute;
use App\PaymentProcessing\Api\Charges\RetrieveChargeRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(name: 'api_', host: '%app.api_domain%')]
class ChargeApiController extends AbstractApiController
{
    #[Route(path: '/charges', name: 'list_charges', methods: ['GET'])]
    public function listAll(ListChargesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/charges', name: 'perform_charge', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function create(PerformChargeRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/charges/{model_id}', name: 'retrieve_charge', methods: ['GET'])]
    public function retrieve(RetrieveChargeRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/charges/{model_id}/refunds', name: 'refund_charge', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function refundCharge(RefundChargeRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
