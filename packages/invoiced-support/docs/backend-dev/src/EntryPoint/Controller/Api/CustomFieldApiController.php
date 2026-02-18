<?php

namespace App\EntryPoint\Controller\Api;

use App\Metadata\Api\CreateCustomFieldRoute;
use App\Metadata\Api\DeleteCustomFieldRoute;
use App\Metadata\Api\EditCustomFieldRoute;
use App\Metadata\Api\ListCustomFieldsRoute;
use App\Metadata\Api\RetrieveCustomFieldRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class CustomFieldApiController extends AbstractApiController
{
    #[Route(path: '/custom_fields', name: 'list_custom_fields', methods: ['GET'])]
    public function listAll(ListCustomFieldsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/custom_fields', name: 'create_custom_field', methods: ['POST'])]
    public function create(CreateCustomFieldRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/custom_fields/{model_id}', name: 'retrieve_custom_field', methods: ['GET'])]
    public function retrieve(RetrieveCustomFieldRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/custom_fields/{model_id}', name: 'edit_custom_field', methods: ['PATCH'])]
    public function edit(EditCustomFieldRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/custom_fields/{model_id}', name: 'delete_custom_field', methods: ['DELETE'])]
    public function delete(DeleteCustomFieldRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
