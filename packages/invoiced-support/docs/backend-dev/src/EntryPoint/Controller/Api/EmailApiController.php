<?php

namespace App\EntryPoint\Controller\Api;

use App\Core\Files\Api\ListAttachmentsRoute;
use App\Sending\Email\Api\CreateInboxThreadNoteRoute;
use App\Sending\Email\Api\DeleteInboxThreadNoteRoute;
use App\Sending\Email\Api\EditInboxThreadNoteRoute;
use App\Sending\Email\Api\EditInboxThreadRoute;
use App\Sending\Email\Api\EmailAutocompleteRoute;
use App\Sending\Email\Api\InboxMigrateRoute;
use App\Sending\Email\Api\ListInboxEmailsRoute;
use App\Sending\Email\Api\ListInboxesRoute;
use App\Sending\Email\Api\ListInboxThreadEmailsRoute;
use App\Sending\Email\Api\ListInboxThreadNotesRoute;
use App\Sending\Email\Api\ListInboxThreadsRoute;
use App\Sending\Email\Api\ListLegacyEmailsRoute;
use App\Sending\Email\Api\RetrieveEmailThreadRoute;
use App\Sending\Email\Api\RetrieveInboxEmailBodyRoute;
use App\Sending\Email\Api\RetrieveInboxEmailRoute;
use App\Sending\Email\Api\RetrieveInboxRoute;
use App\Sending\Email\Api\RetrieveInboxThreadByDocumentRoute;
use App\Sending\Email\Api\RetrieveLegacyEmailRoute;
use App\Sending\Email\Api\SendEmailRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class EmailApiController extends AbstractApiController
{
    /*
     * =========
     * Legacy Emails API
     * =========
     */
    #[Route(path: '/emails', name: 'list_emails', methods: ['GET'])]
    public function listLegacyEmails(ListLegacyEmailsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/emails/{model_id}', name: 'retrieve_email', methods: ['GET'])]
    public function retrieveLegacyEmail(RetrieveLegacyEmailRoute $route): Response
    {
        return $this->runRoute($route);
    }

    /*
     * =========
     * Inboxes API
     * =========
     */
    #[Route(path: '/inboxes/migrate', name: 'migrate_inbox', methods: ['POST'])]
    public function inboxMigrate(InboxMigrateRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/inboxes', name: 'list_inboxes', methods: ['GET'])]
    public function listInboxes(ListInboxesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/inboxes/{model_id}', name: 'retrieve_inbox', methods: ['GET'])]
    public function retrieveInbox(RetrieveInboxRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/inboxes/{model_id}/threads', name: 'retrieve_inbox_threads', methods: ['GET'])]
    public function listInboxesThreads(ListInboxThreadsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/inboxes/{model_id}/emails', name: 'retrieve_inbox_emails', methods: ['GET'])]
    public function listInboxesEmails(ListInboxEmailsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/threads/{model_id}', name: 'edit_inbox_thread', methods: ['PATCH'])]
    public function editInboxThread(EditInboxThreadRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/threads/{model_id}', name: 'retrieve_inbox_thread', methods: ['GET'])]
    public function retrieveInboxThread(RetrieveEmailThreadRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/threads/document/{document_type}/{document_id}', name: 'retrieve_inbox_thread_by_document', methods: ['GET'])]
    public function retrieveInboxThreadByDocument(RetrieveInboxThreadByDocumentRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/threads/{thread_id}/notes/{model_id}', name: 'edit_inbox_thread_note', methods: ['PATCH'])]
    public function editInboxThreadNote(EditInboxThreadNoteRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/threads/{thread_id}/notes', name: 'create_inbox_thread_note', methods: ['POST'])]
    public function createInboxThreadNote(CreateInboxThreadNoteRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/threads/{thread_id}/notes/{model_id}', name: 'delete_inbox_thread_note', methods: ['DELETE'])]
    public function deleteInboxThreadNote(DeleteInboxThreadNoteRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/threads/{thread_id}/notes', name: 'list_inbox_thread_note', methods: ['GET'])]
    public function listThreadNotes(ListInboxThreadNotesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/threads/{parent_id}/emails', name: 'list_thread_email', methods: ['GET'])]
    public function listThreadEmails(ListInboxThreadEmailsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/emails/{model_id}', name: 'retrieve_inbox_email', methods: ['GET'])]
    public function retrieveInboxEmail(RetrieveInboxEmailRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/emails/{parent_id}/attachments', name: 'list_emails_attachments', methods: ['GET'], defaults: ['parent_type' => 'email'])]
    public function listEmailAttachments(ListAttachmentsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/emails/{model_id}/message', name: 'retrieve_inbox_email_message', methods: ['GET'])]
    public function retrieveInboxEmailBody(RetrieveInboxEmailBodyRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/inboxes/{inbox_id}/emails', name: 'send_inbox_email', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function sendInboxEmail(SendEmailRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/autocomplete/emails', name: 'list_email_autocomplete', methods: ['GET'])]
    public function emailAutocomplete(EmailAutocompleteRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
