<?php

namespace App\EntryPoint\Controller\Api;

use App\Integrations\Api\CheckExpenseDocumentImportStatusRoute;
use App\Integrations\Api\ImportExpenseDocumentRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class ImportExpenseDocumentApiController extends AbstractApiController
{
    #[Route(path: '/invoice/capture', name: 'expense_import', methods: ['POST'])]
    public function import(ImportExpenseDocumentRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/invoice/capture/{model_id}/completed', name: 'expense_status', methods: ['GET'])]
    public function status(CheckExpenseDocumentImportStatusRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
