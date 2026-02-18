<?php

namespace App\EntryPoint\Controller\Api;

use App\Chasing\Api\CreateInvoiceChasingCadenceRoute;
use App\Chasing\Api\DeleteInvoiceChasingCadenceRoute;
use App\Chasing\Api\EditInvoiceChasingCadenceRoute;
use App\Chasing\Api\ListInvoiceChasingCadencesRoute;
use App\Chasing\Api\RetrieveInvoiceChasingCadenceRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class InvoiceChasingCadenceApiController extends AbstractApiController
{
    #[Route(path: '/invoice_chasing_cadences', name: 'list_invoice_chasing_cadences', methods: ['GET'])]
    public function listAll(ListInvoiceChasingCadencesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoice_chasing_cadences', name: 'create_invoice_chasing_cadence', methods: ['POST'])]
    public function create(CreateInvoiceChasingCadenceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoice_chasing_cadences/{model_id}', name: 'retrieve_invoice_chasing_cadence', methods: ['GET'])]
    public function retrieve(RetrieveInvoiceChasingCadenceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoice_chasing_cadences/{model_id}', name: 'edit_invoice_chasing_cadence', methods: ['PATCH'])]
    public function edit(EditInvoiceChasingCadenceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoice_chasing_cadences/{model_id}', name: 'delete_invoice_chasing_cadence', methods: ['DELETE'])]
    public function delete(DeleteInvoiceChasingCadenceRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
