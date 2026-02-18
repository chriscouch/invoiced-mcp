<?php

namespace App\Tests\PaymentProcessing;

use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Transaction;
use App\EntryPoint\QueueJob\AutoPayJob;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Operations\AutoPay;
use App\Tests\AppTestCase;
use Symfony\Component\Lock\LockFactory;

class AutoPayQueueJobTest extends AppTestCase
{
    public static LockFactory $lock;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$lock = self::getService('test.lock_factory');
    }

    public function testPerform(): void
    {
        $autoPay = \Mockery::mock(AutoPay::class);
        self::$company->canceled = true;
        self::$company->saveOrFail();
        $job = new AutoPayJob($autoPay, self::$lock);
        $job->args = ['tenant_id' => self::$company->id];
        $job->perform();

        self::$company->canceled = false;
        self::$company->saveOrFail();
        $job->perform();

        self::acceptsPaymentMethod(PaymentMethod::CREDIT_CARD, null, 'Payment instructions...');

        self::hasCustomer();
        $this->makeInvoice();
        $this->makeInvoice();
        $this->makeInvoice(['closed' => true]);
        // make paid invoice
        $this->makeInvoice();
        self::hasTransaction();
        $this->makeInvoice(['voided' => true]);
        $this->makeInvoice(['next_payment_attempt' => time() + 3600]);
        $this->makeInvoice(['next_payment_attempt' => null]);
        // pending invoice
        $this->makeInvoice();
        $pending = new Transaction();
        $pending->status = Transaction::STATUS_PENDING;
        $pending->type = Transaction::TYPE_CHARGE;
        $pending->setInvoice(self::$invoice);
        $pending->amount = 10;
        $pending->saveOrFail();

        $invoice = new Invoice();
        $invoice->draft = true;
        $invoice->setCustomer(self::$customer);
        $invoice->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        $invoice->saveOrFail();

        $autoPay->shouldReceive('collect')->twice()->andReturn(true);
        $job->perform();
    }

    private function makeInvoice(array $params = []): void
    {
        self::hasInvoice();
        self::$invoice->autopay = true;
        self::$invoice->next_payment_attempt = time();
        foreach ($params as $key => $value) {
            self::$invoice->{$key} = $value;
        }
        self::$invoice->saveOrFail();
    }
}
