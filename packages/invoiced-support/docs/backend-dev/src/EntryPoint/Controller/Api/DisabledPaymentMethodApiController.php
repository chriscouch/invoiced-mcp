<?php

namespace App\EntryPoint\Controller\Api;

use App\PaymentProcessing\Api\CreateDisabledPaymentMethodRoute;
use App\PaymentProcessing\Api\DeleteDisabledPaymentRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class DisabledPaymentMethodApiController extends AbstractApiController
{
    #[Route(path: '/disabled_payment_methods', name: 'create_disabled_payment_method', methods: ['POST'])]
    public function createDisabledPaymentMethod(CreateDisabledPaymentMethodRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/disabled_payment_methods/{model_id}', name: 'delete_disabled_payment_method', methods: ['DELETE'])]
    public function deleteDisabledPaymentMethod(DeleteDisabledPaymentRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
