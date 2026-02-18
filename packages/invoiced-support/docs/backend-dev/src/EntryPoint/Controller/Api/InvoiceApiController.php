<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsReceivable\Api\BadDebtInvoiceRoute;
use App\AccountsReceivable\Api\CreateInvoiceLineItemRoute;
use App\AccountsReceivable\Api\CreateInvoiceRoute;
use App\AccountsReceivable\Api\DeleteInvoiceLineItemRoute;
use App\AccountsReceivable\Api\DeleteInvoiceRoute;
use App\AccountsReceivable\Api\EditInvoiceDistributionRoute;
use App\AccountsReceivable\Api\EditInvoiceLineItemRoute;
use App\AccountsReceivable\Api\EditInvoiceRoute;
use App\AccountsReceivable\Api\ListInvoiceDistributions;
use App\AccountsReceivable\Api\ListInvoiceLineItemsRoute;
use App\AccountsReceivable\Api\ListInvoiceNotesRoute;
use App\AccountsReceivable\Api\ListInvoicesRoute;
use App\AccountsReceivable\Api\ListInvoiceTemplatesRoute;
use App\AccountsReceivable\Api\PayInvoiceRoute;
use App\AccountsReceivable\Api\RetrieveDocumentRoute;
use App\AccountsReceivable\Api\RetrieveInvoiceDeliveryRoute;
use App\AccountsReceivable\Api\RetrieveInvoiceLineItemRoute;
use App\AccountsReceivable\Api\SetInvoiceDeliveryRoute;
use App\AccountsReceivable\Api\VoidInvoiceRoute;
use App\AccountsReceivable\Models\Invoice;
use App\Chasing\Api\InvoiceChaseStateRoute;
use App\Core\Files\Api\ListAttachmentsRoute;
use App\Integrations\AccountingSync\Api\InvoiceAccountingSyncRoute;
use App\Integrations\AccountingSync\Api\InvoiceAccountingSyncStatusRoute;
use App\Network\Api\SendNetworkDocumentApiRoute;
use App\PaymentPlans\Api\CancelPaymentPlanRoute;
use App\PaymentPlans\Api\RetrievePaymentPlanRoute;
use App\PaymentPlans\Api\SetPaymentPlanRoute;
use App\Sending\Email\Api\SendDocumentEmailRoute;
use App\Sending\Mail\Api\SendLetterRoute;
use App\Sending\Sms\Api\SendTextMessageRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class InvoiceApiController extends AbstractApiController
{
    #[Route(path: '/invoices/{parent_id}/delivery', name: 'set_invoice_delivery', methods: ['PUT'])]
    public function setInvoiceDelivery(SetInvoiceDeliveryRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices/{parent_id}/delivery', name: 'retrieve_invoice_delivery', methods: ['GET'])]
    public function retrieveInvoiceDelivery(RetrieveInvoiceDeliveryRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices/{parent_id}/delivery/state', name: 'invoice_delivery_state', methods: ['GET'])]
    public function getInvoiceChaseState(InvoiceChaseStateRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoice_distributions/{model_id}', name: 'edit_invoice_distribution', methods: ['PATCH'])]
    public function editInvoiceDistribution(EditInvoiceDistributionRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices', name: 'list_invoices', methods: ['GET'])]
    public function listAll(ListInvoicesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices', name: 'create_invoice', methods: ['POST'])]
    public function create(CreateInvoiceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices/{model_id}', name: 'retrieve_invoice', methods: ['GET'])]
    public function retrieve(RetrieveDocumentRoute $route): Response
    {
        return $this->runRoute($route->setModelClass(Invoice::class));
    }

    #[Route(path: '/invoices/{model_id}', name: 'edit_invoice', methods: ['PATCH'])]
    public function edit(EditInvoiceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices/{model_id}', name: 'delete_invoice', methods: ['DELETE'])]
    public function delete(DeleteInvoiceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices/accounting_sync', name: 'accounting_sync_invoice', methods: ['POST'])]
    public function accountingSync(InvoiceAccountingSyncRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices/{parent_id}/line_items', name: 'list_invoice_line_items', methods: ['GET'])]
    public function listLineItems(ListInvoiceLineItemsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices/{parent_id}/line_items', name: 'create_invoice_line_item', methods: ['POST'])]
    public function createLineItem(CreateInvoiceLineItemRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices/{parent_id}/line_items/{model_id}', name: 'retrieve_invoice_line_item', methods: ['GET'])]
    public function retrieveLineItem(RetrieveInvoiceLineItemRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices/{parent_id}/line_items/{model_id}', name: 'edit_invoice_line_item', methods: ['PATCH'])]
    public function editLineItem(EditInvoiceLineItemRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices/{parent_id}/line_items/{model_id}', name: 'delete_invoice_line_item', methods: ['DELETE'])]
    public function deleteLineItem(DeleteInvoiceLineItemRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices/{model_id}/pay', name: 'pay_invoice', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function pay(PayInvoiceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices/{parent_id}/attachments', name: 'list_invoice_attachments', methods: ['GET'], defaults: ['parent_type' => 'invoice'])]
    public function listAttachments(ListAttachmentsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices/{model_id}/distributions', name: 'list_invoice_distributions', methods: ['GET'])]
    public function listInvoiceDistributions(ListInvoiceDistributions $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices/{model_id}/notes', name: 'list_invoice_notes', methods: ['GET'])]
    public function listNotes(ListInvoiceNotesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices/{model_id}/emails', name: 'send_invoice_email', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function sendEmail(SendDocumentEmailRoute $route): Response
    {
        return $this->runRoute($route->setModelClass(Invoice::class));
    }

    #[Route(path: '/invoices/{model_id}/letters', name: 'send_invoice_letter', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function sendLetter(SendLetterRoute $route): Response
    {
        return $this->runRoute($route->setModelClass(Invoice::class));
    }

    #[Route(path: '/invoices/{model_id}/text_messages', name: 'send_invoice_text_message', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function sendTextMessage(SendTextMessageRoute $route): Response
    {
        return $this->runRoute($route->setModelClass(Invoice::class));
    }

    #[Route(path: '/invoices/{model_id}/send', name: 'send_invoice_network', methods: ['POST'])]
    public function sendThroughNetwork(SendNetworkDocumentApiRoute $route): Response
    {
        return $this->runRoute($route->setModelClass(Invoice::class));
    }

    #[Route(path: '/invoices/{model_id}/void', name: 'void_invoice', methods: ['POST'])]
    public function void(VoidInvoiceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices/{model_id}/bad_debt', name: 'bad_debt_invoice', methods: ['POST'])]
    public function badDebt(BadDebtInvoiceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices/{model_id}/accounting_sync_status', name: 'invoice_sync_status', methods: ['GET'])]
    public function accountingSyncStatus(InvoiceAccountingSyncStatusRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices/{model_id}/payment_plan', name: 'retrieve_payment_plan', methods: ['GET'])]
    public function retrievePaymentPlan(RetrievePaymentPlanRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices/{model_id}/payment_plan', name: 'set_payment_plan', methods: ['PUT'])]
    public function setPaymentPlan(SetPaymentPlanRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoices/{model_id}/payment_plan', name: 'cancel_payment_plan', methods: ['DELETE'])]
    public function cancelPaymentPlan(CancelPaymentPlanRoute $route): Response
    {
        return $this->runRoute($route);
    }

    /*
     * =========
     * Invoice Templates API (deprecated)
     * =========
     */
    #[Route(path: '/invoice_templates', name: 'list_invoice_templates', methods: ['GET'])]
    public function listInvoiceTemplates(ListInvoiceTemplatesRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
