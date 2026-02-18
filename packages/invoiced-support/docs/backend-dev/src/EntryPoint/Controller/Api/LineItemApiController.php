<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsReceivable\Api\DeleteLineItemRoute;
use App\AccountsReceivable\Api\EditLineItemRoute;
use App\AccountsReceivable\Api\RetrieveLineItemRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class LineItemApiController extends AbstractApiController
{
    #[Route(path: '/line_items/{model_id}', name: 'retrieve_line_items', methods: ['GET'])]
    public function retrieve(RetrieveLineItemRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/line_items/{model_id}', name: 'edit_line_items', methods: ['PATCH'])]
    public function edit(EditLineItemRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/line_items/{model_id}', name: 'delete_line_items', methods: ['DELETE'])]
    public function delete(DeleteLineItemRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
