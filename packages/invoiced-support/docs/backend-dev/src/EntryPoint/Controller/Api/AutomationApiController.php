<?php

namespace App\EntryPoint\Controller\Api;

use App\Automations\Api\AutomationEnrollRoute;
use App\Automations\Api\AutomationMassEnrollRoute;
use App\Automations\Api\AutomationMassTriggerRoute;
use App\Automations\Api\AutomationMassUnEnrollRoute;
use App\Automations\Api\AutomationUnEnrollRoute;
use App\Automations\Api\CreateAutomationWorkflowRoute;
use App\Automations\Api\CreateAutomationWorkflowVersionRoute;
use App\Automations\Api\DeleteAutomationWorkflowRoute;
use App\Automations\Api\EditAutomationWorkflowRoute;
use App\Automations\Api\EditAutomationWorkflowVersionRoute;
use App\Automations\Api\ListAutomationWorkflowsRoute;
use App\Automations\Api\RetrieveAutomationWorkflowRoute;
use App\Automations\Api\RetrieveAutomationWorkflowVersionRoute;
use App\Automations\Api\AutomationRunDetailsRoute;
use App\Automations\Api\TriggerAutomationRoute;
use App\Automations\Api\AutomationRunRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class AutomationApiController extends AbstractApiController
{
    #[Route(path: '/automation_workflows', name: 'list_automation_workflows', methods: ['GET'])]
    public function listAll(ListAutomationWorkflowsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/automation_workflows', name: 'create_automation_workflow', methods: ['POST'])]
    public function create(CreateAutomationWorkflowRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/automation_workflows/{model_id}', name: 'retrieve_automation_workflow', methods: ['GET'])]
    public function retrieve(RetrieveAutomationWorkflowRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/automation_workflows/{model_id}', name: 'edit_automation_workflow', methods: ['PATCH'])]
    public function edit(EditAutomationWorkflowRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/automation_workflows/{model_id}', name: 'delete_automation_workflow', methods: ['DELETE'])]
    public function delete(DeleteAutomationWorkflowRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/automation_workflows/{workflow_id}/versions', name: 'create_automation_workflow_version', methods: ['POST'])]
    public function createVersion(CreateAutomationWorkflowVersionRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/automation_workflows/{workflow_id}/versions/{model_id}', name: 'retrieve_automation_workflow_version', methods: ['GET'])]
    public function retrieveVersion(RetrieveAutomationWorkflowVersionRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/automation_workflows/{workflow_id}/versions/{model_id}', name: 'edit_automation_workflow_version', methods: ['PATCH'])]
    public function editVersion(EditAutomationWorkflowVersionRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/automation_workflows/manual_trigger', name: 'trigger_automation_workflow', methods: ['POST'])]
    public function manualTrigger(TriggerAutomationRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/automation_workflows/enrollment', name: 'automation_enroll', methods: ['POST'])]
    public function enroll(AutomationEnrollRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/automation_workflows/enrollment/{model_id}', name: 'automation_un_enroll', methods: ['DELETE'])]
    public function unEnroll(AutomationUnEnrollRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/automation_workflows/enrollments/{model_id}', name: 'automation_mass_enroll', methods: ['POST'])]
    public function massEnroll(AutomationMassEnrollRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/automation_workflows/enrollments/{model_id}', name: 'automation_mass_un_enroll', methods: ['DELETE'])]
    public function massUnEnroll(AutomationMassUnEnrollRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/automation_workflow_runs', name: 'automation_runs', methods: ['GET'])]
    public function runs(AutomationRunRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/automation_workflows/manual_trigger/{model_id}', name: 'automation_mass_manual_trigger', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function massManualTrigger(AutomationMassTriggerRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/automation_workflow_runs/{model_id}', name: 'automation_run_details', methods: ['GET'])]
    public function details(AutomationRunDetailsRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
