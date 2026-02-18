<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsReceivable\Api\GlAccounts\CreateGlAccountRoute;
use App\AccountsReceivable\Api\GlAccounts\DeleteGlAccountRoute;
use App\AccountsReceivable\Api\GlAccounts\EditGlAccountRoute;
use App\AccountsReceivable\Api\GlAccounts\ListGlAccountsRoute;
use App\AccountsReceivable\Api\GlAccounts\RetrieveGlAccountRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class GlAccountApiController extends AbstractApiController
{
    #[Route(path: '/gl_accounts', name: 'list_gl_accounts', methods: ['GET'])]
    public function listAll(ListGlAccountsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/gl_accounts', name: 'create_gl_account', methods: ['POST'])]
    public function create(CreateGlAccountRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/gl_accounts/{model_id}', name: 'retrieve_gl_account', methods: ['GET'])]
    public function retrieve(RetrieveGlAccountRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/gl_accounts/{model_id}', name: 'edit_gl_account', methods: ['PATCH'])]
    public function edit(EditGlAccountRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/gl_accounts/{model_id}', name: 'delete_gl_account', methods: ['DELETE'])]
    public function delete(DeleteGlAccountRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
