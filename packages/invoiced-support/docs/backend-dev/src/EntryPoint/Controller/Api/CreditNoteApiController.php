<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsReceivable\Api\CreateCreditNoteLineItemRoute;
use App\AccountsReceivable\Api\CreateCreditNoteRoute;
use App\AccountsReceivable\Api\DeleteCreditNoteLineItemRoute;
use App\AccountsReceivable\Api\DeleteCreditNoteRoute;
use App\AccountsReceivable\Api\EditCreditNoteLineItemRoute;
use App\AccountsReceivable\Api\EditCreditNoteRoute;
use App\AccountsReceivable\Api\ListCreditNoteLineItemsRoute;
use App\AccountsReceivable\Api\ListCreditNotesRoute;
use App\AccountsReceivable\Api\RetrieveCreditNoteLineItemRoute;
use App\AccountsReceivable\Api\RetrieveDocumentRoute;
use App\AccountsReceivable\Api\VoidCreditNoteRoute;
use App\AccountsReceivable\Models\CreditNote;
use App\Core\Files\Api\ListAttachmentsRoute;
use App\Integrations\AccountingSync\Api\CreditNoteAccountingSyncRoute;
use App\Integrations\AccountingSync\Api\CreditNoteAccountingSyncStatusRoute;
use App\Network\Api\SendNetworkDocumentApiRoute;
use App\Sending\Email\Api\SendDocumentEmailRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class CreditNoteApiController extends AbstractApiController
{
    #[Route(path: '/credit_notes', name: 'list_credit_notes', methods: ['GET'])]
    public function listAll(ListCreditNotesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/credit_notes', name: 'create_credit_note', methods: ['POST'])]
    public function create(CreateCreditNoteRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/credit_notes/{model_id}', name: 'retrieve_credit_note', methods: ['GET'])]
    public function retrieve(RetrieveDocumentRoute $route): Response
    {
        return $this->runRoute($route->setModelClass(CreditNote::class));
    }

    #[Route(path: '/credit_notes/{model_id}', name: 'edit_credit_note', methods: ['PATCH'])]
    public function edit(EditCreditNoteRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/credit_notes/{model_id}', name: 'delete_credit_note', methods: ['DELETE'])]
    public function delete(DeleteCreditNoteRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/credit_notes/accounting_sync', name: 'accounting_sync_credit_note', methods: ['POST'])]
    public function accountingSync(CreditNoteAccountingSyncRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/credit_notes/{parent_id}/line_items', name: 'list_credit_note_line_items', methods: ['GET'])]
    public function listLineItems(ListCreditNoteLineItemsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/credit_notes/{parent_id}/line_items', name: 'create_credit_note_line_item', methods: ['POST'])]
    public function createLineItem(CreateCreditNoteLineItemRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/credit_notes/{parent_id}/line_items/{model_id}', name: 'retrieve_credit_note_line_item', methods: ['GET'])]
    public function retrieveLineItem(RetrieveCreditNoteLineItemRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/credit_notes/{parent_id}/line_items/{model_id}', name: 'edit_credit_note_line_item', methods: ['PATCH'])]
    public function editLineItem(EditCreditNoteLineItemRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/credit_notes/{parent_id}/line_items/{model_id}', name: 'delete_credit_note_line_item', methods: ['DELETE'])]
    public function deleteLineItem(DeleteCreditNoteLineItemRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/credit_notes/{parent_id}/attachments', name: 'list_credit_note_attachments', methods: ['GET'], defaults: ['parent_type' => 'credit_note'])]
    public function listAttachments(ListAttachmentsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/credit_notes/{model_id}/emails', name: 'send_credit_note_email', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function sendEmail(SendDocumentEmailRoute $route): Response
    {
        return $this->runRoute($route->setModelClass(CreditNote::class));
    }

    #[Route(path: '/credit_notes/{model_id}/send', name: 'send_credit_note_network', methods: ['POST'])]
    public function sendThroughNetwork(SendNetworkDocumentApiRoute $route): Response
    {
        return $this->runRoute($route->setModelClass(CreditNote::class));
    }

    #[Route(path: '/credit_notes/{model_id}/void', name: 'void_credit_note', methods: ['POST'])]
    public function void(VoidCreditNoteRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/credit_notes/{model_id}/accounting_sync_status', name: 'credit_note_sync_status', methods: ['GET'])]
    public function accountingSyncStatus(CreditNoteAccountingSyncStatusRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
