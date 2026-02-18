<?php

namespace App\EntryPoint\Controller\Api;

use App\CashApplication\Api\CreatePaymentRoute;
use App\CashApplication\Api\EditPaymentRoute;
use App\CashApplication\Api\ListPaymentsRoute;
use App\CashApplication\Api\RetrievePaymentRoute;
use App\CashApplication\Api\VoidPaymentRoute;
use App\CashApplication\Models\Payment;
use App\Core\Files\Api\ListAttachmentsRoute;
use App\Integrations\AccountingSync\Api\PaymentAccountingSyncRoute;
use App\Integrations\AccountingSync\Api\PaymentAccountingSyncStatusRoute;
use App\Integrations\AccountingSync\Api\PaymentCaptureRoute;
use App\Integrations\AccountingSync\Api\RetrievePaymentCaptureRoute;
use App\Sending\Email\Api\SendDocumentEmailRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class PaymentApiController extends AbstractApiController
{
    #[Route(path: '/payments', name: 'list_payments', methods: ['GET'])]
    public function listAll(ListPaymentsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payments', name: 'create_payment', methods: ['POST'])]
    public function create(CreatePaymentRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payments/{model_id}', name: 'retrieve_payment', methods: ['GET'])]
    public function retrieve(RetrievePaymentRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payments/{model_id}', name: 'edit_payment', methods: ['PATCH'])]
    public function edit(EditPaymentRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payments/{model_id}', name: 'delete_payment', methods: ['DELETE'])]
    public function delete(VoidPaymentRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payments/accounting_sync', name: 'accounting_sync_payment', methods: ['POST'])]
    public function accountingSync(PaymentAccountingSyncRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payments/{parent_id}/attachments', name: 'list_payment_attachments', methods: ['GET'], defaults: ['parent_type' => 'payment'])]
    public function listAttachments(ListAttachmentsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payments/{model_id}/emails', name: 'send_payment_email', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function sendEmail(SendDocumentEmailRoute $route): Response
    {
        return $this->runRoute($route->setModelClass(Payment::class));
    }

    #[Route(path: '/payments/{model_id}/accounting_sync_status', name: 'payment_sync_status', methods: ['GET'])]
    public function accountingSyncStatus(PaymentAccountingSyncStatusRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payments/{model_id}/capture', name: 'payment_capture', methods: ['GET'])]
    public function paymentCapture(RetrievePaymentCaptureRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/payments/{model_id}/capture', name: 'capture', methods: ['POST'])]
    public function capture(PaymentCaptureRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
