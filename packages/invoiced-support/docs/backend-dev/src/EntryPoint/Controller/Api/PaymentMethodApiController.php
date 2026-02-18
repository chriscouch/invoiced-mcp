<?php

namespace App\EntryPoint\Controller\Api;

use App\PaymentProcessing\Api\EditPaymentMethodRoute;
use App\PaymentProcessing\Api\ListPaymentMethodsRoute;
use App\PaymentProcessing\Api\RetrievePaymentMethodRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class PaymentMethodApiController extends AbstractApiController
{
    #[Route(path: '/payment_methods', name: 'list_payment_methods', methods: ['GET'])]
    public function listAll(ListPaymentMethodsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payment_methods/{model_id}', name: 'retrieve_payment_method', methods: ['GET'])]
    public function retrieve(RetrievePaymentMethodRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payment_methods/{model_id}', name: 'edit_payment_method', methods: ['PATCH'])]
    public function edit(EditPaymentMethodRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
