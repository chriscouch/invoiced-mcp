<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsPayable\Api\CreateVendorRoute;
use App\AccountsPayable\Api\EditVendorRoute;
use App\AccountsPayable\Api\RetrieveVendorRoute;
use App\AccountsPayable\Api\VendorBalanceApiRoute;
use App\AccountsPayable\Api\ListVendorsApiRoute;
use App\AccountsPayable\Api\VendorPaymentMethodsApiRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class VendorApiController extends AbstractApiController
{
    #[Route(path: '/vendors', name: 'list_vendors', methods: ['GET'])]
    public function listAll(ListVendorsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendors', name: 'create_vendor', methods: ['POST'])]
    public function create(CreateVendorRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendors/{model_id}', name: 'retrieve_vendor', methods: ['GET'])]
    public function retrieve(RetrieveVendorRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendors/{model_id}', name: 'edit_vendor', methods: ['PATCH'])]
    public function edit(EditVendorRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendors/{model_id}/balance', name: 'vendor_balance', methods: ['GET'])]
    public function balance(VendorBalanceApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendors/{model_id}/payment_methods', name: 'vendor_payment_methods', methods: ['GET'])]
    public function paymentMethods(VendorPaymentMethodsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
