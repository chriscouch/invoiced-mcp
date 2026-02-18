<?php

namespace App\CustomerPortal\Enums;

enum CustomerPortalEvent: int
{
    case Login = 1;
    case ViewInvoice = 2;
    case ViewEstimate = 3;
    case ViewCreditNote = 4;
    case SendMessage = 6;
    case DownloadFile = 7;
    case UpdateContactInfo = 8;
    case AddPaymentMethod = 9;
    case ApproveEstimate = 10;
    case ApprovePaymentPlan = 11;
    case CompleteSignUpPage = 12;
    case PromiseToPay = 13;
    case AutoPayEnrollment = 14;
    case SubmitPayment = 15;
    case CompletePaymentLink = 16;
    case CompleteSignUpPagePaymentMethodDisabled = 17;
    case RefundReversalApplied = 18;
}
