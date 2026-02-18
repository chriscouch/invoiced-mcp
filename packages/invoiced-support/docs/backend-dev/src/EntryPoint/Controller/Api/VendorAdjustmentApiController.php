<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsPayable\Api\CreateVendorAdjustmentApiRoute;
use App\AccountsPayable\Api\ListVendorAdjustmentsApiRoute;
use App\AccountsPayable\Api\RetrieveVendorAdjustmentRoute;
use App\AccountsPayable\Api\VoidVendorAdjustmentApiRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class VendorAdjustmentApiController extends AbstractApiController
{
    #[Route(path: '/vendor_adjustments', name: 'list_vendor_adjustment', methods: ['GET'])]
    public function listAll(ListVendorAdjustmentsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_adjustments/{model_id}', name: 'retrieve_vendor_adjustment', methods: ['GET'])]
    public function retrieve(RetrieveVendorAdjustmentRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_adjustments', name: 'create_vendor_adjustment', methods: ['POST'])]
    public function create(CreateVendorAdjustmentApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_adjustments/{model_id}', name: 'delete_vendor_adjustment', methods: ['DELETE'])]
    public function delete(VoidVendorAdjustmentApiRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
