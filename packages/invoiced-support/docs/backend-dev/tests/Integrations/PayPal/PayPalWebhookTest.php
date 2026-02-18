<?php

namespace App\Tests\Integrations\PayPal;

use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\CashApplication\Models\Transaction;
use App\Integrations\Libs\IpnContext;
use App\Integrations\PayPal\Libs\PayPalWebhook;
use App\PaymentProcessing\Libs\PaymentGatewayMetadata;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Exception\ModelNotFoundException;
use stdClass;

class PayPalWebhookTest extends AppTestCase
{
    /**
     * @throws ModelException
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::acceptsPayPal();
        self::hasCustomer();
        self::hasInvoice();

        self::getService('test.redis')->flushdb();
    }

    protected function tearDown(): void
    {
        if (!self::getService('test.tenant')->has()) {
            self::getService('test.tenant')->set(self::$company);
        }
    }

    //
    // WebhookHandlerInterface
    //

    public function testGetCompanies(): void
    {
        // Setup - Models, Mocks, etc.

        $paypal = new IpnContext('01234567890123456789012345678912');

        $event = [
            'invoice' => 'i10005',
            'custom' => $paypal->encode((int) self::$company->id()),
        ];

        // Call the method being tested
        $handler = $this->getHandler();
        $companies = $handler->getCompanies($event);
        $this->assertCount(1, $companies);
        $this->assertEquals(self::$company->id(), $companies[0]->id());
    }

    public function testShouldProcess(): void
    {
        // Setup - Models, Mocks, etc.

        $handler = $this->getHandler();

        $event = [
            'test_ipn' => true,
            'txn_id' => 'test111',
        ];

        // Call the method being tested

        $this->assertTrue($handler->shouldProcess($event));

        // Verify the results

        $expected = [
            'test_ipn' => true,
            'txn_id' => 'test111',
        ];
        $this->assertEquals($expected, $event);

        // should not be processed again
        $this->assertFalse($handler->shouldProcess($event));
    }

    public function testShouldProcessMismatchedTestMode(): void
    {
        $event = [
            'test_ipn' => false,
        ];

        // Call the method being tested

        $handler = $this->getHandler();
        $this->assertFalse($handler->shouldProcess($event));
    }

    private function paymentCompletedInput(ReceivableDocument $document, int $time, string $txnId): array
    {
        return [
            'txn_type' => 'web_accept',
            'invoice' => 'i'.$document->id().'|100s'.$time,
            'payment_status' => 'Completed',
            'payment_date' => date('G:i:s M d, Y T', $time),
            'mc_currency' => 'usd',
            'mc_gross' => 1.00,
            'mc_fee' => 0.05,
            'txn_id' => $txnId,
            'payer_email' => 'sherlock@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
        ];
    }

    /**
     * @throws ModelNotFoundException
     */
    public function testPaymentCompletedWebhook(): void
    {
        // Setup - Models, Mocks, etc.

        $handler = $this->getHandler();

        self::$customer->email = null;
        $this->assertTrue(self::$customer->save());

        // setup the event
        $time = (int) mktime(0, 0, 0, 5, 2, 2014);

        $event = $this->paymentCompletedInput(self::$invoice, $time, 'test');
        // Call the method being tested

        $handler->process(self::$company, $event);
        $this->paymentOutput(self::$invoice, $time, 'test');
        // should save email address
        $this->assertEquals('sherlock@example.com', self::$customer->refresh()->email);
    }

    private function paymentInput(ReceivableDocument $base, int $time, string $txnId): array
    {
        return [
            'txn_type' => 'web_accept',
            'invoice' => $base->object[0].$base->id().'|100s'.$time,
            'payment_status' => 'Pending',
            'payment_date' => date('G:i:s M d, Y T', $time),
            'mc_currency' => 'usd',
            'mc_gross' => 1.00,
            'txn_id' => $txnId,
            'payer_email' => 'sherlock@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
        ];
    }

