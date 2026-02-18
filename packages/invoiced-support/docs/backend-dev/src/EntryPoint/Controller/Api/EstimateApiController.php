<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsReceivable\Api\CreateEstimateLineItemRoute;
use App\AccountsReceivable\Api\CreateEstimateRoute;
use App\AccountsReceivable\Api\DeleteEstimateLineItemRoute;
use App\AccountsReceivable\Api\DeleteEstimateRoute;
use App\AccountsReceivable\Api\EditEstimateLineItemRoute;
use App\AccountsReceivable\Api\EditEstimateRoute;
use App\AccountsReceivable\Api\GenerateInvoiceRoute;
use App\AccountsReceivable\Api\ListEstimateLineItemsRoute;
use App\AccountsReceivable\Api\ListEstimatesRoute;
use App\AccountsReceivable\Api\RetrieveDocumentRoute;
use App\AccountsReceivable\Api\RetrieveEstimateLineItemRoute;
use App\AccountsReceivable\Api\VoidEstimateRoute;
use App\AccountsReceivable\Models\Estimate;
use App\Core\Files\Api\ListAttachmentsRoute;
use App\Network\Api\SendNetworkDocumentApiRoute;
use App\Sending\Email\Api\SendDocumentEmailRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class EstimateApiController extends AbstractApiController
{
    #[Route(path: '/estimates', name: 'list_estimates', methods: ['GET'])]
    public function listAll(ListEstimatesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/estimates', name: 'create_estimate', methods: ['POST'])]
    public function create(CreateEstimateRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/estimates/{model_id}', name: 'retrieve_estimate', methods: ['GET'])]
    public function retrieve(RetrieveDocumentRoute $route): Response
    {
        return $this->runRoute($route->setModelClass(Estimate::class));
    }

    #[Route(path: '/estimates/{model_id}', name: 'edit_estimate', methods: ['PATCH'])]
    public function edit(EditEstimateRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/estimates/{model_id}', name: 'delete_estimate', methods: ['DELETE'])]
    public function delete(DeleteEstimateRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/estimates/{parent_id}/line_items', name: 'list_estimate_line_items', methods: ['GET'])]
    public function listLineItems(ListEstimateLineItemsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/estimates/{parent_id}/line_items', name: 'create_estimate_line_item', methods: ['POST'])]
    public function createLineItem(CreateEstimateLineItemRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/estimates/{parent_id}/line_items/{model_id}', name: 'retrieve_estimate_line_item', methods: ['GET'])]
    public function retrieveLineItem(RetrieveEstimateLineItemRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/estimates/{parent_id}/line_items/{model_id}', name: 'edit_estimate_line_item', methods: ['PATCH'])]
    public function editLineItem(EditEstimateLineItemRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/estimates/{parent_id}/line_items/{model_id}', name: 'delete_estimate_line_item', methods: ['DELETE'])]
    public function deleteLineItem(DeleteEstimateLineItemRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/estimates/{parent_id}/attachments', name: 'list_estimate_attachments', methods: ['GET'], defaults: ['parent_type' => 'estimate'])]
    public function listAttachments(ListAttachmentsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/estimates/{model_id}/invoice', name: 'generate_invoice_from_estimate', methods: ['POST'])]
    public function generateInvoice(GenerateInvoiceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/estimates/{model_id}/emails', name: 'send_estimate_email', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function sendEmail(SendDocumentEmailRoute $route): Response
    {
        return $this->runRoute($route->setModelClass(Estimate::class));
    }

    #[Route(path: '/estimates/{model_id}/send', name: 'send_estimate_network', methods: ['POST'])]
    public function sendThroughNetwork(SendNetworkDocumentApiRoute $route): Response
    {
        return $this->runRoute($route->setModelClass(Estimate::class));
    }

    #[Route(path: '/estimates/{model_id}/void', name: 'void_estimate', methods: ['POST'])]
    public function void(VoidEstimateRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
