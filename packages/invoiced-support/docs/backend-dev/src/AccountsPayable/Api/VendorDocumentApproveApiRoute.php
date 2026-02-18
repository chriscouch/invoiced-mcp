<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Enums\PayableDocumentStatus;
use App\AccountsPayable\Models\PayableDocument;
use App\Network\Enums\DocumentStatus;

abstract class VendorDocumentApproveApiRoute extends VendorDocumentApiRoute
{
    protected function shouldPerformTransition(PayableDocument $vendorDocument): bool
    {
        $stepFinished = $vendorDocument->stepFinished();
        if (!$stepFinished) {
            return false;
        }

        // we don't change status if next step exists
        if ($nextStep = $vendorDocument->approval_workflow_step?->getNextStep()) {
            $this->updateDocument($vendorDocument, [
                'approval_workflow_step' => $nextStep,
            ]);

            return false;
        }

        return true;
    }

    protected function getDocumentStatus(): DocumentStatus
    {
        return DocumentStatus::Approved;
    }

    protected function getPayableDocumentStatus(): PayableDocumentStatus
    {
        return PayableDocumentStatus::Approved;
    }
}