    /**
     * @throws ModelNotFoundException
     */
    private function paymentOutput(Invoice $invoice, int $time, string $gateway = 'test_2'): Transaction
    {
        // should create a payment
        /** @var Transaction $transaction */
        $transaction = Transaction::where('invoice', $invoice->id())
            ->where('type', Transaction::TYPE_CHARGE)
            ->where('method', PaymentMethod::PAYPAL)
            ->where('gateway', PaymentGatewayMetadata::PAYPAL)
            ->where('gateway_id', $gateway)
            ->oneOrNull();

        $this->assertInstanceOf(Transaction::class, $transaction);

        $expected = [
            'customer' => self::$customer->id(),
            'invoice' => $invoice->id(),
            'credit_note' => null,
            'type' => Transaction::TYPE_CHARGE,
            'method' => PaymentMethod::PAYPAL,
            'gateway' => PaymentGatewayMetadata::PAYPAL,
            'gateway_id' => $gateway,
            'payment_source' => null,
            'status' => Transaction::STATUS_SUCCEEDED,
            'currency' => 'usd',
            'amount' => 1.00,
            'date' => $time,
            'notes' => null,
            'parent_transaction' => null,
            'metadata' => new stdClass(),
            'estimate' => null,
            'payment_id' => $transaction->payment_id,
        ];

        $arr = $transaction->toArray();
        foreach (['object', 'created_at', 'updated_at', 'id', 'pdf_url'] as $property) {
            unset($arr[$property]);
        }

        $this->assertEquals($expected, $arr);

        return $transaction;
    }

    /**
     * @throws ModelNotFoundException
     */
    public function testInvoicePaymentPendingWebhook(): void
    {
        // Setup - Models, Mocks, etc.

        $handler = $this->getHandler();

        // setup the event
        $time = (int) mktime(0, 0, 0, 5, 2, 2014);
        $event = $this->paymentInput(self::$invoice, $time, 'test_invoice_3');

        // Call the method being tested

        $handler->process(self::$company, $event);
        $this->paymentOutput(self::$invoice, $time, 'test_invoice_3');
    }

    /**
     * @depends testInvoicePaymentPendingWebhook
     *
     * @throws ModelNotFoundException
     */
    public function testPaymentCompletedFromPendingWebhook(): void
    {
        // Setup - Models, Mocks, etc.

        $handler = $this->getHandler();

        // setup the event
        $time = (int) mktime(0, 0, 0, 5, 2, 2014);
        $event = $this->paymentInput(self::$invoice, $time, 'test_estimate_4');

        // Call the method being tested

        $handler->process(self::$company, $event);

        // Verify the results
        $this->paymentOutput(self::$invoice, $time, 'test_estimate_4');
    }

    public function testParseInvoiceId(): void
    {
        $handler = $this->getHandler();
        $result = $handler->parseInvoiceId('i1234|100i456|101c789|-102e1054|103a34|104');
        $expected = [
            ['invoice', '1234', 100],
            ['invoice', '456', 101],
            ['credit_note', '789', -102],
            ['estimate', '1054', 103],
            ['customer', '34', 104],
        ];
        $this->assertEquals($expected, $result);

        $result = $handler->parseInvoiceId('i1234|100i456|101c789|-102e1054|103a34|104s123485');
        $this->assertEquals($expected, $result);

        $result = $handler->parseInvoiceId('i1234|100');
        $expected = [
            ['invoice', '1234', 100],
        ];
        $this->assertEquals($expected, $result);

        $result = $handler->parseInvoiceId('e4567|101');
        $expected = [
            ['estimate', '4567', 101],
        ];
        $this->assertEquals($expected, $result);

        $result = $handler->parseInvoiceId('e4567|102s1234');
        $expected = [
            ['estimate', '4567', 102],
        ];
        $this->assertEquals($expected, $result);
    }

    private function getHandler(): PayPalWebhook
    {
        return self::getService('test.paypal_webhook');
    }
}
