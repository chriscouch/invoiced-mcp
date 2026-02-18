<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsPayable\Api\AddVendorCreditAttachmentsApiRoute;
use App\AccountsPayable\Api\CreateVendorCreditRoute;
use App\AccountsPayable\Api\EditVendorCreditRoute;
use App\AccountsPayable\Api\ListVendorCreditApprovalsApiRoute;
use App\AccountsPayable\Api\ListVendorCreditAttachmentsApiRoute;
use App\AccountsPayable\Api\ListVendorCreditRejectionsApiRoute;
use App\AccountsPayable\Api\ListVendorCreditsApiRoute;
use App\AccountsPayable\Api\RetrieveVendorCreditRoute;
use App\AccountsPayable\Api\VendorCreditApproveApiRoute;
use App\AccountsPayable\Api\VendorCreditBalanceApiRoute;
use App\AccountsPayable\Api\VendorCreditRejectApiRoute;
use App\AccountsPayable\Api\VoidVendorCreditApiRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class VendorCreditApiController extends AbstractApiController
{
    #[Route(path: '/vendor_credits', name: 'list_vendor_credits', methods: ['GET'])]
    public function listAll(ListVendorCreditsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_credits/{model_id}', name: 'retrieve_vendor_credit', methods: ['GET'])]
    public function retrieve(RetrieveVendorCreditRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_credits', name: 'create_vendor_credit', methods: ['POST'])]
    public function create(CreateVendorCreditRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_credits/{model_id}', name: 'edit_vendor_credit', methods: ['PATCH'])]
    public function edit(EditVendorCreditRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_credits/{model_id}', name: 'delete_vendor_credit', methods: ['DELETE'])]
    public function delete(VoidVendorCreditApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_credits/{model_id}/balance', name: 'vendor_credit_balance', methods: ['GET'])]
    public function billBalance(VendorCreditBalanceApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_credits/{model_id}/approval', name: 'approve_vendor_credit', methods: ['POST'])]
    public function approve(VendorCreditApproveApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_credits/{model_id}/rejection', name: 'reject_vendor_credit', methods: ['POST'])]
    public function reject(VendorCreditRejectApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_credits/{vendor_credit}/approval', name: 'list_vendor_credit_approvals', methods: ['GET'])]
    public function listApprovals(ListVendorCreditApprovalsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_credits/{vendor_credit}/rejection', name: 'list_vendor_credit_rejections', methods: ['GET'])]
    public function listRejections(ListVendorCreditRejectionsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_credits/{parent_id}/attachments', name: 'list_vendor_credit_attachments', methods: ['GET'])]
    public function listAttachments(ListVendorCreditAttachmentsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/vendor_credits/{vendor_credit_id}/attachment', name: 'add_vendor_credit_attachments', methods: ['POST'])]
    public function addAttachments(AddVendorCreditAttachmentsApiRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
