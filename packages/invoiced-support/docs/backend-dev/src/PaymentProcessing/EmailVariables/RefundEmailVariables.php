<?php

namespace App\PaymentProcessing\EmailVariables;

use App\Core\I18n\MoneyFormatter;
use App\PaymentProcessing\Models\Refund;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Models\EmailTemplate;

/**
 * View model for refund email templates.
 */
class RefundEmailVariables implements EmailVariablesInterface
{
    public function __construct(protected Refund $refund)
    {
    }

    public function generate(EmailTemplate $template): array
    {
        $companyVariables = $this->refund->tenant()->getEmailVariables();
        $customerVariables = $this->refund->customer()->getEmailVariables();

        $source = $this->refund->charge->payment_source;

        $paymentAmount = $this->refund->charge->getAmount();
        $amount = $this->refund->getAmount();
        $moneyFormat = $this->refund->tenant()->moneyFormat();
        $moneyFormatter = MoneyFormatter::get();

        $variables = [
            // payment specific variables
            'invoice_number' => null,
            'payment_date' => date('M j, Y h:i a', $this->refund->created_at),
            'payment_amount' => $moneyFormatter->format($paymentAmount, $moneyFormat),
            'payment_method' => $source ? $source->getPaymentMethod()->toString() : null,
            'payment_source' => $source ? $source->toString(true) : null,
            // refund variables
            'refund_amount' => $moneyFormatter->format($amount, $moneyFormat),
            'refund_date' => date('M j, Y h:i a', $this->refund->created_at),
        ];

        return array_replace(
            $companyVariables->generate($template),
            $customerVariables->generate($template),
            $variables
        );
    }

    public function getCurrency(): string
    {
        return $this->refund->currency;
    }
}
