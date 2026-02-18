<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsReceivable\Api\CreateContactRoute;
use App\AccountsReceivable\Api\DeleteContactRoute;
use App\AccountsReceivable\Api\EditContactRoute;
use App\AccountsReceivable\Api\ListContactsRoute;
use App\AccountsReceivable\Api\RetrieveContactRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class ContactApiController extends AbstractApiController
{
    #[Route(path: '/customers/{customer_id}/contacts', name: 'list_contacts', methods: ['GET'])]
    public function listAll(ListContactsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{customer_id}/contacts', name: 'create_contacts', methods: ['POST'])]
    public function create(CreateContactRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{customer_id}/contacts/{model_id}', name: 'retrieve_contact', methods: ['GET'])]
    public function retrieve(RetrieveContactRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{customer_id}/contacts/{model_id}', name: 'edit_contact', methods: ['PATCH'])]
    public function edit(EditContactRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/customers/{customer_id}/contacts/{model_id}', name: 'delete_contact', methods: ['DELETE'])]
    public function delete(DeleteContactRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
