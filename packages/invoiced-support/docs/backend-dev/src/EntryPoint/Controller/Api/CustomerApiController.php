<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsReceivable\Api\BalanceRoute;
use App\AccountsReceivable\Api\ConsolidateInvoicesRoute;
use App\AccountsReceivable\Api\CreateCustomerRoute;
use App\AccountsReceivable\Api\DeleteCustomerRoute;
use App\AccountsReceivable\Api\EditCustomerRoute;
use App\AccountsReceivable\Api\ListCustomerDisabledPaymentMethodsRoute;
use App\AccountsReceivable\Api\ListCustomerNotesRoute;
use App\AccountsReceivable\Api\ListCustomersRoute;
use App\AccountsReceivable\Api\ListCustomerTasksRoute;
use App\AccountsReceivable\Api\MergeCustomerRoute;
use App\AccountsReceivable\Api\RetrieveCustomerRoute;
use App\Chasing\Api\CollectionActivityRoute;
use App\Core\Files\Api\DeleteAttachmentsRoute;
use App\Core\Files\Api\ListAttachmentsRoute;
use App\Integrations\AccountingSync\Api\CustomerAccountingSyncRoute;
use App\Integrations\AccountingSync\Api\CustomerAccountingSyncStatusRoute;
use App\Integrations\GoCardless\ReinstateMandateRoute;
use App\PaymentProcessing\Api\AddPaymentSourceRoute;
use App\PaymentProcessing\Api\DeletePaymentSourceRoute;
use App\PaymentProcessing\Api\ImportPaymentSourceRoute;
use App\PaymentProcessing\Api\ListPaymentSourcesRoute;
use App\PaymentProcessing\Api\VerifyBankAccountRoute;
use App\Sending\Email\Api\SendGenericEmailRoute;
use App\Statements\Api\SendStatementEmailRoute;
use App\Statements\Api\StatementSendLetterRoute;
use App\Statements\Api\StatementSendRoute;
use App\Statements\Api\StatementSendTextMessageRoute;
use App\SubscriptionBilling\Api\BillCustomerRoute;
use App\SubscriptionBilling\Api\CreatePendingLineItemRoute;
use App\SubscriptionBilling\Api\DeletePendingLineItemRoute;
use App\SubscriptionBilling\Api\EditPendingLineItemRoute;
use App\SubscriptionBilling\Api\ListPendingLineItemsRoute;
use App\SubscriptionBilling\Api\RetrievePendingLineItemRoute;
use App\SubscriptionBilling\Api\UpcomingInvoiceRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class CustomerApiController extends AbstractApiController
{
    #[Route(path: '/customers', name: 'list_customers', methods: ['GET'])]
    public function listAll(ListCustomersRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers', name: 'create_customer', methods: ['POST'])]
    public function create(CreateCustomerRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{model_id}', name: 'retrieve_customer', methods: ['GET'])]
    public function retrieve(RetrieveCustomerRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{model_id}', name: 'edit_customer', methods: ['PATCH'])]
    public function edit(EditCustomerRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{model_id}', name: 'delete_customer', methods: ['DELETE'])]
    public function delete(DeleteCustomerRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/accounting_sync', name: 'accounting_sync_customer', methods: ['POST'])]
    public function accountingSync(CustomerAccountingSyncRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{model_id}/collection_activity', name: 'customer_collection_activity', methods: ['GET'])]
    public function customerCollectionActivity(CollectionActivityRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{model_id}/balance', name: 'customer_balance', methods: ['GET'])]
    public function customerBalance(BalanceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{customer_id}/disabled_payment_methods', name: 'list_disabled_payment_methods', methods: ['GET'])]
    public function listDisabledPaymentMethods(ListCustomerDisabledPaymentMethodsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{model_id}/notes', name: 'list_customer_notes', methods: ['GET'])]
    public function listNotes(ListCustomerNotesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{model_id}/tasks', name: 'list_customer_tasks', methods: ['GET'])]
    public function listTasks(ListCustomerTasksRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{model_id}/merge', name: 'merge_customer', methods: ['POST'])]
    public function merge(MergeCustomerRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{model_id}/send', name: 'send_statement_network', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function sendStatement(StatementSendRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{model_id}/emails/generic', name: 'send_generic_email', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function sendGenericEmail(SendGenericEmailRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{model_id}/emails', name: 'send_statement_email', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function sendStatementEmail(SendStatementEmailRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{model_id}/letters', name: 'send_statement_letter', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function sendStatementLetter(StatementSendLetterRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{model_id}/text_messages', name: 'send_statement_text_message', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function sendStatementTextMessage(StatementSendTextMessageRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{model_id}/accounting_sync_status', name: 'customer_sync_status', methods: ['GET'])]
    public function accountingSyncStatus(CustomerAccountingSyncStatusRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{model_id}/upcoming_invoice', name: 'generate_upcoming_invoice', methods: ['GET'])]
    public function generateUpcomingInvoice(UpcomingInvoiceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{customer_id}/invoices', name: 'bill_customer', methods: ['POST'])]
    public function billCustomer(BillCustomerRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{model_id}/consolidate_invoices', name: 'consolidate_invoices', methods: ['POST'])]
    public function consolidateInvoices(ConsolidateInvoicesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{model_id}/payment_sources', name: 'list_customer_payment_sources', methods: ['GET'])]
    public function listPaymentSources(ListPaymentSourcesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{model_id}/payment_sources', name: 'add_payment_source', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function addPaymentSource(AddPaymentSourceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{model_id}/import_payment_source', name: 'import_payment_source', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function importPaymentSource(ImportPaymentSourceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{customer_id}/bank_accounts/{model_id}/verify', name: 'verify_payment_method', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function verifyPaymentMethod(VerifyBankAccountRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{customer_id}/{source_type}/{model_id}', name: 'delete_payment_source', methods: ['DELETE'], requirements: ['source_type' => 'bank_accounts|cards'], defaults: ['no_database_transaction' => true])]
    public function deletePaymentSource(DeletePaymentSourceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{customer_id}/bank_accounts/{model_id}/reinstate', name: 'reinstate_direct_debit_mandate', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function reinstateDirectDebitMandate(ReinstateMandateRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{parent_id}/line_items', name: 'list_pending_line_items', methods: ['GET'])]
    public function listPendingLineItems(ListPendingLineItemsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{parent_id}/line_items', name: 'create_pending_line_item', methods: ['POST'])]
    public function createPendingLineItem(CreatePendingLineItemRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{parent_id}/line_items/{model_id}', name: 'retrieve_pending_line_item', methods: ['GET'])]
    public function retrievePendingLineItem(RetrievePendingLineItemRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{parent_id}/line_items/{model_id}', name: 'edit_pending_line_item', methods: ['PATCH'])]
    public function editPendingLineItem(EditPendingLineItemRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{parent_id}/line_items/{model_id}', name: 'delete_pending_line_item', methods: ['DELETE'])]
    public function deletePendingLineItem(DeletePendingLineItemRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{parent_id}/attachments', name: 'list_cutomer_attachments', methods: ['GET'], defaults: ['parent_type' => 'customer'])]
    public function listAttachments(ListAttachmentsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{parent_id}/attachments/{file_id}', name: 'delete_cutomer_attachments', methods: ['DELETE'], defaults: ['parent_type' => 'customer'])]
    public function deleteAttachments(DeleteAttachmentsRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
