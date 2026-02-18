<?php

namespace App\EntryPoint\Controller\Api;

use App\CustomerPortal\Api\CreateCspTrustedSiteRoute;
use App\CustomerPortal\Api\DeleteCspTrustedSiteRoute;
use App\CustomerPortal\Api\EditCspTrustedSiteRoute;
use App\CustomerPortal\Api\ListCspTrustedSitesRoute;
use App\CustomerPortal\Api\RetrieveCspTrustedSiteRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class CspTrustedSiteApiController extends AbstractApiController
{
    #[Route(path: '/csp_trusted_sites', name: 'list_csp_trusted_sites', methods: ['GET'])]
    public function listAll(ListCspTrustedSitesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/csp_trusted_sites', name: 'create_csp_trusted_site', methods: ['POST'])]
    public function create(CreateCspTrustedSiteRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/csp_trusted_sites/{model_id}', name: 'retrieve_csp_trusted_site', methods: ['GET'])]
    public function retrieve(RetrieveCspTrustedSiteRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/csp_trusted_sites/{model_id}', name: 'edit_csp_trusted_site', methods: ['PATCH'])]
    public function edit(EditCspTrustedSiteRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/csp_trusted_sites/{model_id}', name: 'delete_csp_trusted_site', methods: ['DELETE'])]
    public function delete(DeleteCspTrustedSiteRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
