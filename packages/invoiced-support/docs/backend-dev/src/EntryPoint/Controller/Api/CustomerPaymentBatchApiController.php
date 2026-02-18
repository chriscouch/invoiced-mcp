<?php

namespace App\EntryPoint\Controller\Api;

use App\PaymentProcessing\Api\CompleteCustomerPaymentBatchRoute;
use App\PaymentProcessing\Api\CreateCustomerPaymentBatchRoute;
use App\PaymentProcessing\Api\GenerateAchDebitFileApiRoute;
use App\PaymentProcessing\Api\ListBatchChargesRoute;
use App\PaymentProcessing\Api\ListCustomerPaymentBatchesRoute;
use App\PaymentProcessing\Api\ListCustomerPaymentBatchItemsRoute;
use App\PaymentProcessing\Api\RetrieveCustomerPaymentBatchRoute;
use App\PaymentProcessing\Operations\VoidCustomerPaymentBatchApiRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class CustomerPaymentBatchApiController extends AbstractApiController
{
    #[Route(path: '/customer_payment_batches/charges_to_process', name: 'list_customer_payment_batch_charges_to_process', methods: ['GET'])]
    public function listAvailableCharges(ListBatchChargesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customer_payment_batches', name: 'list_customer_payment_batches', methods: ['GET'])]
    public function listAll(ListCustomerPaymentBatchesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customer_payment_batches/{model_id}', name: 'retrieve_customer_payment_batch', methods: ['GET'])]
    public function retrieve(RetrieveCustomerPaymentBatchRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customer_payment_batches', name: 'create_customer_payment_batch', methods: ['POST'])]
    public function create(CreateCustomerPaymentBatchRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customer_payment_batches/{model_id}', name: 'delete_customer_payment_batch', methods: ['DELETE'])]
    public function delete(VoidCustomerPaymentBatchApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customer_payment_batches/{batch_id}/items', name: 'list_customer_payment_batch_items', methods: ['GET'])]
    public function listCustomerPaymentBatchItems(ListCustomerPaymentBatchItemsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customer_payment_batches/{model_id}/complete', name: 'complete_customer_payment_batch', methods: ['POST'])]
    public function complete(CompleteCustomerPaymentBatchRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customer_payment_batches/{model_id}/payment_file', name: 'customer_payment_batch_payment_file', methods: ['POST'])]
    public function paymentFile(GenerateAchDebitFileApiRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
