<?php

namespace App\EntryPoint\Controller\Api;

use App\PaymentProcessing\Api\Payouts\ListPayoutsRoute;
use App\PaymentProcessing\Api\Payouts\RetrievePayoutRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(name: 'api_', host: '%app.api_domain%')]
class PayoutApiController extends AbstractApiController
{
    #[Route(path: '/payouts', name: 'list_payouts', methods: ['GET'])]
    public function listAll(ListPayoutsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payouts/{model_id}', name: 'retrieve_payout', methods: ['GET'])]
    public function retrieve(RetrievePayoutRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
