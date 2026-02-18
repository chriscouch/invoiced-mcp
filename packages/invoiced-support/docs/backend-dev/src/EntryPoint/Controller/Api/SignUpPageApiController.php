<?php

namespace App\EntryPoint\Controller\Api;

use App\CustomerPortal\Api\CreateSignUpPageAddonRoute;
use App\CustomerPortal\Api\CreateSignUpPageRoute;
use App\CustomerPortal\Api\DeleteSignUpPageAddonRoute;
use App\CustomerPortal\Api\DeleteSignUpPageRoute;
use App\CustomerPortal\Api\EditSignUpPageAddonRoute;
use App\CustomerPortal\Api\EditSignUpPageRoute;
use App\CustomerPortal\Api\ListSignUpPageAddonsRoute;
use App\CustomerPortal\Api\ListSignUpPagesRoute;
use App\CustomerPortal\Api\RetrieveSignUpPageAddonRoute;
use App\CustomerPortal\Api\RetrieveSignUpPageRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class SignUpPageApiController extends AbstractApiController
{
    #[Route(path: '/sign_up_pages', name: 'list_sign_up_pages', methods: ['GET'])]
    public function listAll(ListSignUpPagesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/sign_up_pages', name: 'create_sign_up_page', methods: ['POST'])]
    public function create(CreateSignUpPageRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/sign_up_pages/{model_id}', name: 'retrieve_sign_up_page', methods: ['GET'])]
    public function retrieve(RetrieveSignUpPageRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/sign_up_pages/{model_id}', name: 'edit_sign_up_page', methods: ['PATCH'])]
    public function edit(EditSignUpPageRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/sign_up_pages/{model_id}', name: 'delete_sign_up_page', methods: ['DELETE'])]
    public function delete(DeleteSignUpPageRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/sign_up_page_addons', name: 'list_sign_up_page_addons', methods: ['GET'])]
    public function listAddons(ListSignUpPageAddonsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/sign_up_page_addons', name: 'create_sign_up_page_addon', methods: ['POST'])]
    public function createAddon(CreateSignUpPageAddonRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/sign_up_page_addons/{model_id}', name: 'retrieve_sign_up_page_addon', methods: ['GET'])]
    public function retrieveAddon(RetrieveSignUpPageAddonRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/sign_up_page_addons/{model_id}', name: 'edit_sign_up_page_addon', methods: ['PATCH'])]
    public function editAddon(EditSignUpPageAddonRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/sign_up_page_addons/{model_id}', name: 'delete_sign_up_page_addon', methods: ['DELETE'])]
    public function deleteAddon(DeleteSignUpPageAddonRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
