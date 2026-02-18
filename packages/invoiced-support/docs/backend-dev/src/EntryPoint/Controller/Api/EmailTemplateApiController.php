<?php

namespace App\EntryPoint\Controller\Api;

use App\Sending\Email\Api\CreateEmailTemplateRoute;
use App\Sending\Email\Api\DeleteEmailTemplateRoute;
use App\Sending\Email\Api\EditEmailTemplateRoute;
use App\Sending\Email\Api\ListEmailTemplatesRoute;
use App\Sending\Email\Api\RetrieveEmailTemplateRoute;
use App\Sending\Email\Models\EmailTemplate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class EmailTemplateApiController extends AbstractApiController
{
    #[Route(path: '/email_templates', name: 'list_email_templates', methods: ['GET'])]
    public function listAll(ListEmailTemplatesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/email_templates', name: 'create_email_template', methods: ['POST'])]
    public function create(CreateEmailTemplateRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/email_templates/{model_id}', name: 'retrieve_email_template', methods: ['GET'])]
    public function retrieve(RetrieveEmailTemplateRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/email_templates/{model_id}', name: 'edit_email_template', methods: ['PATCH'])]
    public function edit(CreateEmailTemplateRoute $createRoute, EditEmailTemplateRoute $editRoute, string $model_id): Response
    {
        $template = EmailTemplate::where('id', $model_id)->oneOrNull();

        // if the model does not exist then use the create API route,
        // otherwise perform an update
        if (!$template) {
            return $this->runRoute($createRoute);
        }

        return $this->runRoute($editRoute->setModel($template));
    }

    #[Route(path: '/email_templates/{model_id}', name: 'delete_email_template', methods: ['DELETE'])]
    public function delete(DeleteEmailTemplateRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
