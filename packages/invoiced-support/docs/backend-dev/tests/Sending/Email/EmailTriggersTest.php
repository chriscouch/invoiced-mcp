<?php

namespace App\Tests\Sending\Email;

use App\Sending\Email\Libs\EmailTriggers;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\EmailTemplateOption;
use App\Tests\AppTestCase;

class EmailTriggersTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function get(): EmailTriggers
    {
        return EmailTriggers::make(self::$company);
    }

    private function enable(string $templateId, string $option, bool $enabled = true): void
    {
        $template = new EmailTemplate();
        $template->id = $templateId;
        $template->subject = 'blah subj';
        $template->body = 'blah';
        $template->options = [$option => $enabled];
        $template->saveOrFail();
    }

    public function testNewSubscriptionInvoice(): void
    {
        $triggers = $this->get();
        $this->assertTrue($triggers->isEnabled('new_subscription_invoice'));
        $this->enable(EmailTemplate::NEW_INVOICE, EmailTemplateOption::SEND_ON_SUBSCRIPTION_INVOICE, false);
        $this->assertFalse($triggers->isEnabled('new_subscription_invoice'));
    }

    public function testInvoicePaid(): void
    {
        $triggers = $this->get();
        $this->assertFalse($triggers->isEnabled('invoice_paid'));
        $this->enable(EmailTemplate::PAID_INVOICE, EmailTemplateOption::SEND_ONCE_PAID);
        $this->assertTrue($triggers->isEnabled('invoice_paid'));
    }

    public function testNewCharge(): void
    {
        $triggers = $this->get();
        $this->assertTrue($triggers->isEnabled('new_charge'));
        $this->enable(EmailTemplate::PAYMENT_RECEIPT, EmailTemplateOption::SEND_ON_CHARGE, false);
        $this->assertFalse($triggers->isEnabled('new_charge'));
    }

    public function testNewRefund(): void
    {
        $triggers = $this->get();
        $this->assertTrue($triggers->isEnabled('new_refund'));
        $this->enable(EmailTemplate::REFUND, EmailTemplateOption::SEND_ON_CHARGE, false);
        $this->assertFalse($triggers->isEnabled('new_refund'));
    }

    public function testAutoPayFailed(): void
    {
        $triggers = $this->get();
        $this->assertTrue($triggers->isEnabled('autopay_failed'));
        $this->enable(EmailTemplate::AUTOPAY_FAILED, EmailTemplateOption::SEND_ON_CHARGE, false);
        $this->assertFalse($triggers->isEnabled('autopay_failed'));
    }

    public function testNewSubscription(): void
    {
        $triggers = $this->get();
        $this->assertFalse($triggers->isEnabled('new_subscription'));
        $this->enable(EmailTemplate::SUBSCRIPTION_CONFIRMATION, EmailTemplateOption::SEND_ON_SUBSCRIBE);
        $this->assertTrue($triggers->isEnabled('new_subscription'));
    }

    public function testSubscriptionCanceled(): void
    {
        $triggers = $this->get();
        $this->assertFalse($triggers->isEnabled('subscription_canceled'));
        $this->enable(EmailTemplate::SUBSCRIPTION_CANCELED, EmailTemplateOption::SEND_ON_CANCELLATION);
        $this->assertTrue($triggers->isEnabled('subscription_canceled'));
    }
}
