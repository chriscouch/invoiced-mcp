<?php

namespace App\EntryPoint\Controller\Api;

use App\PaymentProcessing\Api\Refunds\ListRefundsRoute;
use App\PaymentProcessing\Api\Refunds\RetrieveRefundRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\PaymentProcessing\Api\Refunds\VoidRefundRoute;

#[Route(name: 'api_', host: '%app.api_domain%')]
class RefundApiController extends AbstractApiController
{
    #[Route(path: '/refunds', name: 'list_refunds', methods: ['GET'])]
    public function listAll(ListRefundsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/refunds/{model_id}', name: 'retrieve_refund', methods: ['GET'])]
    public function retrieve(RetrieveRefundRoute $route): Response
    {
        return $this->runRoute($route);
    }
    
    #[Route(path: '/refunds/{model_id}/void', name: 'void_refund', methods: ['POST'])]
    public function void(VoidRefundRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
