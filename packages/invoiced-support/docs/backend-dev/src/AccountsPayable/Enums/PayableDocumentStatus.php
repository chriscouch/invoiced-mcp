<?php

namespace App\AccountsPayable\Enums;

enum PayableDocumentStatus: int
{
    case PendingApproval = 1;
    case Approved = 2;
    case Rejected = 3;
    case Paid = 4;
    case Voided = 5;
}
