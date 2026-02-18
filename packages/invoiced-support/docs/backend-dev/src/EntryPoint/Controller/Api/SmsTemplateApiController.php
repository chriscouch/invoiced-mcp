<?php

namespace App\EntryPoint\Controller\Api;

use App\Sending\Sms\Api\CreateSmsTemplateRoute;
use App\Sending\Sms\Api\DeleteSmsTemplateRoute;
use App\Sending\Sms\Api\EditSmsTemplateRoute;
use App\Sending\Sms\Api\ListSmsTemplatesRoute;
use App\Sending\Sms\Api\RetrieveSmsTemplateRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class SmsTemplateApiController extends AbstractApiController
{
    #[Route(path: '/sms_templates', name: 'list_sms_templates', methods: ['GET'])]
    public function listAll(ListSmsTemplatesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/sms_templates', name: 'create_sms_template', methods: ['POST'])]
    public function create(CreateSmsTemplateRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/sms_templates/{model_id}', name: 'retrieve_sms_template', methods: ['GET'])]
    public function retrieve(RetrieveSmsTemplateRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/sms_templates/{model_id}', name: 'edit_sms_template', methods: ['PATCH'])]
    public function edit(EditSmsTemplateRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/sms_templates/{model_id}', name: 'delete_sms_template', methods: ['DELETE'])]
    public function delete(DeleteSmsTemplateRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
