<?php

namespace App\EntryPoint\Controller\Api;

use App\Core\Multitenant\TenantContext;
use App\Themes\Api\CreateThemeRoute;
use App\Themes\Api\DeleteThemeRoute;
use App\Themes\Api\EditThemeRoute;
use App\Themes\Api\ListThemesRoute;
use App\Themes\Api\RetrieveThemeRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class ThemeApiController extends AbstractApiController
{
    #[Route(path: '/themes', name: 'list_themes', methods: ['GET'])]
    public function listAll(ListThemesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/themes', name: 'create_theme', methods: ['POST'])]
    public function create(CreateThemeRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/themes/{model_id}', name: 'retrieve_theme', methods: ['GET'])]
    public function retrieve(RetrieveThemeRoute $route, TenantContext $tenant, string $model_id): Response
    {
        // add the company ID to the theme ID
        $route->setModelIds([$tenant->get()->id(), $model_id]);

        return $this->runRoute($route);
    }

    #[Route(path: '/themes/{model_id}', name: 'edit_theme', methods: ['PATCH'])]
    public function edit(EditThemeRoute $route, TenantContext $tenant, string $model_id): Response
    {
        // add the company ID to the theme ID
        $route->setModelIds([$tenant->get()->id(), $model_id]);

        return $this->runRoute($route);
    }

    #[Route(path: '/themes/{model_id}', name: 'delete_theme', methods: ['DELETE'])]
    public function delete(DeleteThemeRoute $route, TenantContext $tenant, string $model_id): Response
    {
        // add the company ID to the theme ID
        $route->setModelIds([$tenant->get()->id(), $model_id]);

        return $this->runRoute($route);
    }
}
