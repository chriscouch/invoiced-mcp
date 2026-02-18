<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsReceivable\Api\Bundles\CreateBundleRoute;
use App\AccountsReceivable\Api\Bundles\DeleteBundleRoute;
use App\AccountsReceivable\Api\Bundles\EditBundleRoute;
use App\AccountsReceivable\Api\Bundles\ListBundlesRoute;
use App\AccountsReceivable\Api\Bundles\RetrieveBundleRoute;
use App\AccountsReceivable\Models\Bundle;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class BundleApiController extends AbstractApiController
{
    #[Route(path: '/bundles', name: 'list_bundles', methods: ['GET'])]
    public function listAll(ListBundlesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/bundles', name: 'create_bundle', methods: ['POST'])]
    public function create(CreateBundleRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/bundles/{model_id}', name: 'retrieve_bundle', methods: ['GET'])]
    public function retrieve(RetrieveBundleRoute $route, string $model_id): Response
    {
        // look up the current version
        if ($model = Bundle::where('id', $model_id)->oneOrNull()) {
            $route->setModel($model);
        }

        return $this->runRoute($route);
    }

    #[Route(path: '/bundles/{model_id}', name: 'edit_bundle', methods: ['PATCH'])]
    public function edit(EditBundleRoute $route, string $model_id): Response
    {
        // look up the current version
        if ($model = Bundle::where('id', $model_id)->oneOrNull()) {
            $route->setModel($model);
        }

        return $this->runRoute($route);
    }

    #[Route(path: '/bundles/{model_id}', name: 'delete_bundle', methods: ['DELETE'])]
    public function delete(DeleteBundleRoute $route, string $model_id): Response
    {
        // look up the current version
        if ($model = Bundle::where('id', $model_id)->oneOrNull()) {
            $route->setModel($model);
        }

        return $this->runRoute($route);
    }
}
