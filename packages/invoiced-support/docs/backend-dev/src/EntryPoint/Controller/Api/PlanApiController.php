<?php

namespace App\EntryPoint\Controller\Api;

use App\SubscriptionBilling\Api\CreatePlanRoute;
use App\SubscriptionBilling\Api\DeletePlanRoute;
use App\SubscriptionBilling\Api\EditPlanRoute;
use App\SubscriptionBilling\Api\ListPlansRoute;
use App\SubscriptionBilling\Api\RetrievePlanRoute;
use App\SubscriptionBilling\Models\Plan;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class PlanApiController extends AbstractApiController
{
    #[Route(path: '/plans', name: 'list_plans', methods: ['GET'])]
    public function listAll(ListPlansRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/plans', name: 'create_plan', methods: ['POST'])]
    public function create(CreatePlanRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/plans/{model_id}', name: 'retrieve_plan', methods: ['GET'])]
    public function retrieve(RetrievePlanRoute $route, string $model_id): Response
    {
        if ($model = Plan::getLatest($model_id)) {
            $route->setModel($model);
        }

        return $this->runRoute($route);
    }

    #[Route(path: '/plans/{model_id}', name: 'edit_plan', methods: ['PATCH'])]
    public function edit(EditPlanRoute $route, string $model_id): Response
    {
        if ($model = Plan::getLatest($model_id)) {
            $route->setModel($model);
        }

        return $this->runRoute($route);
    }

    #[Route(path: '/plans/{model_id}', name: 'delete_plan', methods: ['DELETE'])]
    public function delete(DeletePlanRoute $route, string $model_id): Response
    {
        if ($model = Plan::getLatest($model_id)) {
            $route->setModel($model);
        }

        return $this->runRoute($route);
    }
}
