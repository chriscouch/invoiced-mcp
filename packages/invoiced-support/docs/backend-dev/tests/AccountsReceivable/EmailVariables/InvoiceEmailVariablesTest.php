<?php

namespace App\Tests\AccountsReceivable\EmailVariables;

use App\AccountsReceivable\EmailVariables\InvoiceEmailVariables;
use App\AccountsReceivable\Models\Invoice;
use App\Sending\Email\Models\EmailTemplate;
use App\Tests\AppTestCase;

class InvoiceEmailVariablesTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
    }

    protected function getDocument(): Invoice
    {
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);
        $invoice->number = 'INV-00001';
        $invoice->currency = 'eur';
        $invoice->date = (int) gmmktime(0, 0, 0, 6, 12, 2020);
        $invoice->due_date = (int) gmmktime(0, 0, 0, 6, 24, 2020);
        $invoice->payment_terms = 'NET 12';
        $invoice->balance = 91.8;
        $invoice->amount_paid = 10;
        $invoice->total = 101.8;
        $invoice->discounts = [['amount' => 5.1]];
        $invoice->client_id = 'test_client_id';
        $invoice->attempt_count = 0;
        $invoice->notes = 'test';

        return $invoice;
    }

    public function testGenerate(): void
    {
        $invoice = $this->getDocument();
        $generator = new InvoiceEmailVariables($invoice);

        $url = 'http://invoiced.localhost:1234/invoices/'.self::$company->identifier.'/test_client_id';
        $expected = [
            'company_name' => 'TEST',
            'company_username' => self::$company->username,
            'company_address' => "Company\nAddress\nAustin, TX 78701",
            'company_email' => 'test@example.com',
            'customer_name' => 'Sherlock',
            'customer_contact_name' => 'Sherlock',
            'customer_number' => 'CUST-00001',
            'customer_address' => "Test\nAddress\nAustin, TX 78701",
            'url' => $url,
            'invoice_number' => 'INV-00001',
            'invoice_date' => 'Jun 12, 2020',
            'due_date' => 'Jun 24, 2020',
            'payment_terms' => 'NET 12',
            'purchase_order' => null,
            'total' => '€101.80',
            'balance' => '€91.80',
            'discounts' => '€5.10',
            'notes' => 'test',
            'payment_url' => $invoice->payment_url,
            'attempt_count' => 0,
            'next_payment_attempt' => 'None',
            'view_invoice_button' => '<center style="width: 100%; min-width: 532px;" class=""><table class="button radius" align="center" style="border-spacing: 0; border-collapse: collapse; padding: 0; vertical-align: top; text-align: left; width: auto; margin: 0 0 16px 0; Margin: 0 0 16px 0;"><tbody class=""><tr style="padding: 0; vertical-align: top; text-align: left;" class=""><td style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; color: #ffffff; font-family: Helvetica, Arial, sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; text-align: left; font-size: 16px; line-height: 1.3;" class=""><table style="border-spacing: 0; border-collapse: collapse; padding: 0; vertical-align: top; text-align: left; width: 100%;" class=""><tbody class=""><tr style="padding: 0; vertical-align: top; text-align: left;" class=""><td style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; font-family: Helvetica, Arial, sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; font-size: 16px; line-height: 1.3; text-align: left; color: #ffffff; background: #348eda; border-radius: 3px; border: none;" class=""><a href="'.$url.'" style="margin: 0; Margin: 0; text-align: left; line-height: 1.3; font-family: Helvetica, Arial, sans-serif; font-size: 16px; font-weight: bold; color: #ffffff; text-decoration: none; display: inline-block; padding: 8px 16px 8px 16px; border: 0 solid #348eda; border-radius: 3px;" class="">View Invoice<plainTextOnly>: '.$url.'</plainTextOnly></a></td></tr></tbody></table></td></tr></tbody></table></center>',
            'invoice' => [
                'metadata' => [],
            ],
            'customer' => [
                'metadata' => [],
                'id' => self::$customer->id,
            ],
        ];

        $emailTemplate = EmailTemplate::make(self::$company->id, EmailTemplate::NEW_INVOICE);
        $variables = $generator->generate($emailTemplate);
        $this->assertEquals($expected, $variables);
    }

    public function testAvailableVariables(): void
    {
        $invoice = $this->getDocument();
        $generator = new InvoiceEmailVariables($invoice);

        // these templates should all have the same variables
        $templates = [
            EmailTemplate::NEW_INVOICE,
            EmailTemplate::UNPAID_INVOICE,
            EmailTemplate::LATE_PAYMENT_REMINDER,
            EmailTemplate::PAID_INVOICE,
            EmailTemplate::AUTOPAY_FAILED,
        ];

        foreach ($templates as $t) {
            if (EmailTemplate::AUTOPAY_FAILED == $t) {
                $invoice->setEmailVariables(['payment_amount' => 100]);
            }

            $template = EmailTemplate::make(self::$company->id, $t);

            $variables = array_keys($generator->generate($template));

            // verify the variables match the email template
            // (minus the mustaches)
            $expected = $template->getAvailableVariables(false);

            $missingFromVariables = array_diff($expected, $variables);
            $this->assertEquals([], $missingFromVariables, 'These variables were missing on the model side for '.$template->id);

            $missingFromTemplate = array_diff($variables, $expected);
            $this->assertEquals([], $missingFromTemplate, 'These variables were not found on the template side for '.$template->id);
        }
    }
}
