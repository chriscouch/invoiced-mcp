<?php

namespace App\Tests\PaymentProcessing\Forms;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Companies\Models\Company;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\Enums\ObjectType;
use App\PaymentProcessing\Forms\PaymentInfoFormBuilder;
use App\PaymentProcessing\Models\DisabledPaymentMethod;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\PaymentFormSettings;
use App\Tests\AppTestCase;

class PaymentInfoFormTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::acceptsCreditCards();
        self::hasCustomer();
        self::hasInvoice();
    }

    private function getFormBuilder(?PaymentFormSettings $settings = null): PaymentInfoFormBuilder
    {
        $settings ??= new PaymentFormSettings(
            self::$company,
            false,
            false,
            false,
            false
        );

        return new PaymentInfoFormBuilder($settings);
    }

    public function testMethods(): void
    {
        $builder = $this->getFormBuilder();

        $form = $builder->build();
        $methods = $form->methods;
        $this->assertEquals([PaymentMethod::CREDIT_CARD], array_keys($methods));

        // try with a disabled payment method
        $disabled = new DisabledPaymentMethod();
        $disabled->object_type = ObjectType::Customer->typeName();
        $disabled->object_id = '100';
        $disabled->method = PaymentMethod::CREDIT_CARD;
        $disabled->save();

        $builder->setCustomer(new Customer(['id' => 100]));

        $form = $builder->build();
        $methods = array_keys($form->methods);
        $this->assertEquals([], $methods);
    }

    public function testCustomer(): void
    {
        $builder = $this->getFormBuilder();

        $customer = new Customer();
        $builder->setCustomer($customer);
        $form = $builder->build();
        $this->assertEquals($customer, $form->customer);
    }

    public function testMethod(): void
    {
        $builder = $this->getFormBuilder();

        $method = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);

        $form = $builder->build();
        $this->assertNull($form->method);
        $builder->setMethod($method);

        $form = $builder->build();
        $this->assertEquals($method, $form->method);
    }

    public function testGetOutstandingAutoPayInvoices(): void
    {
        $builder = $this->getFormBuilder();

        $customer = new Customer();
        $customer->name = 'Test';
        $this->assertTrue($customer->save());
        $builder->setCustomer($customer);

        $form = $builder->build();
        $invoices = $form->outstandingAutoPayInvoices;
        $this->assertCount(0, $invoices);

        // create an outstanding invoice
        $invoice = $this->_createBasicAutopayInvoice($customer);
        $this->assertTrue($invoice->save());

        // create a closed invoice
        $invoice2 = $this->_createBasicAutopayInvoice($customer);
        $invoice2->closed = true;
        $this->assertTrue($invoice2->save());

        // create a paid invoice
        $invoice3 = $this->_createBasicAutopayInvoice($customer, null);
        $this->assertTrue($invoice3->save());

        // create another invoice
        $invoice4 = $this->_createBasicAutopayInvoice($customer, [['unit_cost' => 200]]);
        $this->assertTrue($invoice4->save());

        // create draft invoice
        $invoice5 = $this->_createBasicAutopayInvoice($customer);
        $invoice5->draft = true;
        $this->assertTrue($invoice5->save());

        // create past invoice
        $invoice8 = $this->_createBasicAutopayInvoice($customer);
        $this->assertTrue($invoice8->save());

        // create future invoice
        $invoice9 = $this->_createBasicAutopayInvoice($customer);
        $invoice9->next_payment_attempt = strtotime('+1 hour');
        $this->assertTrue($invoice9->save());

        // create future invoice attempted
        $invoice10 = $this->_createBasicAutopayInvoice($customer);
        $invoice10->next_payment_attempt = strtotime('+1 hour');
        $invoice10->attempt_count = 1;
        $this->assertTrue($invoice10->save());

        // create null next payment time invoice
        $invoice11 = $this->_createBasicAutopayInvoice($customer);
        $this->assertTrue($invoice11->save());

        // create a voided invoice
        $invoice12 = $this->_createBasicAutopayInvoice($customer);
        $invoice12->void();

        // simulate null on the invoice
        self::getService('test.database')->update('Invoices', ['next_payment_attempt' => null], ['id' => $invoice11->id()]);
        $form = $builder->build();
        $invoices = $form->outstandingAutoPayInvoices;

        $this->assertCount(5, $invoices);
        $this->assertEquals($invoice->id(), $invoices[0]->id());
        $this->assertEquals($invoice4->id(), $invoices[1]->id());
        $this->assertEquals($invoice8->id(), $invoices[2]->id());
        $this->assertEquals($invoice10->id(), $invoices[3]->id());
        $this->assertEquals($invoice11->id(), $invoices[4]->id());

        /** @var Money $balance */
        $balance = $form->outstandingAutoPayBalance;
        $this->assertEquals(60000, $balance->amount);
        $this->assertEquals('usd', $balance->currency);
    }

    /**
     * Boilerplate for the autopay
     * invoice creation.
     *
     * @param Customer $customer - customer to add invoice to
     * @param array    $units    - list of the units to be put on the invoice
     */
    private function _createBasicAutopayInvoice(Customer $customer, ?array $units = [['unit_cost' => 100]]): Invoice
    {
        $invoice = new Invoice();
        $invoice->autopay = true;
        $invoice->setCustomer($customer);
        if ($units) {
            $invoice->items = $units;
        }
        // setting default payment attempt to the past
        $invoice->next_payment_attempt = strtotime('-1 hour');

        return $invoice;
    }

    public function testAllowAutoPayEnrollment(): void
    {
        $company = new Company();
        $customer = new Customer();
        $settings = new PaymentFormSettings(
            $company,
            false,
            false,
            false,
            false
        );
        $builder = new PaymentInfoFormBuilder($settings);
        $builder->setCustomer($customer);
        $form = $builder->build();
        $this->assertFalse($form->allowAutoPayEnrollment);

        $settings = new PaymentFormSettings(
            $company,
            false,
            false,
            false,
            true
        );
        $builder = new PaymentInfoFormBuilder($settings);
        $builder->setCustomer($customer);
        $form = $builder->build();
        $this->assertTrue($form->allowAutoPayEnrollment);

        $customer->autopay = true;
        $form = $builder->build();
        $this->assertFalse($form->allowAutoPayEnrollment);
    }
}
