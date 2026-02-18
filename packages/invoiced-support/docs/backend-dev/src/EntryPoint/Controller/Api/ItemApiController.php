<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsReceivable\Api\Items\CreateItemRoute;
use App\AccountsReceivable\Api\Items\DeleteItemRoute;
use App\AccountsReceivable\Api\Items\EditItemRoute;
use App\AccountsReceivable\Api\Items\ListItemsRoute;
use App\AccountsReceivable\Api\Items\RetrieveItemRoute;
use App\AccountsReceivable\Models\Item;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class ItemApiController extends AbstractApiController
{
    #[Route(path: '/catalog_items', name: 'list_catalog_items', methods: ['GET'])]
    #[Route(path: '/items', name: 'list_items', methods: ['GET'])]
    public function listAll(ListItemsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/catalog_items', name: 'create_catalog_item', methods: ['POST'])]
    #[Route(path: '/items', name: 'create_item', methods: ['POST'])]
    public function create(CreateItemRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/catalog_items/{model_id}', name: 'retrieve_catalog_item', methods: ['GET'])]
    #[Route(path: '/items/{model_id}', name: 'retrieve_item', methods: ['GET'])]
    public function retrieve(RetrieveItemRoute $route, string $model_id): Response
    {
        if ($model = Item::getLatest($model_id)) {
            $route->setModel($model);
        }

        return $this->runRoute($route);
    }

    #[Route(path: '/catalog_items/{model_id}', name: 'edit_catalog_item', methods: ['PATCH'])]
    #[Route(path: '/items/{model_id}', name: 'edit_item', methods: ['PATCH'])]
    public function edit(EditItemRoute $route, string $model_id): Response
    {
        if ($model = Item::getLatest($model_id)) {
            $route->setModel($model);
        }

        return $this->runRoute($route);
    }

    #[Route(path: '/catalog_items/{model_id}', name: 'delete_catalog_item', methods: ['DELETE'])]
    #[Route(path: '/items/{model_id}', name: 'delete_item', methods: ['DELETE'])]
    public function delete(DeleteItemRoute $route, string $model_id): Response
    {
        if ($model = Item::getLatest($model_id)) {
            $route->setModel($model);
        }

        return $this->runRoute($route);
    }
}
