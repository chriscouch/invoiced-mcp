<?php

namespace App\EntryPoint\Controller\Api;

use App\Core\Authentication\OAuth\Api\CreateOAuthApplicationApiRoute;
use App\Core\Authentication\OAuth\Api\DeleteOAuthApplicationRoute;
use App\Core\Authentication\OAuth\Api\EditOAuthApplicationApiRoute;
use App\Core\Authentication\OAuth\Api\ListOAuthApplicationsApiRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class OAuthApplicationApiController extends AbstractApiController
{
    #[Route(path: '/oauth_applications', name: 'list_oauth_applications', methods: ['GET'])]
    public function listAll(ListOAuthApplicationsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/oauth_applications', name: 'create_oauth_application', methods: ['POST'])]
    public function create(CreateOAuthApplicationApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/oauth_applications/{model_id}', name: 'edit_oauth_application', methods: ['PATCH'])]
    public function edit(EditOAuthApplicationApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/oauth_applications/{model_id}', name: 'delete_oauth_application', methods: ['DELETE'])]
    public function delete(DeleteOAuthApplicationRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
