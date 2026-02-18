<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Enums\PayableDocumentStatus;
use App\Network\Enums\DocumentStatus;

abstract class VendorDocumentRejectApiRoute extends VendorDocumentApiRoute
{
    protected function getDocumentStatus(): DocumentStatus
    {
        return DocumentStatus::Rejected;
    }

    protected function getPayableDocumentStatus(): PayableDocumentStatus
    {
        return PayableDocumentStatus::Rejected;
    }
}
