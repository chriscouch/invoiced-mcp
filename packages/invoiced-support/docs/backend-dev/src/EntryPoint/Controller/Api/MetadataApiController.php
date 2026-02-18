<?php

namespace App\EntryPoint\Controller\Api;

use App\Automations\Api\AutomationFieldsRoute;
use App\Imports\Api\ImportFieldsRoute;
use App\Reports\Api\ReportFieldsRoute;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class MetadataApiController extends AbstractApiController
{
    #[Route(path: '/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse(['status' => 'healthy']);
    }

    #[Route(path: '/_metadata/automation_fields', name: 'automation_field_metadata', methods: ['GET'])]
    public function automationFields(AutomationFieldsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/_metadata/import_fields', name: 'import_field_metadata', methods: ['GET'])]
    public function importFields(ImportFieldsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/_metadata/report_fields', name: 'report_field_metadata', methods: ['GET'])]
    public function reportFields(ReportFieldsRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
