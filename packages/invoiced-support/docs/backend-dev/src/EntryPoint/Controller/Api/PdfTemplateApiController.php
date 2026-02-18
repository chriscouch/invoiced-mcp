<?php

namespace App\EntryPoint\Controller\Api;

use App\Themes\Api\CreatePdfTemplateRoute;
use App\Themes\Api\DeletePdfTemplateRoute;
use App\Themes\Api\EditPdfTemplateRoute;
use App\Themes\Api\ListPdfTemplatesRoute;
use App\Themes\Api\RetrievePdfTemplateRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class PdfTemplateApiController extends AbstractApiController
{
    #[Route(path: '/pdf_templates', name: 'list_pdf_templates', methods: ['GET'])]
    public function listAll(ListPdfTemplatesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/pdf_templates', name: 'create_pdf_template', methods: ['POST'])]
    public function create(CreatePdfTemplateRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/pdf_templates/{model_id}', name: 'retrieve_pdf_template', methods: ['GET'])]
    public function retrieve(RetrievePdfTemplateRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/pdf_templates/{model_id}', name: 'edit_pdf_template', methods: ['PATCH'])]
    public function edit(EditPdfTemplateRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/pdf_templates/{model_id}', name: 'delete_pdf_template', methods: ['DELETE'])]
    public function delete(DeletePdfTemplateRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
