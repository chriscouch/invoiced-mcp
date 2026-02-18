<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsPayable\Api\AddVendorPaymentAttachmentsApiRoute;
use App\AccountsPayable\Api\CreateVendorPaymentApiRoute;
use App\AccountsPayable\Api\EditVendorPaymentApiRoute;
use App\AccountsPayable\Api\ListVendorPaymentAttachmentsApiRoute;
use App\AccountsPayable\Api\ListVendorPaymentsApiRoute;
use App\AccountsPayable\Api\PayVendorRoute;
use App\AccountsPayable\Api\PrintVendorPaymentCheckApiRoute;
use App\AccountsPayable\Api\RetrieveVendorPaymentRoute;
use App\AccountsPayable\Api\VoidVendorPaymentApiRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class VendorPaymentApiController extends AbstractApiController
{
    #[Route(path: '/pay', name: 'pay_vendor', methods: ['POST'])]
    public function payVendor(PayVendorRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_payments', name: 'list_vendor_payment', methods: ['GET'])]
    public function listAll(ListVendorPaymentsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_payments/{model_id}', name: 'retrieve_vendor_payment', methods: ['GET'])]
    public function retrieve(RetrieveVendorPaymentRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_payments', name: 'create_vendor_payment', methods: ['POST'])]
    public function create(CreateVendorPaymentApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_payments/{model_id}', name: 'edit_vendor_payment', methods: ['PATCH'])]
    public function edit(EditVendorPaymentApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_payments/{model_id}', name: 'delete_vendor_payment', methods: ['DELETE'])]
    public function delete(VoidVendorPaymentApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_payments/{parent_id}/attachments', name: 'list_vendor_payment_attachments', methods: ['GET'])]
    public function listAttachments(ListVendorPaymentAttachmentsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_payments/{vendor_payment_id}/attachment', name: 'add_vendor_payment_attachments', methods: ['POST'])]
    public function addAttachments(AddVendorPaymentAttachmentsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_payments/{model_id}/pdf', name: 'print_vendor_payment', methods: ['GET'])]
    public function printCheck(PrintVendorPaymentCheckApiRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
