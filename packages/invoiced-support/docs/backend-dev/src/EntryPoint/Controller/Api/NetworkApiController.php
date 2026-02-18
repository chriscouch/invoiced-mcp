<?php

namespace App\EntryPoint\Controller\Api;

use App\Network\Api\DeleteNetworkConnectionApiRoute;
use App\Network\Api\DeleteNetworkInvitationApiRoute;
use App\Network\Api\ListNetworkCustomersApiRoute;
use App\Network\Api\ListNetworkDocumentsApiRoute;
use App\Network\Api\ListNetworkInvitationsApiRoute;
use App\Network\Api\ListNetworkVendorsApiRoute;
use App\Network\Api\RetrieveNetworkCustomerApiRoute;
use App\Network\Api\RetrieveNetworkDocumentApiRoute;
use App\Network\Api\RetrieveNetworkVendorApiRoute;
use App\Network\Api\SendNetworkInvitationApiRoute;
use App\Network\Api\SetNetworkDocumentStatusApiRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class NetworkApiController extends AbstractApiController
{
    #[Route(path: '/network/invitations', name: 'send_network_invitation', methods: ['POST'])]
    public function sendNetworkInvitation(SendNetworkInvitationApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/network/invitations', name: 'list_network_invitations', methods: ['GET'])]
    public function listAllInvitations(ListNetworkInvitationsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/network/invitations/{model_id}', name: 'delete_network_invitation', methods: ['DELETE'])]
    public function deleteInvitation(DeleteNetworkInvitationApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/network/customers', name: 'list_network_customers', methods: ['GET'])]
    public function listCustomers(ListNetworkCustomersApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/network/customers/{model_id}', name: 'retrieve_network_customer', methods: ['GET'])]
    public function retrieveVendor(RetrieveNetworkCustomerApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/network/customers/{model_id}', name: 'delete_network_customer', methods: ['DELETE'])]
    public function deleteCustomer(DeleteNetworkConnectionApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/network/vendors', name: 'list_network_vendors', methods: ['GET'])]
    public function listVendors(ListNetworkVendorsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/network/vendors/{model_id}', name: 'retrieve_network_vendor', methods: ['GET'])]
    public function retrieveCustomer(RetrieveNetworkVendorApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/network/vendors/{model_id}', name: 'delete_network_vendor', methods: ['DELETE'])]
    public function deleteVendor(DeleteNetworkConnectionApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/network/documents', name: 'list_network_documents', methods: ['GET'])]
    public function listAllDocuments(ListNetworkDocumentsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/network/documents/{model_id}', name: 'retrieve_network_document', methods: ['GET'])]
    public function retrieveDocument(RetrieveNetworkDocumentApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/network/documents/{model_id}/current_status', name: 'set_network_document_status', methods: ['POST'])]
    public function setDocumentStatus(SetNetworkDocumentStatusApiRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
