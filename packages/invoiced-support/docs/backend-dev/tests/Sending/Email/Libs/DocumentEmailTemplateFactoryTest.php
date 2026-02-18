<?php

namespace App\Tests\Sending\Email\Libs;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Payment;
use App\Companies\Models\Company;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentProcessing\Models\Refund;
use App\Sending\Email\Interfaces\SendableDocumentInterface;
use App\Sending\Email\Libs\DocumentEmailTemplateFactory;
use App\Sending\Email\Models\EmailTemplate;
use App\Tests\AppTestCase;

class DocumentEmailTemplateFactoryTest extends AppTestCase
{
    private function getFactory(): DocumentEmailTemplateFactory
    {
        return new DocumentEmailTemplateFactory();
    }

    private function check(SendableDocumentInterface $document, string $expected): void
    {
        $template = $this->getFactory()->get($document);
        $this->assertEquals($expected, $template->id);
    }

    public function testGetCreditNote(): void
    {
        $creditNote = new CreditNote(['tenant_id' => -1]);
        $this->check($creditNote, EmailTemplate::CREDIT_NOTE);
    }

    public function testGetEstimate(): void
    {
        $estimate = new Estimate(['tenant_id' => -1]);
        $this->check($estimate, EmailTemplate::ESTIMATE);
    }

    public function testGetInvoice(): void
    {
        $invoice = new Invoice(['tenant_id' => -1]);
        $this->check($invoice, EmailTemplate::NEW_INVOICE);

        $invoice->sent = true;
        $template = $this->getFactory()->get($invoice);
        $this->assertEquals(EmailTemplate::UNPAID_INVOICE, $template->id);

        $invoice->status = InvoiceStatus::PastDue->value;
        $this->check($invoice, EmailTemplate::LATE_PAYMENT_REMINDER);

        $invoice->paid = true;
        $this->check($invoice, EmailTemplate::PAID_INVOICE);

        $invoice = new Invoice(['tenant_id' => -1]);
        $paymentPlan = new PaymentPlan(['id' => 1234]);
        $paymentPlan->status = PaymentPlan::STATUS_PENDING_SIGNUP;
        $invoice->payment_plan_id = 1234;
        $invoice->setRelation('payment_plan_id', $paymentPlan);
        $this->check($invoice, EmailTemplate::PAYMENT_PLAN);

        $paymentPlan->status = PaymentPlan::STATUS_ACTIVE;
        $this->check($invoice, EmailTemplate::NEW_INVOICE);
    }

    public function testGetPayment(): void
    {
        $payment = new Payment(['tenant_id' => -1]);
        $this->check($payment, EmailTemplate::PAYMENT_RECEIPT);
    }

    public function testGetRefund(): void
    {
        $refund = new Refund(['tenant_id' => -1]);
        $this->check($refund, EmailTemplate::REFUND);
    }

    public function testGetStatement(): void
    {
        $customer = new Customer(['tenant_id' => -1]);
        $customer->setRelation('tenant_id', new Company(['id' => -1]));
        $balanceForward = self::getService('test.statement_builder')->balanceForward($customer);
        $this->check($balanceForward, EmailTemplate::STATEMENT);

        $openItem = self::getService('test.statement_builder')->openItem($customer);
        $this->check($openItem, EmailTemplate::STATEMENT);
    }
}
