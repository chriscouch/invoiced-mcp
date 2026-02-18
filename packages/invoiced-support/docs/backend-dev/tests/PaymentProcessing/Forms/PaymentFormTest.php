<?php

namespace App\Tests\PaymentProcessing\Forms;

use App\AccountsReceivable\Libs\CustomerHierarchy;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\Enums\ObjectType;
use App\CustomerPortal\Libs\CustomerPortal;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\PaymentProcessing\Enums\PaymentAmountOption;
use App\PaymentProcessing\Exceptions\FormException;
use App\PaymentProcessing\Forms\PaymentFormBuilder;
use App\PaymentProcessing\Gateways\MockGateway;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\DisabledPaymentMethod;
use App\PaymentProcessing\Models\PaymentInstruction;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\PaymentForm;
use App\PaymentProcessing\ValueObjects\PaymentFormItem;
use App\PaymentProcessing\ValueObjects\PaymentFormSettings;
use App\Tests\AppTestCase;
use Mockery;

class PaymentFormTest extends AppTestCase
{
    private static PaymentMethod $paymentMethod;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::acceptsCreditCards();
        self::hasCustomer();
        self::hasInvoice();
        self::$paymentMethod = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
        self::$company->accounts_receivable_settings->auto_apply_credits = false;
        self::$company->accounts_receivable_settings->saveOrFail();
    }

    private function getFormBuilder(?Customer $customer = null, ?PaymentFormSettings $settings = null): PaymentFormBuilder
    {
        if ($settings) {
            self::$company->customer_portal_settings->allow_partial_payments = $settings->allowPartialPayments;
            self::$company->customer_portal_settings->allow_invoice_payment_selector = $settings->allowApplyingCredits;
            self::$company->customer_portal_settings->allow_advance_payments = $settings->allowAdvancePayments;
            self::$company->customer_portal_settings->allow_autopay_enrollment = $settings->allowAutoPayEnrollment;
        }
        $portal = new CustomerPortal(self::$company, new CustomerHierarchy(self::getService('test.database')));
        $portal->setSignedInCustomer($customer ?? self::$customer);

        return new PaymentFormBuilder($portal);
    }

    public function testBuildNoCustomer(): void
    {
        $this->expectException(FormException::class);
        $builder = $this->getFormBuilder();
        $builder->build();
    }

    public function testBuildNoItems(): void
    {
        $this->expectException(FormException::class);
        $builder = $this->getFormBuilder();
        $builder->build();
    }

    public function testGetPaymentDescriptionManyInvoices(): void
    {
        $customer = new Customer(['id' => 100]);
        $builder = $this->getFormBuilder($customer);

        for ($i = 1; $i <= 3; ++$i) {
            $invoice = new Invoice(['id' => -$i]);
            $invoice->tenant_id = (int) self::$company->id();
            $invoice->setCustomer($customer);
            $invoice->currency = 'usd';
            $invoice->number = "INV-$i";
            $builder->addInvoice($invoice);
        }

        $form = $builder->build();
        $this->assertEquals('INV-1, INV-2, INV-3', $form->getPaymentDescription(self::getService('translator')));
    }

    public function testGetPaymentDescriptionOneInvoice(): void
    {
        $customer = new Customer(['id' => 100]);
        $builder = $this->getFormBuilder($customer);
        $invoice = new Invoice(['id' => -1]);
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer($customer);
        $invoice->currency = 'usd';
        $invoice->number = 'INV-0001';
        $builder->addInvoice($invoice);
        $form = $builder->build();
        $this->assertEquals('INV-0001', $form->getPaymentDescription(self::getService('translator')));
    }

    public function testGetPaymentDescriptionZeroInvoices(): void
    {
        $form = new PaymentForm(
            company: self::$company,
            customer: self::$customer,
            totalAmount: Money::zero('usd'),
        );
        $this->assertEquals('Account Balance', $form->getPaymentDescription(self::getService('translator')));
    }

    public function testGetPaymentDescriptionCreditNotes(): void
    {
        $builder = $this->getFormBuilder();

        $builder->addInvoice(self::$invoice);

        $creditNote = new CreditNote([
            'tenant_id' => self::$company->id(),
            'currency' => 'usd',
            'number' => 'CN-00001',
        ]);
        $creditNote->setCustomer(self::$customer);
        $builder->addCreditNote($creditNote);

        $form = $builder->build();
        $this->assertEquals('INV-00001, CN-00001', $form->getPaymentDescription(self::getService('translator')));

        $creditNote2 = clone $creditNote;
        $creditNote2->number = 'CN-00002';
        $builder->addCreditNote($creditNote2);
        $form = $builder->build();
        $this->assertEquals('INV-00001, CN-00001, CN-00002', $form->getPaymentDescription(self::getService('translator')));
    }

    public function testGetPaymentDescriptionOneEstimate(): void
    {
        $builder = $this->getFormBuilder();
        $estimate = new Estimate([
            'tenant_id' => self::$company->id(),
            'currency' => 'usd',
            'deposit' => 10,
            'number' => 'EST-00001',
        ]);
        $estimate->setCustomer(self::$customer);
        $builder->addEstimate($estimate);
        $form = $builder->build();
        $this->assertEquals('EST-00001', $form->getPaymentDescription(self::getService('translator')));
    }

    public function testMethodsWithCheck(): void
    {
        $customer = new Customer(['id' => -10]);
        $builder = $this->getFormBuilder($customer);
        $invoice = new Invoice();
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->currency = 'usd';
        $invoice->setCustomer($customer);
        $builder->addInvoice($invoice);

        self::$paymentMethod->enabled = false;
        self::$paymentMethod->saveOrFail();
        self::acceptsChecks();
        $form = $builder->build();
        $methods = array_keys($form->methods);
        $this->assertEquals([PaymentMethod::CHECK], $methods);

        $override = new PaymentInstruction();
        $override->meta = 'test';
        $override->enabled = true;
        $override->payment_method_id = PaymentMethod::CHECK;
        $override->country = 'UK';
        $override->saveOrFail();

        $customer = new Customer(['id' => -10]);
        $builder = $this->getFormBuilder($customer);
        $invoice = new Invoice();
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->currency = 'usd';
        $invoice->setCustomer($customer);
        $builder->addInvoice($invoice);
        $form = $builder->build();
        $methods = $form->methods;
        $method = $methods[PaymentMethod::CHECK];
        $this->assertEquals('Payment instructions...', $method->meta);
        $this->assertTrue($method->enabled);

        $builder = $this->getFormBuilder(new Customer(['id' => -10]));
        $invoice = new Invoice();
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->currency = 'usd';
        $invoice->setCustomer(new Customer(['id' => -10]));
        $builder->addInvoice($invoice);
        $form = $builder->build();
        $methods = $form->methods;
        $method = $methods[PaymentMethod::CHECK];
        $this->assertCount(1, $methods);
        $this->assertEquals('Payment instructions...', $method->meta);
        $this->assertTrue($method->enabled);

        $customer = new Customer(['id' => -10]);
        $customer->country = 'UK';
        $builder = $this->getFormBuilder($customer);
        $invoice = new Invoice();
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->currency = 'usd';
        $invoice->setCustomer($customer);
        $builder->addInvoice($invoice);
        $form = $builder->build();
        $methods = $form->methods;
        $method = $methods[PaymentMethod::CHECK];
        $this->assertCount(1, $methods);
        $this->assertEquals('test', $method->meta);
        $this->assertTrue($method->enabled);

        $customer = new Customer(['id' => -10]);
        $customer->country = 'UK';
        $builder = $this->getFormBuilder($customer);
        $override->enabled = false;
        $override->saveOrFail();
        $invoice = new Invoice();
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->currency = 'usd';
        $invoice->setCustomer($customer);
        $builder->addInvoice($invoice);
        $form = $builder->build();
        $this->assertEquals([], $form->methods);

        $override = new PaymentInstruction();
        $override->meta = 'test2';
        $override->enabled = true;
        $override->payment_method_id = PaymentMethod::CASH;
        $override->country = 'UK';
        $override->saveOrFail();

        $customer = new Customer(['id' => -10]);
        $customer->country = 'UK';
        $builder = $this->getFormBuilder($customer);
        $invoice = new Invoice();
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->currency = 'usd';
        $invoice->setCustomer($customer);
        $builder->addInvoice($invoice);
        $form = $builder->build();
        $methods = $form->methods;
        $method = $methods[PaymentMethod::CASH];
        $this->assertCount(1, $methods);
        $this->assertEquals('test2', $method->meta);
        $this->assertTrue($method->enabled);
    }

    public function testMethodsWithInvoiceDisabledMethod(): void
    {
        $customer = new Customer(['id' => 100]);
        $builder = $this->getFormBuilder($customer);

        $invoice = new Invoice(['id' => 101]);
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer($customer);
        $invoice->currency = 'USD';

        $disabled = new DisabledPaymentMethod();
        $disabled->object_type = ObjectType::Invoice->typeName();
        $disabled->object_id = '101';
        $disabled->method = PaymentMethod::CHECK;
        $disabled->save();

        $builder->addInvoice($invoice);

        $form = $builder->build();
        $methods = array_keys($form->methods);
        sort($methods);
        $this->assertEquals([], $methods);
    }

    public function testMethodsWithCreditCard(): void
    {
        self::$paymentMethod->enabled = true;
        self::$paymentMethod->saveOrFail();

        $customer = new Customer(['id' => 101]);
        $builder = $this->getFormBuilder($customer);

        $invoice = new Invoice(['id' => 100]);
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer($customer);
        $invoice->currency = 'USD';
        $builder->addInvoice($invoice);

        $form = $builder->build();
        $methods = array_keys($form->methods);
        sort($methods);
        $this->assertEquals([PaymentMethod::CHECK, PaymentMethod::CREDIT_CARD], $methods);
    }

    public function testMethodsAutoPayInvoice(): void
    {
        $customer = new Customer(['id' => 104]);
        $builder = $this->getFormBuilder($customer);

        $invoice = new Invoice();
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer($customer);
        $invoice->currency = 'USD';
        $invoice->autopay = true;
        $builder->addInvoice($invoice);

        $form = $builder->build();
        $methods = array_keys($form->methods);
        sort($methods);
        $this->assertEquals([PaymentMethod::CREDIT_CARD], $methods);
    }

    public function testMethodsEstimate(): void
    {
        self::acceptsPaymentMethod(PaymentMethod::PAYPAL, null, 'test@example.com');
        $customer = new Customer(['id' => 104]);
        $builder = $this->getFormBuilder($customer);

        $estimate = new Estimate();
        $estimate->number = '1';
        $estimate->tenant_id = (int) self::$company->id();
        $estimate->setCustomer($customer);
        $estimate->currency = 'USD';
        $estimate->deposit = 10;
        $builder->addEstimate($estimate);

        $form = $builder->build();
        $methods = array_keys($form->methods);
        sort($methods);
        $this->assertEquals([PaymentMethod::CREDIT_CARD, PaymentMethod::PAYPAL], $methods);
    }

    public function testMethodsUnsupportedCurrency(): void
    {
        self::$paymentMethod->setMerchantAccount(self::$merchantAccount);
        self::$paymentMethod->saveOrFail();

        $customer = new Customer(['id' => 105]);
        $builder = $this->getFormBuilder($customer);
        $invoice = new Invoice();
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(new Customer(['id' => 105]));
        $invoice->currency = 'GGP';
        $builder->addInvoice($invoice);

        $form = $builder->build();
        $methods = array_keys($form->methods);
        sort($methods);
        $this->assertEquals([PaymentMethod::CHECK, PaymentMethod::PAYPAL], $methods);
    }

    public function testMethodsWithCustomerDisabledMethod(): void
    {
        self::$paymentMethod->gateway = MockGateway::ID;
        self::$paymentMethod->saveOrFail();

        $customer = new Customer(['id' => 102]);
        $builder = $this->getFormBuilder($customer);

        $invoice = new Invoice();
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer($customer);
        $invoice->currency = 'USD';
        $invoice->autopay = true;
        $builder->addInvoice($invoice);

        $disabled = new DisabledPaymentMethod();
        $disabled->object_type = ObjectType::Customer->typeName();
        $disabled->object_id = '102';
        $disabled->method = PaymentMethod::CHECK;
        $disabled->save();

        $form = $builder->build();
        $methods = array_keys($form->methods);
        $this->assertEquals([PaymentMethod::CREDIT_CARD], $methods);
    }

    public function testMethodsWithMinimumAmount(): void
    {
        $check = PaymentMethod::instance(self::$company, PaymentMethod::CHECK);
        $check->enabled = true;
        $check->saveOrFail();
        $card = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
        $card->min = 10001;
        $card->max = null;
        $card->enabled = true;
        $card->saveOrFail();

        DisabledPaymentMethod::queryWithCurrentTenant()->delete();

        $customer = new Customer(['id' => 106]);
        $builder = $this->getFormBuilder($customer);

        $invoice = new Invoice(['id' => 100]);
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer($customer);
        $invoice->currency = 'USD';
        $invoice->balance = 100;
        $builder->addInvoice($invoice);

        $form = $builder->build();
        $methods = array_keys($form->methods);
        sort($methods);
        $this->assertEquals([PaymentMethod::CHECK, PaymentMethod::PAYPAL], $methods);
    }

    public function testMethodsWithMaximumAmount(): void
    {
        $check = PaymentMethod::instance(self::$company, PaymentMethod::CHECK);
        $check->enabled = true;
        $check->saveOrFail();
        $card = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
        $card->min = null;
        $card->max = 9999;
        $card->enabled = true;
        $card->saveOrFail();

        DisabledPaymentMethod::queryWithCurrentTenant()->delete();

        $customer = new Customer(['id' => 107]);
        $builder = $this->getFormBuilder($customer);

        $invoice = new Invoice(['id' => 100]);
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer($customer);
        $invoice->currency = 'USD';
        $invoice->balance = 100;

        $builder->addInvoice($invoice);

        $form = $builder->build();
        $methods = array_keys($form->methods);
        sort($methods);
        $this->assertEquals([PaymentMethod::CHECK, PaymentMethod::PAYPAL], $methods);
    }

    public function testGetSavedPaymentSources(): void
    {
        $card = new Card();
        $customer = new Customer();
        $customer->refreshWith(['id' => 103]);
        /** @var Customer|Mockery\MockInterface $customer */
        $customer = Mockery::mock($customer);
        $customer->shouldReceive('paymentSources')
            ->andReturn([$card]);
        $builder = $this->getFormBuilder($customer);

        $invoice = new Invoice(['id' => 100]);
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(new Customer(['id' => 103]));
        $invoice->currency = 'usd';
        $builder->addInvoice($invoice);

        $form = $builder->build();
        $this->assertEquals([$card], $form->getSavedPaymentSources());
    }

    public function testGetSavedPaymentSourcesDisabledMethod(): void
    {
        $card = new Card();
        $customer = new Customer(['id' => 108]);
        /** @var Customer|Mockery\MockInterface $customer */
        $customer = Mockery::mock($customer);
        $customer->shouldReceive('paymentSources')
            ->andReturn([$card]);
        $builder = $this->getFormBuilder($customer);

        $invoice = new Invoice(['id' => 102]);
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(new Customer(['id' => 108]));
        $invoice->currency = 'usd';
        $builder->addInvoice($invoice);

        $disabled = new DisabledPaymentMethod();
        $disabled->object_type = ObjectType::Invoice->typeName();
        $disabled->object_id = '102';
        $disabled->method = PaymentMethod::CREDIT_CARD;
        $disabled->save();

        $form = $builder->build();
        $this->assertEquals([], $form->getSavedPaymentSources());
    }

    public function testAddInvoiceFail(): void
    {
        $this->expectException(FormException::class);
        $builder = $this->getFormBuilder();
        $invoice = new Invoice();
        $builder->addInvoice($invoice);
    }

    public function testAddInvoiceFail2(): void
    {
        $this->expectException(FormException::class);
        $builder = $this->getFormBuilder();
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->voided = true;
        $builder->addInvoice($invoice);
    }

    public function testAddInvoiceFail3(): void
    {
        $this->expectException(FormException::class);
        $customer = new Customer(['id' => 100]);
        $builder = $this->getFormBuilder($customer);
        $invoice3 = new Invoice();
        $invoice3->number = '3';
        $invoice3->tenant_id = (int) self::$company->id();
        $invoice3->customer = 101;
        $invoice3->currency = 'USD';
        $builder->addInvoice($invoice3);
    }

    public function testAddInvoiceFail4(): void
    {
        $this->expectException(FormException::class);
        $customer = new Customer(['id' => 100]);
        $builder = $this->getFormBuilder($customer);

        $invoice = new Invoice();
        $invoice->voided = false;
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(new Customer(['id' => 100]));
        $invoice->currency = 'USD';
        $invoice->number = 'INV-0001';
        $builder->addInvoice($invoice);

        $invoice4 = new Invoice();
        $invoice4->number = '4';
        $invoice4->tenant_id = (int) self::$company->id();
        $invoice4->customer = 100;
        $invoice4->currency = 'EUR';
        $builder->addInvoice($invoice4);
    }

    public function testAddInvoiceNegative(): void
    {
        $this->expectException(FormException::class);
        $builder = $this->getFormBuilder();
        $invoice2 = new Invoice();
        $invoice2->number = '2';
        $invoice2->currency = 'usd';
        $invoice2->tenant_id = (int) self::$company->id();
        $invoice2->setCustomer(self::$customer);
        $invoice2->balance = -10;
        $builder->addInvoice($invoice2);
    }

    public function testInvoices(): void
    {
        $customer = new Customer(['id' => 100]);
        $builder = $this->getFormBuilder($customer);

        $invoice = new Invoice();
        $invoice->voided = false;
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(new Customer(['id' => 100]));
        $invoice->currency = 'USD';
        $invoice->number = 'INV-0001';
        $invoice->balance = 100;
        $builder->addInvoice($invoice);

        $invoice2 = new Invoice();
        $invoice2->number = '2';
        $invoice2->tenant_id = (int) self::$company->id();
        $invoice2->customer = 100;
        $invoice2->currency = 'USD';
        $invoice2->balance = 50;
        $builder->addInvoice($invoice2);

        $form = $builder->build();
        $this->assertEquals([$invoice, $invoice2], $form->documents);
        $this->assertEquals([
            new PaymentFormItem(
                amount: new Money('usd', 10000),
                description: 'INV-0001',
                document: $invoice,
                amountOption: PaymentAmountOption::PayInFull
            ),
            new PaymentFormItem(
                amount: new Money('usd', 5000),
                description: '2',
                document: $invoice2,
                amountOption: PaymentAmountOption::PayInFull
            ),
        ], $form->paymentItems);
    }

    public function testAddCreditNoteFail(): void
    {
        $this->expectException(FormException::class);
        $builder = $this->getFormBuilder();
        $creditNote = new CreditNote(['voided' => true, 'number' => '1']);
        $creditNote->setCustomer(self::$customer);
        $builder->addCreditNote($creditNote);
    }

    public function testAddCreditNote(): void
    {
        $builder = $this->getFormBuilder();

        $creditNote = new CreditNote([
            'tenant_id' => self::$company->id(),
            'currency' => 'usd',
            'number' => 2,
            'balance' => 100,
        ]);
        $creditNote->setCustomer(self::$customer);
        $builder->addCreditNote($creditNote);

        $form = $builder->build();
        $this->assertEquals([$creditNote], $form->documents);
        $this->assertEquals([
            new PaymentFormItem(
                amount: new Money('usd', -10000),
                description: '2',
                document: $creditNote,
                amountOption: PaymentAmountOption::ApplyCredit
            ),
        ], $form->paymentItems);
    }

    public function testAddEstimateFail(): void
    {
        $this->expectException(FormException::class);
        $builder = $this->getFormBuilder();

        $estimate = new Estimate();
        $builder->addEstimate($estimate);
    }

    public function testAddEstimateFail2(): void
    {
        $this->expectException(FormException::class);
        $builder = $this->getFormBuilder();

        $estimate = new Estimate();
        $estimate->tenant_id = (int) self::$company->id();
        $estimate->voided = true;
        $builder->addEstimate($estimate);
    }

    public function testAddEstimateFail3(): void
    {
        $this->expectException(FormException::class);
        $customer = new Customer(['id' => 100]);
        $builder = $this->getFormBuilder($customer);

        $estimate3 = new Estimate();
        $estimate3->number = '3';
        $estimate3->tenant_id = (int) self::$company->id();
        $estimate3->customer = 101;
        $estimate3->currency = 'USD';
        $estimate3->deposit = 10;
        $builder->addEstimate($estimate3);
    }

    public function testAddEstimateFail4(): void
    {
        $this->expectException(FormException::class);
        $customer = new Customer(['id' => 100]);
        $builder = $this->getFormBuilder($customer);

        $estimate = new Estimate();
        $estimate->number = '1';
        $estimate->voided = false;
        $estimate->tenant_id = (int) self::$company->id();
        $estimate->setCustomer(new Customer(['id' => 100]));
        $estimate->currency = 'USD';
        $estimate->deposit = 10;
        $estimate->number = 'EST-0001';
        $builder->addEstimate($estimate);

        $estimate4 = new Estimate();
        $estimate4->number = '4';
        $estimate4->tenant_id = (int) self::$company->id();
        $estimate4->customer = 100;
        $estimate4->currency = 'EUR';
        $estimate4->deposit = 10;
        $builder->addEstimate($estimate4);
    }

    public function testAddEstimate(): void
    {
        $customer = new Customer(['id' => 100]);
        $builder = $this->getFormBuilder($customer);

        $estimate = new Estimate();
        $estimate->number = '1';
        $estimate->voided = false;
        $estimate->tenant_id = (int) self::$company->id();
        $estimate->setCustomer(new Customer(['id' => 100]));
        $estimate->currency = 'USD';
        $estimate->deposit = 10;
        $estimate->number = 'EST-0001';
        $builder->addEstimate($estimate);

        $estimate2 = new Estimate();
        $estimate2->number = '2';
        $estimate2->tenant_id = (int) self::$company->id();
        $estimate2->customer = 100;
        $estimate2->currency = 'USD';
        $estimate2->deposit = 10;
        $builder->addEstimate($estimate2);

        $form = $builder->build();
        $this->assertEquals([$estimate, $estimate2], $form->documents);
        $this->assertEquals([
            new PaymentFormItem(
                amount: new Money('usd', 1000),
                description: 'EST-0001',
                document: $estimate,
                amountOption: PaymentAmountOption::PayInFull
            ),
            new PaymentFormItem(
                amount: new Money('usd', 1000),
                description: '2',
                document: $estimate2,
                amountOption: PaymentAmountOption::PayInFull
            ),
        ], $form->paymentItems);
    }

    public function testAddAdvancePayment(): void
    {
        $customer = new Customer(['id' => 100]);
        $builder = $this->getFormBuilder($customer);
        $builder->addAdvancePayment($customer, PaymentAmountOption::AdvancePayment, new Money('usd', 10000));

        $form = $builder->build();
        $this->assertEquals([], $form->documents);
        $this->assertEquals([
            new PaymentFormItem(
                amount: new Money('usd', 10000),
                description: 'Advance Payment',
                document: null,
                amountOption: PaymentAmountOption::AdvancePayment
            ),
        ], $form->paymentItems);
    }

    public function testCreditBalance(): void
    {
        self::hasCredit();
        $builder = $this->getFormBuilder();

        $builder->addCreditBalance(self::$customer, PaymentAmountOption::ApplyCredit, new Money('usd', -10000));

        $form = $builder->build();
        $this->assertEquals([], $form->documents);
        $this->assertEquals([
            new PaymentFormItem(
                amount: new Money('usd', -10000),
                description: 'Credit Balance',
                document: null,
                amountOption: PaymentAmountOption::ApplyCredit
            ),
        ], $form->paymentItems);
    }

    public function testCustomer(): void
    {
        $customer = new Customer();
        $builder = $this->getFormBuilder($customer);

        $invoice = new Invoice();
        $invoice->number = '1';
        $invoice->currency = 'usd';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer($customer);
        $builder->addInvoice($invoice);
        $form = $builder->build();
        $this->assertEquals($customer, $form->customer);

        $builder = $this->getFormBuilder();

        $invoice = new Invoice();
        $invoice->number = '1';
        $invoice->currency = 'usd';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);
        $builder->addInvoice($invoice);
        $form = $builder->build();
        $customer = $form->customer;
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals(self::$customer->id(), $customer->id());
    }

    public function testCurrency(): void
    {
        $customer = new Customer(['id' => 100]);
        $builder = $this->getFormBuilder($customer);

        $invoice = new Invoice();
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer($customer);
        $invoice->currency = 'EUR';
        $builder->addInvoice($invoice);

        $form = $builder->build();
        $this->assertEquals('eur', $form->currency);
    }

    public function testTotalAmount(): void
    {
        $builder = $this->getFormBuilder();

        $builder = $this->getFormBuilder();
        $invoice = new Invoice();
        $invoice->number = '1';
        $invoice->currency = 'usd';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);
        $invoice->balance = 100;
        $builder->addInvoice($invoice);

        $invoice3 = new Invoice();
        $invoice3->number = '3';
        $invoice3->currency = 'usd';
        $invoice3->tenant_id = (int) self::$company->id();
        $invoice3->setCustomer(self::$customer);
        $invoice3->balance = 0.37;
        $builder->addInvoice($invoice3);

        $form = $builder->build();
        $amount = $form->totalAmount;
        $this->assertEquals(10037, $amount->amount);
        $this->assertEquals('usd', $amount->currency);

        $invoice4 = new Invoice();
        $invoice4->currency = 'usd';
        $invoice4->setCustomer(self::$customer);
        $invoice4->items = [['unit_cost' => 500]];
        $invoice4->saveOrFail();

        $transaction = new Transaction();
        $transaction->setInvoice($invoice4);
        $transaction->amount = 400;
        $transaction->status = Transaction::STATUS_PENDING;
        $transaction->saveOrFail();
        $builder->addInvoice($invoice4->refresh());

        $form = $builder->build();
        $amount = $form->totalAmount;
        $this->assertEquals(20037, $amount->amount);
        $this->assertEquals('usd', $amount->currency);

        $form = $builder->build();
        $amount = $form->totalAmount;
        $this->assertEquals('usd', $amount->currency);
        $this->assertEquals(20037, $amount->amount);

        $invoice5 = new Invoice();
        $invoice5->currency = 'usd';
        $invoice5->setCustomer(self::$customer);
        $invoice5->items = [['unit_cost' => 300]];
        $invoice5->saveOrFail();

        $installment1 = new PaymentPlanInstallment();
        $installment1->date = strtotime('-1 month');
        $installment1->amount = 100;
        $installment2 = new PaymentPlanInstallment();
        $installment2->date = strtotime('+3 days');
        $installment2->amount = 100;
        $installment3 = new PaymentPlanInstallment();
        $installment3->date = strtotime('+1 month');
        $installment3->amount = 100;
        $paymentPlan = new PaymentPlan();
        $paymentPlan->installments = [$installment1, $installment2, $installment3];

        $invoice5->attachPaymentPlan($paymentPlan, false, true);
        $builder->addInvoice($invoice5, PaymentAmountOption::PaymentPlan);

        $form = $builder->build();
        $amount = $form->totalAmount;
        $this->assertEquals('usd', $amount->currency);
        $this->assertEquals(40037, $amount->amount);
    }

    public function testMethod(): void
    {
        $builder = $this->getFormBuilder();

        $builder->addInvoice(self::$invoice);

        $method = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);

        $form = $builder->build();
        $this->assertNull($form->method);

        $builder->setMethod($method);
        $form = $builder->build();
        $this->assertEquals($method, $form->method);
    }

    public function testGetPaymentSource(): void
    {
        $builder = $this->getFormBuilder();

        $builder->addInvoice(self::$invoice);
        $form = $builder->build();
        $this->assertNull($form->paymentSource);

        self::hasCard();
        $builder->setPaymentSource(self::$card->object, (string) self::$card->id);
        $form = $builder->build();
        $this->assertEquals(self::$card->id, $form->paymentSource?->id);
    }

    public function testAllowAutoPayEnrollmentAlreadyEnrolled(): void
    {
        $customer = new Customer();
        $customer->autopay = true;
        $builder = $this->getFormBuilder($customer);

        $invoice = new Invoice();
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->currency = 'usd';
        $invoice->setCustomer($customer);
        $invoice->autopay = true;
        $builder->addInvoice($invoice);

        $form = $builder->build();
        $this->assertFalse($form->allowAutoPayEnrollment);
    }

    public function testAllowAutoPayEnrollmentSettingDisabled(): void
    {
        $builder = $this->getFormBuilder();

        $builder->addInvoice(self::$invoice);

        $form = $builder->build();
        $this->assertFalse($form->allowAutoPayEnrollment);
    }

    public function testallowAutoPayEnrollment(): void
    {
        $settings = new PaymentFormSettings(
            self::$company,
            false,
            false,
            false,
            true
        );
        $builder = $this->getFormBuilder(self::$customer, $settings);

        $builder->addInvoice(self::$invoice);

        $method = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
        $builder->setMethod($method);

        $form = $builder->build();
        $this->assertTrue($form->allowAutoPayEnrollment);
    }

    public function testShouldCapturePaymentInfoNoAutoPay(): void
    {
        $customer = new Customer(['id' => -10]);
        $builder = $this->getFormBuilder($customer);

        $invoice = new Invoice();
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->currency = 'usd';
        $invoice->setCustomer($customer);
        $builder->addInvoice($invoice);

        $form = $builder->build();
        $this->assertFalse($form->shouldCapturePaymentInfo);
    }

    public function testShouldCapturePaymentInfoAutoPayInvoice(): void
    {
        $customer = new Customer(['id' => -10]);
        $builder = $this->getFormBuilder($customer);

        $invoice = new Invoice();
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->currency = 'usd';
        $invoice->setCustomer($customer);
        $invoice->autopay = true;
        $builder->addInvoice($invoice);

        $form = $builder->build();
        $this->assertTrue($form->shouldCapturePaymentInfo);
    }

    public function testShouldCapturePaymentInfoAutoPayInvoiceExistingInfo(): void
    {
        $card = new Card();
        $customer = new Customer(['id' => -10]);
        $customer->setPaymentSource($card);
        $builder = $this->getFormBuilder($customer);

        $invoice = new Invoice();
        $invoice->number = '1';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->currency = 'usd';
        $invoice->setCustomer($customer);
        $invoice->autopay = true;
        $builder->addInvoice($invoice);

        $form = $builder->build();
        $this->assertFalse($form->shouldCapturePaymentInfo);
    }

    public function testShouldCapturePaymentInfoAutoPayNoInvoices(): void
    {
        $customer = new Customer(['autopay' => true]);
        $builder = $this->getFormBuilder($customer);
        $estimate = new Estimate();
        $estimate->number = '1';
        $estimate->tenant_id = (int) self::$company->id();
        $estimate->currency = 'usd';
        $estimate->setCustomer($customer);
        $estimate->deposit = 100;
        $builder->addEstimate($estimate);

        $form = $builder->build();
        $this->assertTrue($form->shouldCapturePaymentInfo);
    }
}
