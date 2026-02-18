<?php

namespace App\EntryPoint\Controller\Api;

use App\Themes\Api\CreateTemplateRoute;
use App\Themes\Api\EditTemplateRoute;
use App\Themes\Api\ListTemplatesRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class TemplateApiController extends AbstractApiController
{
    #[Route(path: '/templates', name: 'create_template', methods: ['POST'])]
    public function create(CreateTemplateRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/templates', name: 'list_templates', methods: ['GET'])]
    public function listAll(ListTemplatesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/templates/{model_id}', name: 'edit_template', methods: ['PATCH'])]
    public function edit(EditTemplateRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
