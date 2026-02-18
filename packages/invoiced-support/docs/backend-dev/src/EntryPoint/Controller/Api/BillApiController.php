<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsPayable\Api\AddBillAttachmentsApiRoute;
use App\AccountsPayable\Api\BillApproveApiRoute;
use App\AccountsPayable\Api\BillBalanceApiRoute;
use App\AccountsPayable\Api\BillRejectApiRoute;
use App\AccountsPayable\Api\CreateBillRoute;
use App\AccountsPayable\Api\EditBillRoute;
use App\AccountsPayable\Api\ListBillApprovalsApiRoute;
use App\AccountsPayable\Api\ListBillAttachmentsApiRoute;
use App\AccountsPayable\Api\ListBillRejectionsApiRoute;
use App\AccountsPayable\Api\ListBillsApiRoute;
use App\AccountsPayable\Api\RetrieveBillRoute;
use App\AccountsPayable\Api\VoidBillApiRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class BillApiController extends AbstractApiController
{
    #[Route(path: '/bills', name: 'list_bills', methods: ['GET'])]
    public function listAll(ListBillsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/bills/{model_id}', name: 'retrieve_bill', methods: ['GET'])]
    public function retrieve(RetrieveBillRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/bills', name: 'create_bill', methods: ['POST'])]
    public function create(CreateBillRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/bills/{model_id}', name: 'edit_bill', methods: ['PATCH'])]
    public function edit(EditBillRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/bills/{model_id}', name: 'delete_bill', methods: ['DELETE'])]
    public function delete(VoidBillApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/bills/{model_id}/balance', name: 'bill_balance', methods: ['GET'])]
    public function billBalance(BillBalanceApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/bills/{model_id}/approval', name: 'approve_bill', methods: ['POST'])]
    public function approve(BillApproveApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/bills/{model_id}/rejection', name: 'reject_bill', methods: ['POST'])]
    public function reject(BillRejectApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/bills/{bill}/approval', name: 'list_bill_approvals', methods: ['GET'])]
    public function listApprovals(ListBillApprovalsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/bills/{bill}/rejection', name: 'list_bill_rejections', methods: ['GET'])]
    public function listRejections(ListBillRejectionsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/bills/{parent_id}/attachments', name: 'list_bill_attachments', methods: ['GET'])]
    public function listAttachments(ListBillAttachmentsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/bills/{parent_id}/attachment', name: 'add_bill_attachments', methods: ['POST'])]
    public function addAttachments(AddBillAttachmentsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
