<?php

namespace App\Tests\AccountsReceivable\Libs;

use App\AccountsReceivable\Exception\EstimateApprovalFormException;
use App\AccountsReceivable\Libs\CustomerHierarchy;
use App\AccountsReceivable\Libs\EstimateApprovalForm;
use App\AccountsReceivable\Libs\EstimateApprovalFormProcessor;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\Companies\Models\Company;
use App\CustomerPortal\Libs\CustomerPortal;
use App\Tests\AppTestCase;

class EstimateApprovalFormTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasEstimate();
    }

    private function getEstimate(): Estimate
    {
        $estimate = new Estimate();
        $estimate->tenant_id = (int) self::$company->id();
        $estimate->currency = 'usd';
        $estimate->deposit = 0;
        $estimate->setCustomer(self::$customer);
        $estimate->setRelation('customer', self::$customer);

        return $estimate;
    }

    private function getForm(?Estimate $estimate = null): EstimateApprovalForm
    {
        if (!$estimate) {
            $estimate = $this->getEstimate();
        }

        $customerPortal = new CustomerPortal(self::$company, new CustomerHierarchy(self::getService('test.database')));

        return new EstimateApprovalForm($customerPortal, $estimate);
    }

    private function getFormProcessor(): EstimateApprovalFormProcessor
    {
        return self::getService('test.estimate_approval_form_processor');
    }

    public function testGetEstimate(): void
    {
        $form = $this->getForm();
        $this->assertInstanceOf(Estimate::class, $form->getEstimate());
    }

    public function testGetCustomer(): void
    {
        $form = $this->getForm();
        $this->assertInstanceOf(Customer::class, $form->getCustomer());
    }

    public function testGetCompany(): void
    {
        $form = $this->getForm();
        $this->assertInstanceOf(Company::class, $form->getCompany());
    }

    public function testUseNewEstimateForm(): void
    {
        $form = $this->getForm();
        $this->assertTrue($form->useNewEstimateForm());
        self::$company->features->disable('estimates_v2');
        $this->assertFalse($form->useNewEstimateForm());
    }

    public function testMustVerifyBillingInformation(): void
    {
        $form = $this->getForm();
        $this->assertTrue($form->mustVerifyBillingInformation());
        self::$company->customer_portal_settings->enabled = false;
        $this->assertFalse($form->mustVerifyBillingInformation());
        self::$company->customer_portal_settings->enabled = true;
    }

    public function testHasDeposit(): void
    {
        self::$company->features->disable('estimates_v2');
        $form = $this->getForm();
        $this->assertFalse($form->hasDeposit());
        $this->assertFalse($form->hasRequiredDeposit());

        $estimate = new Estimate();
        $estimate->tenant_id = (int) self::$company->id();
        $estimate->currency = 'usd';
        $estimate->deposit = 500;
        $estimate->setCustomer(self::$customer);
        $form = $this->getForm($estimate);
        $this->assertTrue($form->hasDeposit());
        $this->assertTrue($form->hasRequiredDeposit());

        $estimate->deposit_paid = false;
        $form = $this->getForm($estimate);
        $this->assertTrue($form->hasDeposit());
        $this->assertTrue($form->hasRequiredDeposit());

        $estimate = new Estimate();
        $estimate->tenant_id = (int) self::$company->id();
        $estimate->currency = 'usd';
        $estimate->deposit = 500;
        $estimate->customer = -1234;
        $customer = new Customer();
        $customer->autopay = true;
        $estimate->setRelation('customer', $customer);
        $form = $this->getForm($estimate);
        $this->assertTrue($form->hasDeposit());
        $this->assertFalse($form->hasRequiredDeposit());

        self::$company->features->enable('estimates_v2');
        $this->assertTrue($form->hasRequiredDeposit());
        self::$company->features->disable('estimates_v2');
    }

    public function testNeedsPaymentInformation(): void
    {
        $customer = new Customer();
        $estimate = new Estimate();
        $estimate->tenant_id = (int) self::$company->id();
        $estimate->customer = 100;
        $estimate->setRelation('customer', $customer);

        $form = $this->getForm($estimate);
        $this->assertFalse($form->needsPaymentInformation());

        $customer->autopay = true;
        $this->assertTrue($form->needsPaymentInformation());
    }

    public function testHandleSubmit(): void
    {
        $estimate = new Estimate();
        $estimate->tenant_id = (int) self::$company->id();
        $estimate->setCustomer(self::$customer);
        $estimate->items = [['unit_cost' => 400]];
        $this->assertTrue($estimate->save());

        $this->getFormProcessor()->handleSubmit($estimate, ['initials' => 'JTK'], '127.0.0.1', 'firefox');
        $this->assertEquals('JTK', $estimate->approved);
    }

    public function testHandleSubmitFail(): void
    {
        $this->expectException(EstimateApprovalFormException::class);
        $this->expectExceptionMessage('Could not mark estimate as approved');

        $estimate = $this->getEstimate();

        $this->getFormProcessor()->handleSubmit($estimate, [], '127.0.0.1', 'firefox');
    }
}
