<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsReceivable\Api\CreatePaymentTermsRoute;
use App\AccountsReceivable\Api\DeletePaymentTermsRoute;
use App\AccountsReceivable\Api\EditPaymentTermsRoute;
use App\AccountsReceivable\Api\ListPaymentTermsRoute;
use App\AccountsReceivable\Api\RetrievePaymentTermsRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class PaymentTermsApiController extends AbstractApiController
{
    #[Route(path: '/payment_terms', name: 'list_payment_terms', methods: ['GET'])]
    public function listAll(ListPaymentTermsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payment_terms', name: 'create_payment_terms', methods: ['POST'])]
    public function create(CreatePaymentTermsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payment_terms/{model_id}', name: 'retrieve_payment_terms', methods: ['GET'])]
    public function retrieve(RetrievePaymentTermsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payment_terms/{model_id}', name: 'update_payment_terms', methods: ['PATCH'])]
    public function update(EditPaymentTermsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payment_terms/{model_id}', name: 'delete_payment_terms', methods: ['DELETE'])]
    public function delete(DeletePaymentTermsRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
