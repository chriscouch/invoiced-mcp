<?php

namespace App\EntryPoint\Controller\Api;

use App\Chasing\Api\CreateLateFeeScheduleRoute;
use App\Chasing\Api\DeleteLateFeeScheduleRoute;
use App\Chasing\Api\EditLateFeeScheduleRoute;
use App\Chasing\Api\ListLateFeeSchedulesRoute;
use App\Chasing\Api\MassAssignLateFeeScheduleRoute;
use App\Chasing\Api\RunLateFeeScheduleRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class LateFeeApiController extends AbstractApiController
{
    #[Route(path: '/late_fee_schedules', name: 'list_late_fee_schedules', methods: ['GET'])]
    public function listAll(ListLateFeeSchedulesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/late_fee_schedules', name: 'create_late_fee_schedules', methods: ['POST'])]
    public function create(CreateLateFeeScheduleRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/late_fee_schedules/{model_id}/customers', name: 'late_fee_schedule_customerss', methods: ['POST'])]
    public function massAssign(MassAssignLateFeeScheduleRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/late_fee_schedules/{model_id}/runs', name: 'run_late_fee_schedule', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function run(RunLateFeeScheduleRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/late_fee_schedules/{model_id}', name: 'update_late_fee_schedules', methods: ['PATCH'])]
    public function update(EditLateFeeScheduleRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/late_fee_schedules/{model_id}', name: 'delete_late_fee_schedules', methods: ['DELETE'])]
    public function delete(DeleteLateFeeScheduleRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
