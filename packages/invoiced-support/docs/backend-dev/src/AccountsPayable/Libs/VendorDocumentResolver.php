<?php

namespace App\AccountsPayable\Libs;

use App\AccountsPayable\Enums\PayableDocumentStatus;
use App\AccountsPayable\Models\PayableDocument;
use App\Companies\Models\Member;
use App\Core\RestApi\Exception\InvalidRequest;

class VendorDocumentResolver
{
    public function resolve(PayableDocument $vendorDocument, Member $member, PayableDocumentStatus $status, ?string $description): void
    {
        $step = $vendorDocument->approval_workflow_step?->refresh();
        if (!$step || (!$step->members && !$step->roles)) {
            if (!$member->allowed('bills.edit') && empty($vendorDocument->getTasks($member))) {
                throw $this->error();
            }
        } elseif (!$step->isAllowed($member)) {
            throw $this->error();
        }

        $resolution = PayableDocumentStatus::Rejected == $status ? $vendorDocument->createRejection() : $vendorDocument->createApproval();
        $resolution->member = $member;
        $resolution->approval_workflow_step = $step;
        $resolution->note = $description;
        $resolution->saveOrFail();
    }

    private function error(): InvalidRequest
    {
        return new InvalidRequest('Only selected users/roles have permissions to do this', 403);
    }
}
