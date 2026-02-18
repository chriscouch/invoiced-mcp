<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsPayable\Api\CreateWorkflowApiRoute;
use App\AccountsPayable\Api\DefaultWorkflowsApiRoute;
use App\AccountsPayable\Api\DeleteApprovalWorkflowApiRoute;
use App\AccountsPayable\Api\EditWorkflowApiRoute;
use App\AccountsPayable\Api\EnableWorkflowsApiRoute;
use App\AccountsPayable\Api\ListWorkflowsApiRoute;
use App\AccountsPayable\Api\RetrieveApprovalWorkflowRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class WorkflowApiController extends AbstractApiController
{
    #[Route(path: '/approvals/workflows', name: 'list_approval_workflows', methods: ['GET'])]
    public function listAll(ListWorkflowsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/approvals/workflows/{model_id}', name: 'retrieve_approval_workflow', methods: ['GET'])]
    public function retrieve(RetrieveApprovalWorkflowRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/approvals/workflows', name: 'create_approval_workflow', methods: ['POST'])]
    public function create(CreateWorkflowApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/approvals/workflows/{model_id}', name: 'edit_approval_workflow', methods: ['PATCH'])]
    public function edit(EditWorkflowApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/approvals/workflows/{model_id}', name: 'delete_approval_workflow', methods: ['DELETE'])]
    public function delete(DeleteApprovalWorkflowApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/approvals/workflows/{model_id}/enabled', name: 'enable_approval_workflow', defaults: ['enabled' => true], methods: ['POST'])]
    public function enable(EnableWorkflowsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/approvals/workflows/{model_id}/enabled', name: 'disable_approval_workflow', defaults: ['enabled' => false], methods: ['DELETE'])]
    public function disable(EnableWorkflowsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/approvals/workflows/{model_id}/default', name: 'set_default_approval_workflow', defaults: ['default' => true], methods: ['POST'])]
    public function makeDefault(DefaultWorkflowsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/approvals/workflows/{model_id}/default', name: 'unset_default_approval_workflow', defaults: ['default' => false], methods: ['DELETE'])]
    public function makeNotDefault(DefaultWorkflowsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
