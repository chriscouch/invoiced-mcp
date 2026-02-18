<?php

namespace App\EntryPoint\Controller\Api;

use App\PaymentProcessing\Api\CreateAchFileFormatRoute;
use App\PaymentProcessing\Api\DeleteAchFileFormatRoute;
use App\PaymentProcessing\Api\ListAchFileFormatsRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class AchFileFormatApiController extends AbstractApiController
{
    #[Route(path: '/ach_file_formats', name: 'list_ach_file_formats', methods: ['GET'])]
    public function listAll(ListAchFileFormatsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/ach_file_formats', name: 'create_ach_file_format', methods: ['POST'])]
    public function create(CreateAchFileFormatRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/ach_file_formats/{model_id}', name: 'delete_ach_file_format', methods: ['DELETE'])]
    public function delete(DeleteAchFileFormatRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
