<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsPayable\Api\GenerateBatchPaymentFileApiRoute;
use App\AccountsPayable\Api\PrintVendorPaymentBatchCheckApiRoute;
use App\AccountsPayable\Api\CreateVendorPaymentBatchRoute;
use App\AccountsPayable\Api\ListVendorPaymentBatchBillsRoute;
use App\AccountsPayable\Api\ListVendorPaymentBatchesRoute;
use App\AccountsPayable\Api\ListBatchBillsRoute;
use App\AccountsPayable\Api\EditVendorPaymentBatchApiRoute;
use App\AccountsPayable\Api\PayVendorPaymentBatchRoute;
use App\AccountsPayable\Api\RetrieveVendorPaymentBatchRoute;
use App\AccountsPayable\Api\VoidVendorPaymentBatchApiRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class VendorPaymentBatchApiController extends AbstractApiController
{
    #[Route(path: '/vendor_payment_batches/bills_to_pay', name: 'list_vendor_payment_batch_bills_to_pay', methods: ['GET'])]
    public function listApprovedBills(ListBatchBillsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_payment_batches', name: 'list_vendor_payment_batches', methods: ['GET'], defaults: ['expand' => 'member'])]
    public function listAll(ListVendorPaymentBatchesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_payment_batches/{model_id}', name: 'retrieve_vendor_payment_batch', methods: ['GET'])]
    public function retrieve(RetrieveVendorPaymentBatchRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_payment_batches', name: 'create_vendor_payment_batch', methods: ['POST'])]
    public function create(CreateVendorPaymentBatchRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_payment_batches/{model_id}', name: 'edit_vendor_payment_batch', methods: ['PATCH'])]
    public function edit(EditVendorPaymentBatchApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_payment_batches/{model_id}', name: 'delete_vendor_payment_batch', methods: ['DELETE'])]
    public function delete(VoidVendorPaymentBatchApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_payment_batches/{batch_id}/items', name: 'list_vendor_payment_batch_bills', methods: ['GET'])]
    public function listVendorPaymentBatchBills(ListVendorPaymentBatchBillsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_payment_batches/{model_id}/pay', name: 'pay_vendor_payment_batch', methods: ['POST'])]
    public function pay(PayVendorPaymentBatchRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_payment_batches/{model_id}/pdf', name: 'print_vendor_payment_batch', methods: ['GET'])]
    public function printCheck(PrintVendorPaymentBatchCheckApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_payment_batches/{model_id}/payment_file', name: 'vendor_payment_batch_payment_file', methods: ['POST'])]
    public function paymentFile(GenerateBatchPaymentFileApiRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
