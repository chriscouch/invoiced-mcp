<?php

namespace App\Tests\CashApplication\Libs;

use App\CashApplication\EmailVariables\PaymentEmailVariables;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\Core\I18n\TranslatorFacade;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Sending\Email\Libs\DocumentEmailTemplateFactory;
use App\Tests\AppTestCase;

class PaymentEmailVariablesTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        TranslatorFacade::get()->setLocale(self::$company->getLocale());
    }

    private function getPayment(): Payment
    {
        $payment = new Payment();
        $payment->tenant_id = (int) self::$company->id();
        $payment->setCustomer(self::$customer);
        $payment->date = (int) mktime(0, 0, 0, 6, 12, 2014);
        $payment->amount = 105;
        $payment->method = PaymentMethod::CHECK;
        $payment->currency = 'eur';
        $payment->notes = 'test';
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => self::$invoice->id(),
            ],
        ];

        return $payment;
    }

    public function testGenerate(): void
    {
        $payment = $this->getPayment();
        $generator = new PaymentEmailVariables($payment);

        $expected = [
            'company_name' => 'TEST',
            'company_username' => self::$company->username,
            'company_address' => "Company\nAddress\nAustin, TX 78701",
            'company_email' => 'test@example.com',
            'customer_name' => 'Sherlock',
            'customer_contact_name' => 'Sherlock',
            'customer_number' => 'CUST-00001',
            'customer_address' => "Test\nAddress\nAustin, TX 78701",
            'invoice_number' => 'INV-00001',
            'payment_date' => 'Jun 12, 2014 12:00 am',
            'payment_method' => 'Check',
            'payment_amount' => '€105.00',
            'payment_source' => null,
            'customer' => [
                'metadata' => [],
                'id' => self::$customer->id,
            ],
        ];

        $emailTemplate = (new DocumentEmailTemplateFactory())->get($payment);
        $variables = $generator->generate($emailTemplate);
        $this->assertEquals($expected, $variables);
    }

    public function testAvailableVariables(): void
    {
        $payment = $this->getPayment();
        $generator = new PaymentEmailVariables($payment);
        $template = (new DocumentEmailTemplateFactory())->get($payment);

        $variables = array_keys($generator->generate($template));

        // verify the variables match the email template
        // (minus the mustaches)
        $expected = $template->getAvailableVariables(false);

        $missingFromVariables = array_diff($expected, $variables);
        $this->assertEquals([], $missingFromVariables, 'These variables were missing on the model side for '.$template->id);

        $missingFromTemplate = array_diff($variables, $expected);
        $this->assertEquals([], $missingFromTemplate, 'These variables were missing on the template side for '.$template->id);
    }
}
