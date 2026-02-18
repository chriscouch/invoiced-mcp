<?php

namespace App\Tests\Sending\Email\Models;

use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\EmailTemplateOption;
use App\Tests\AppTestCase;

class EmailTemplateTest extends AppTestCase
{
    private static EmailTemplate $template;
    private static EmailTemplate $template2;
    private static EmailTemplate $template3;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testSubjectInvoices(): void
    {
        $template = EmailTemplate::make(self::$company->id, EmailTemplate::NEW_INVOICE);
        $this->assertEquals('Invoice from {{company_name}}: {{invoice_number}}', $template->subject);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::UNPAID_INVOICE);
        $this->assertEquals('Invoice from {{company_name}}: {{invoice_number}}', $template->subject);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::LATE_PAYMENT_REMINDER);
        $this->assertEquals('Past Due - Invoice from {{company_name}}: {{invoice_number}}', $template->subject);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::PAID_INVOICE);
        $this->assertEquals('Thank You - Invoice from {{company_name}}: {{invoice_number}}', $template->subject);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::PAYMENT_PLAN);
        $this->assertEquals('Action Required - Payment plan from {{company_name}}: {{invoice_number}}', $template->subject);
    }

    public function testSubjectEstimates(): void
    {
        $template = EmailTemplate::make(self::$company->id, EmailTemplate::ESTIMATE);
        $this->assertEquals('Estimate from {{company_name}}: {{estimate_number}}', $template->subject);
    }

    public function testSubjectPayments(): void
    {
        $template = EmailTemplate::make(self::$company->id, EmailTemplate::PAYMENT_RECEIPT);
        $this->assertEquals('Receipt for your payment to {{company_name}}', $template->subject);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::REFUND);
        $this->assertEquals('Refund from {{company_name}}', $template->subject);
    }

    public function testSubjectStatements(): void
    {
        $template = EmailTemplate::make(self::$company->id, EmailTemplate::STATEMENT);
        $this->assertEquals('Account statement from {{company_name}}', $template->subject);
    }

    public function testSubjectSubscriptions(): void
    {
        $template = EmailTemplate::make(self::$company->id, EmailTemplate::SUBSCRIPTION_CONFIRMATION);
        $this->assertEquals('You are now subscribed to {{name}} from {{company_name}}', $template->subject);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::SUBSCRIPTION_CANCELED);
        $this->assertEquals('Your subscription to {{name}} has been canceled', $template->subject);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::AUTOPAY_FAILED);
        $this->assertEquals('Your recent payment to {{company_name}} failed', $template->subject);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::SUBSCRIPTION_BILLED_SOON);
        $this->assertEquals('You are about to be charged for a subscription', $template->subject);
    }

    public function testSubjectEmpty(): void
    {
        $template = EmailTemplate::make(self::$company->id, 'blah');
        $this->assertEquals('', $template->subject);
    }

    public function testGetOption(): void
    {
        $template = EmailTemplate::make(self::$company->id, EmailTemplate::AUTOPAY_FAILED);
        $this->assertNull($template->getOption('does_not_exist'));

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::NEW_INVOICE);
        $this->assertNull($template->getOption('does_not_exist'));
        $this->assertTrue($template->getOption(EmailTemplateOption::SEND_ON_SUBSCRIPTION_INVOICE));

        $template->options = [EmailTemplateOption::SEND_ON_SUBSCRIPTION_INVOICE => false];
        $this->assertFalse($template->getOption(EmailTemplateOption::SEND_ON_SUBSCRIPTION_INVOICE));

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::SUBSCRIPTION_CONFIRMATION);
        $this->assertFalse($template->getOption(EmailTemplateOption::SEND_ON_SUBSCRIBE));

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::SUBSCRIPTION_CANCELED);
        $this->assertFalse($template->getOption(EmailTemplateOption::SEND_ON_CANCELLATION));

        $template = EmailTemplate::make(self::$company->id, 'test');
        $template->type = EmailTemplate::TYPE_INVOICE;
        $this->assertEquals('View Invoice', $template->getOption(EmailTemplateOption::BUTTON_TEXT));
        $this->assertTrue($template->getOption(EmailTemplateOption::ATTACH_PDF));
    }

    public function testBodyMustache(): void
    {
        $assetsDir = self::getParameter('kernel.project_dir').'/templates/emailContent';

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::UNPAID_INVOICE);
        $template->template_engine = 'mustache';
        $template->body = 'blah';
        $this->assertEquals('blah', $template->body);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::NEW_INVOICE);
        $template->template_engine = 'mustache';
        $template->body = '';
        $this->assertEquals(file_get_contents($assetsDir.'/new_invoice_email.mustache'), $template->body);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::UNPAID_INVOICE);
        $template->template_engine = 'mustache';
        $template->body = '';
        $this->assertEquals(file_get_contents($assetsDir.'/unpaid_invoice_email.mustache'), $template->body);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::LATE_PAYMENT_REMINDER);
        $template->template_engine = 'mustache';
        $this->assertEquals(file_get_contents($assetsDir.'/late_payment_reminder_email.mustache'), $template->body);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::PAID_INVOICE);
        $template->template_engine = 'mustache';
        $this->assertEquals(file_get_contents($assetsDir.'/paid_invoice_email.mustache'), $template->body);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::PAYMENT_PLAN);
        $template->template_engine = 'mustache';
        $this->assertEquals(file_get_contents($assetsDir.'/payment_plan_onboard_email.mustache'), $template->body);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::ESTIMATE);
        $template->template_engine = 'mustache';
        $this->assertEquals(file_get_contents($assetsDir.'/estimate_email.mustache'), $template->body);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::PAYMENT_RECEIPT);
        $template->template_engine = 'mustache';
        $this->assertEquals(file_get_contents($assetsDir.'/payment_receipt_email.mustache'), $template->body);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::REFUND);
        $template->template_engine = 'mustache';
        $this->assertEquals(file_get_contents($assetsDir.'/refund_email.mustache'), $template->body);
    }

    public function testGetBodyWithButtonMustache(): void
    {
        $assetsDir = self::getParameter('kernel.project_dir').'/templates/emailContent';

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::UNPAID_INVOICE);
        $template->template_engine = 'mustache';
        $template->body = 'blah';
        $this->assertEquals("blah\n\n{{{view_invoice_button}}}", $template->getBodyWithButton());

        $template->body = '{{{view_invoice_button}}}blah';
        $this->assertEquals('{{{view_invoice_button}}}blah', $template->getBodyWithButton());

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::NEW_INVOICE);
        $template->template_engine = 'mustache';
        $template->body = '';
        $this->assertEquals(file_get_contents($assetsDir.'/new_invoice_email.mustache')."\n\n{{{view_invoice_button}}}", $template->getBodyWithButton());

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::UNPAID_INVOICE);
        $template->template_engine = 'mustache';
        $template->body = '';
        $this->assertEquals(file_get_contents($assetsDir.'/unpaid_invoice_email.mustache')."\n\n{{{view_invoice_button}}}", $template->getBodyWithButton());

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::LATE_PAYMENT_REMINDER);
        $template->template_engine = 'mustache';
        $this->assertEquals(file_get_contents($assetsDir.'/late_payment_reminder_email.mustache')."\n\n{{{view_invoice_button}}}", $template->getBodyWithButton());

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::PAYMENT_PLAN);
        $template->template_engine = 'mustache';
        $this->assertEquals(file_get_contents($assetsDir.'/payment_plan_onboard_email.mustache')."\n\n{{{view_invoice_button}}}", $template->getBodyWithButton());

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::PAID_INVOICE);
        $template->template_engine = 'mustache';
        $this->assertEquals(file_get_contents($assetsDir.'/paid_invoice_email.mustache'), $template->getBodyWithButton());

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::ESTIMATE);
        $template->template_engine = 'mustache';
        $this->assertEquals(file_get_contents($assetsDir.'/estimate_email.mustache')."\n\n{{{view_estimate_button}}}", $template->getBodyWithButton());

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::PAYMENT_RECEIPT);
        $template->template_engine = 'mustache';
        $this->assertEquals(file_get_contents($assetsDir.'/payment_receipt_email.mustache'), $template->getBodyWithButton());

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::REFUND);
        $template->template_engine = 'mustache';
        $this->assertEquals(file_get_contents($assetsDir.'/refund_email.mustache'), $template->getBodyWithButton());

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::SUBSCRIPTION_CONFIRMATION);
        $template->template_engine = 'mustache';
        $this->assertEquals(file_get_contents($assetsDir.'/subscription_confirmation_email.mustache')."\n\n{{{manage_subscription_button}}}", $template->getBodyWithButton());

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::SUBSCRIPTION_BILLED_SOON);
        $template->template_engine = 'mustache';
        $this->assertEquals(file_get_contents($assetsDir.'/subscription_renews_soon_email.mustache')."\n\n{{{manage_subscription_button}}}", $template->getBodyWithButton());
    }

    public function testBodyTwig(): void
    {
        $assetsDir = self::getParameter('kernel.project_dir').'/templates/emailContent';

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::UNPAID_INVOICE);
        $template->template_engine = 'twig';
        $template->body = 'blah';
        $this->assertEquals('blah', $template->body);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::NEW_INVOICE);
        $template->template_engine = 'twig';
        $template->body = '';
        $this->assertEquals(file_get_contents($assetsDir.'/new_invoice_email.twig'), $template->body);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::UNPAID_INVOICE);
        $template->template_engine = 'twig';
        $template->body = '';
        $this->assertEquals(file_get_contents($assetsDir.'/unpaid_invoice_email.twig'), $template->body);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::LATE_PAYMENT_REMINDER);
        $template->template_engine = 'twig';
        $this->assertEquals(file_get_contents($assetsDir.'/late_payment_reminder_email.twig'), $template->body);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::PAID_INVOICE);
        $template->template_engine = 'twig';
        $this->assertEquals(file_get_contents($assetsDir.'/paid_invoice_email.twig'), $template->body);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::PAYMENT_PLAN);
        $template->template_engine = 'twig';
        $this->assertEquals(file_get_contents($assetsDir.'/payment_plan_onboard_email.twig'), $template->body);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::ESTIMATE);
        $template->template_engine = 'twig';
        $this->assertEquals(file_get_contents($assetsDir.'/estimate_email.twig'), $template->body);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::PAYMENT_RECEIPT);
        $template->template_engine = 'twig';
        $this->assertEquals(file_get_contents($assetsDir.'/payment_receipt_email.twig'), $template->body);

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::REFUND);
        $template->template_engine = 'twig';
        $this->assertEquals(file_get_contents($assetsDir.'/refund_email.twig'), $template->body);
    }

    public function testGetBodyWithButtonTwig(): void
    {
        $assetsDir = self::getParameter('kernel.project_dir').'/templates/emailContent';

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::UNPAID_INVOICE);
        $template->template_engine = 'twig';
        $template->body = 'blah';
        $this->assertEquals("blah\n\n{{view_invoice_button|raw}}", $template->getBodyWithButton());

        $template->body = '{{view_invoice_button|raw}}blah';
        $this->assertEquals('{{view_invoice_button|raw}}blah', $template->getBodyWithButton());

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::NEW_INVOICE);
        $template->template_engine = 'twig';
        $template->body = '';
        $this->assertEquals(file_get_contents($assetsDir.'/new_invoice_email.twig')."\n\n{{view_invoice_button|raw}}", $template->getBodyWithButton());

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::UNPAID_INVOICE);
        $template->template_engine = 'twig';
        $template->body = '';
        $this->assertEquals(file_get_contents($assetsDir.'/unpaid_invoice_email.twig')."\n\n{{view_invoice_button|raw}}", $template->getBodyWithButton());

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::LATE_PAYMENT_REMINDER);
        $template->template_engine = 'twig';
        $this->assertEquals(file_get_contents($assetsDir.'/late_payment_reminder_email.twig')."\n\n{{view_invoice_button|raw}}", $template->getBodyWithButton());

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::PAYMENT_PLAN);
        $template->template_engine = 'twig';
        $this->assertEquals(file_get_contents($assetsDir.'/payment_plan_onboard_email.twig')."\n\n{{view_invoice_button|raw}}", $template->getBodyWithButton());

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::PAID_INVOICE);
        $template->template_engine = 'twig';
        $this->assertEquals(file_get_contents($assetsDir.'/paid_invoice_email.twig'), $template->getBodyWithButton());

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::ESTIMATE);
        $template->template_engine = 'twig';
        $this->assertEquals(file_get_contents($assetsDir.'/estimate_email.twig')."\n\n{{view_estimate_button|raw}}", $template->getBodyWithButton());

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::PAYMENT_RECEIPT);
        $template->template_engine = 'twig';
        $this->assertEquals(file_get_contents($assetsDir.'/payment_receipt_email.twig'), $template->getBodyWithButton());

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::REFUND);
        $template->template_engine = 'twig';
        $this->assertEquals(file_get_contents($assetsDir.'/refund_email.twig'), $template->getBodyWithButton());

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::SUBSCRIPTION_CONFIRMATION);
        $template->template_engine = 'twig';
        $this->assertEquals(file_get_contents($assetsDir.'/subscription_confirmation_email.twig')."\n\n{{manage_subscription_button|raw}}", $template->getBodyWithButton());

        $template = EmailTemplate::make(self::$company->id, EmailTemplate::SUBSCRIPTION_BILLED_SOON);
        $template->template_engine = 'twig';
        $this->assertEquals(file_get_contents($assetsDir.'/subscription_renews_soon_email.twig')."\n\n{{manage_subscription_button|raw}}", $template->getBodyWithButton());
    }

    public function testCreate(): void
    {
        self::$template = new EmailTemplate();
        $this->assertTrue(self::$template->create([
            'id' => EmailTemplate::UNPAID_INVOICE,
            'subject' => 'test',
            'body' => 'blah',
            'options' => [
                'blah' => true,
            ],
        ]));

        $this->assertEquals(self::$company->id(), self::$template->tenant_id);

        self::$template2 = new EmailTemplate();
        self::$template2->id = EmailTemplate::NEW_INVOICE;
        self::$template2->subject = 'blah subj';
        self::$template2->body = 'blah';
        $this->assertTrue(self::$template2->save());

        self::$template3 = new EmailTemplate();
        self::$template3->type = EmailTemplate::TYPE_CHASING;
        self::$template3->name = 'Chasing Notice';
        self::$template3->subject = 'chasing subject';
        self::$template3->body = 'pay up';
        $this->assertTrue(self::$template3->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => EmailTemplate::NEW_INVOICE,
            'name' => 'New Invoice',
            'type' => EmailTemplate::TYPE_INVOICE,
            'language' => null,
            'subject' => 'blah subj',
            'body' => 'blah',
            'template_engine' => 'twig',
            'options' => [
                EmailTemplateOption::SEND_ON_SUBSCRIPTION_INVOICE => true,
                EmailTemplateOption::BUTTON_TEXT => 'View Invoice',
                EmailTemplateOption::SEND_REMINDER_DAYS => 0,
                EmailTemplateOption::ATTACH_PDF => true,
                EmailTemplateOption::ATTACH_SECONDARY_FILES => false,
                EmailTemplateOption::SEND_ON_ISSUE => false,
            ],
            'created_at' => self::$template2->created_at,
            'updated_at' => self::$template2->updated_at,
        ];

        $this->assertEquals($expected, self::$template2->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        $options = self::$template->options;
        unset($options['blah']);
        self::$template->options = $options;
        $this->assertTrue(self::$template->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$template->delete());
        $n = EmailTemplateOption::where('tenant_id', self::$company->id())
            ->where('template', EmailTemplate::UNPAID_INVOICE)
            ->count();
        $this->assertEquals(0, $n);
    }
}
