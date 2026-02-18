<?php

namespace App\Sending\Email\Libs;

use App\Companies\Models\Company;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\EmailTemplateOption;
use RuntimeException;

final class EmailTriggers
{
    private const TRIGGER_TEMPLATE = [
        'new_subscription_invoice' => EmailTemplate::NEW_INVOICE,
        'invoice_paid' => EmailTemplate::PAID_INVOICE,
        'new_charge' => EmailTemplate::PAYMENT_RECEIPT,
        'new_refund' => EmailTemplate::REFUND,
        'autopay_failed' => EmailTemplate::AUTOPAY_FAILED,
        'new_subscription' => EmailTemplate::SUBSCRIPTION_CONFIRMATION,
        'subscription_canceled' => EmailTemplate::SUBSCRIPTION_CANCELED,
    ];

    private const TRIGGER_ENABLED_OPTION = [
        'new_subscription_invoice' => EmailTemplateOption::SEND_ON_SUBSCRIPTION_INVOICE,
        'invoice_paid' => EmailTemplateOption::SEND_ONCE_PAID,
        'new_charge' => EmailTemplateOption::SEND_ON_CHARGE,
        'new_refund' => EmailTemplateOption::SEND_ON_CHARGE,
        'autopay_failed' => EmailTemplateOption::SEND_ON_CHARGE,
        'new_subscription' => EmailTemplateOption::SEND_ON_SUBSCRIBE,
        'subscription_canceled' => EmailTemplateOption::SEND_ON_CANCELLATION,
    ];

    public static function make(Company $company): self
    {
        return new self($company);
    }

    public function __construct(private Company $company)
    {
    }

    /**
     * Checks if an email trigger is enabled.
     */
    public function isEnabled(string $trigger): bool
    {
        if (!isset(self::TRIGGER_TEMPLATE[$trigger])) {
            throw new RuntimeException('Invalid email trigger: '.$trigger);
        }

        return (bool) $this->getEmailTemplate($trigger)
            ->getOption(self::TRIGGER_ENABLED_OPTION[$trigger]);
    }

    private function getEmailTemplate(string $trigger): EmailTemplate
    {
        return EmailTemplate::make($this->company->id, self::TRIGGER_TEMPLATE[$trigger]);
    }
}
