<?php

namespace App\AccountsReceivable\ClientView;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\Invoice;
use App\Core\Authentication\Libs\UserContext;
use App\Core\I18n\ValueObjects\Money;
use App\CustomerPortal\Libs\CustomerPortal;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentProcessing\Libs\PaymentFlowManager;
use App\Sending\Email\Interfaces\EmailBodyStorageInterface;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class InvoiceClientViewVariables extends AbstractDocumentClientViewVariables
{
    public function __construct(
        private readonly UserContext $userContext,
        private readonly PaymentFlowManager $paymentFlowManager,
        UrlGeneratorInterface $urlGenerator,
        EmailBodyStorageInterface $storage,
    ) {
        parent::__construct($urlGenerator, $storage);
    }

    /**
     * Makes the view parameters for an invoice to be used
     * in the client view.
     */
    public function make(Invoice $invoice, CustomerPortal $portal, Request $request): array
    {
        $company = $invoice->tenant();
        if ($nextAutoPayAttempt = $invoice->next_payment_attempt) {
            $nextAutoPayAttempt = date($company->date_format, $nextAutoPayAttempt);
        } else {
            $nextAutoPayAttempt = null;
        }

        return array_merge(
            $this->makeForDocument($invoice, $request),
            [
                'dueDate' => $invoice->due_date ? CarbonImmutable::createFromTimestamp($invoice->due_date) : null,
                'paymentTerms' => $invoice->payment_terms,
                'amountPaid' => $invoice->amount_paid,
                'amountCredited' => $invoice->amount_credited,
                'balance' => $invoice->balance,
                'commentsUrl' => $this->makeCommentsUrl($portal, $invoice),
                'paymentUrl' => $this->makePaymentUrl($portal, $invoice),
                'updatePaymentInfoUrl' => $this->makeUpdatePaymentInfoUrl($portal, $invoice),
                'saveUrl' => null, // Currently disabled
                'terms' => $invoice->theme()->terms,
                'paymentPlan' => $this->makePaymentPlan($invoice),
                'nextAutoPayAttempt' => $nextAutoPayAttempt,
                'nextAutoPayAmount' => $this->getNextAutoPayAmount($invoice),
                'paymentSource' => $this->makePaymentSource($portal, $invoice),
            ]
        );
    }

    private function makePaymentPlan(Invoice $invoice): ?array
    {
        $paymentPlan = $invoice->paymentPlan();
        if (!$paymentPlan) {
            return null;
        }

        $installments = [];
        $paymentPlanTotal = new Money($invoice->currency, 0);
        foreach ($paymentPlan->installments as $installment) {
            $installmentAmount = Money::fromDecimal($invoice->currency, $installment->amount);
            $installments[] = [
                'amount' => $installmentAmount->toDecimal(),
                'balance' => $installment->balance,
                'date' => CarbonImmutable::createFromTimestamp($installment->date),
                'pastDue' => $installment->date < time() && $installment->balance,
            ];

            $paymentPlanTotal = $paymentPlanTotal->add($installmentAmount);
        }

        return [
            'status' => $paymentPlan->status,
            'total' => $paymentPlanTotal->toDecimal(),
            'installments' => $installments,
        ];
    }

    private function makeCommentsUrl(CustomerPortal $portal, Invoice $invoice): string
    {
        return $this->generatePortalUrl($portal, 'customer_portal_invoice_send_message', [
           'id' => $invoice->client_id,
        ]);
    }

    public function makeSaveUrl(Invoice $invoice): ?string
    {
        $company = $invoice->tenant();
        $customer = $invoice->customer();
        $user = $this->userContext->get();
        $isMember = $user && $company->isMember($user);
        $saveUrl = null;
        if (!$isMember && !$customer->network_connection && 'person' != $customer->type) {
            $saveUrl = $this->urlGenerator->generate('client_view_save_invoice', [
                'companyId' => $company->identifier,
                'id' => $invoice->client_id,
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $saveUrl;
    }

    private function makePaymentUrl(CustomerPortal $portal, Invoice $invoice): ?string
    {
        // cannot pay invoices that are paid or have pending payments
        if ($invoice->paid || InvoiceStatus::Pending->value == $invoice->status) {
            return null;
        }

        $amount = $this->paymentFlowManager->getBlockingAmount($invoice, Money::fromDecimal($invoice->currency, 0.01));
        if (!$amount->isZero()) {
            return null;
        }

        // When the payment plan needs approval then always direct to a single invoice payment page
        $paymentPlan = $invoice->paymentPlan();
        if (PaymentPlan::STATUS_PENDING_SIGNUP == $paymentPlan?->status) {
            return $this->generatePortalUrl($portal, 'customer_portal_payment_form', [
                'invoices' => [$invoice->client_id],
            ]);
        }

        if ($portal->invoicePaymentToItemSelection()) {
            return $this->generatePortalUrl($portal, 'customer_portal_payment_select_items_form', [
                'Invoice' => [$invoice->number],
            ]);
        }

        return $this->generatePortalUrl($portal, 'customer_portal_payment_form', [
            'invoices' => [$invoice->client_id],
        ]);
    }

    private function makeUpdatePaymentInfoUrl(CustomerPortal $portal, Invoice $invoice): string
    {
        return $this->generatePortalUrl($portal, 'customer_portal_update_payment_info_form', [
            'id' => $invoice->customer()->client_id,
        ]);
    }

    private function makePaymentSource(CustomerPortal $portal, Invoice $invoice): ?array
    {
        $customer = $invoice->customer();
        $paymentSource = $customer->payment_source;
        if (!$paymentSource) {
            return null;
        }

        $verifyUrl = $this->generatePortalUrl($portal, 'customer_portal_verify_bank_account_form', [
            'id' => $customer->client_id,
            'bankAccountId' => $paymentSource->id,
        ]);

        return [
            'name' => $paymentSource->toString(true),
            'needsVerification' => $paymentSource->needsVerification(),
            'verifyUrl' => $verifyUrl,
        ];
    }

    private function getNextAutoPayAmount(Invoice $invoice): float
    {
        // When there is a payment plan the next charge amount is the next
        // unpaid installment.
        if ($paymentPlan = $invoice->paymentPlan()) {
            foreach ($paymentPlan->installments as $installment) {
                if ($installment->balance > 0) {
                    return $installment->balance;
                }
            }
        }

        // Otherwise the next charge amount is the current invoice balance.
        return $invoice->balance;
    }
}
