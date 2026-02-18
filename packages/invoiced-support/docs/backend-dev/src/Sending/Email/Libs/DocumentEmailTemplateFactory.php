<?php

namespace App\Sending\Email\Libs;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Payment;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentProcessing\Models\Refund;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Interfaces\SendableDocumentInterface;
use App\Sending\Email\Models\EmailTemplate;
use App\Statements\Libs\AbstractStatement;
use App\Statements\Libs\BalanceForwardStatement;
use App\Statements\Libs\OpenItemStatement;

class DocumentEmailTemplateFactory
{
    private const HANDLERS = [
        CreditNote::class => 'creditNote',
        Estimate::class => 'estimate',
        Invoice::class => 'invoice',
        Payment::class => 'payment',
        Refund::class => 'refund',
        BalanceForwardStatement::class => 'statement',
        OpenItemStatement::class => 'statement',
    ];

    /**
     * @throws SendEmailException if the document is not recognized
     */
    public function get(SendableDocumentInterface $sendableDocument): EmailTemplate
    {
        $class = $sendableDocument::class;
        if (!isset(self::HANDLERS[$class])) {
            throw new SendEmailException('Unable to determine email template');
        }

        $handler = self::HANDLERS[$class];

        return $this->$handler($sendableDocument);
    }

    private function creditNote(CreditNote $creditNote): EmailTemplate
    {
        return EmailTemplate::make($creditNote->tenant_id, EmailTemplate::CREDIT_NOTE);
    }

    private function estimate(Estimate $estimate): EmailTemplate
    {
        return EmailTemplate::make($estimate->tenant_id, EmailTemplate::ESTIMATE);
    }

    private function invoice(Invoice $invoice): EmailTemplate
    {
        if ($invoice->paid) {
            return EmailTemplate::make($invoice->tenant_id, EmailTemplate::PAID_INVOICE);
        }

        $paymentPlan = $invoice->paymentPlan();
        if ($paymentPlan && PaymentPlan::STATUS_PENDING_SIGNUP == $paymentPlan->status) {
            return EmailTemplate::make($invoice->tenant_id, EmailTemplate::PAYMENT_PLAN);
        }

        if (InvoiceStatus::PastDue->value == $invoice->status) {
            return EmailTemplate::make($invoice->tenant_id, EmailTemplate::LATE_PAYMENT_REMINDER);
        }

        if (!$invoice->sent) {
            return EmailTemplate::make($invoice->tenant_id, EmailTemplate::NEW_INVOICE);
        }

        return EmailTemplate::make($invoice->tenant_id, EmailTemplate::UNPAID_INVOICE);
    }

    private function payment(Payment $payment): EmailTemplate
    {
        return EmailTemplate::make($payment->tenant_id, EmailTemplate::PAYMENT_RECEIPT);
    }

    private function refund(Refund $refund): EmailTemplate
    {
        return EmailTemplate::make($refund->tenant_id, EmailTemplate::REFUND);
    }

    private function statement(AbstractStatement $statement): EmailTemplate
    {
        return EmailTemplate::make($statement->getSendCompany()->id, EmailTemplate::STATEMENT);
    }
}
