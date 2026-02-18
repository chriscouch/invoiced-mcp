<?php

namespace App\Tests\Integrations\NetSuite\Writers;

use App\CashApplication\Models\Transaction;
use App\Integrations\NetSuite\Writers\NetSuiteTransactionPaymentWriter;
use App\Integrations\NetSuite\Writers\NetSuiteWriter;
use App\PaymentProcessing\Models\PaymentMethod;

class NetSuiteTransactionPaymentWriterTest extends AbstractWriterTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testToArray(): void
    {
        self::hasCustomer();
        $adjustment = $this->createAdjustment();
        $valueObject = new NetSuiteTransactionPaymentWriter($adjustment);
        $this->assertNull($valueObject->toArray());

        self::hasNetSuiteCustomer();
        $adjustment = $this->createAdjustment();
        $valueObject = new NetSuiteTransactionPaymentWriter($adjustment);
        $response = $valueObject->toArray();
        $this->assertEquals($response, [
            'amount' => 100,
            'custbody_invoiced_id' => $adjustment->id,
            'customer' => 1,
            'invoices' => [
                [
                    'amount' => 100,
                    'id' => null,
                    'type' => Transaction::TYPE_ADJUSTMENT,
                ],
            ],
            'checknum' => null,
            'gateway' => null,
            'payment_source' => null,
            'type' => Transaction::TYPE_ADJUSTMENT,
            'payment' => null,
        ]);

        // parent - adjustment
        // children - mapped and not mapped invoices
        self::hasNetSuiteCustomer();
        $adjustment = $this->createAdjustment();
        // reset adjustment balance
        self::hasInvoice();
        self::hasNetSuiteInvoice();
        $netsuiteTransaction = $this->createTransaction(self::$invoice, self::$invoice->balance, $adjustment);
        self::hasInvoice();
        $transaction = $this->createTransaction(self::$invoice, self::$invoice->balance, $adjustment);
        $valueObject = new NetSuiteTransactionPaymentWriter($adjustment);
        $response = $valueObject->toArray();
        $this->assertEquals($response, [
            'amount' => 300,
            'custbody_invoiced_id' => $adjustment->id,
            'customer' => 1,
            'invoices' => [
                [
                    'amount' => 100,
                    'id' => null,
                    'type' => $adjustment->type,
                ],
                [
                    'amount' => 100,
                    'id' => 3,
                    'type' => $netsuiteTransaction->type,
                ],
                [
                    'amount' => 100,
                    'id' => null,
                    'type' => $transaction->type,
                ],
            ],
            'checknum' => null,
            'gateway' => null,
            'payment_source' => null,
            'type' => $adjustment->type,
            'payment' => null,
        ]);

        // parent - mapped invoice
        // children - adjustment not mapped invoices
        self::hasNetSuiteInvoice();
        $netsuiteTransaction = $this->createTransaction(self::$invoice, self::$invoice->balance);
        $adjustment = $this->createAdjustment($netsuiteTransaction);
        // reset adjustment balance
        self::hasInvoice();
        self::hasInvoice();
        $transaction = $this->createTransaction(self::$invoice, self::$invoice->balance, $netsuiteTransaction);
        $valueObject = new NetSuiteTransactionPaymentWriter($netsuiteTransaction);
        $response = $valueObject->toArray();
        $this->assertEquals($response, [
            'amount' => 300,
            'custbody_invoiced_id' => $netsuiteTransaction->id,
            'customer' => 1,
            'invoices' => [
                [
                    'amount' => 100,
                    'id' => 3,
                    'type' => $netsuiteTransaction->type,
                ],
                [
                    'amount' => 100,
                    'id' => null,
                    'type' => $adjustment->type,
                ],
                [
                    'amount' => 100,
                    'id' => null,
                    'type' => $transaction->type,
                ],
            ],
            'checknum' => null,
            'gateway' => null,
            'payment_source' => null,
            'type' => $netsuiteTransaction->type,
            'payment' => null,
        ]);

        // testing get reverse mapping on fresh metadata
        $this->assertNull($valueObject->getReverseMapping());
        $netsuiteTransaction->metadata = (object) [NetSuiteWriter::REVERSE_MAPPING => 'test'];
        $netsuiteTransaction->saveOrFail();
        $valueObject = new NetSuiteTransactionPaymentWriter($netsuiteTransaction);
        $this->assertNotNull($valueObject->getReverseMapping());
    }

    private function createAdjustment(?Transaction $parentTransaction = null): Transaction
    {
        $adjustment = new Transaction();
        $adjustment->method = PaymentMethod::BALANCE;
        $adjustment->type = Transaction::TYPE_ADJUSTMENT;
        $adjustment->customer = self::$customer->id;
        $adjustment->amount = -100;
        if ($parentTransaction) {
            $adjustment->parent_transaction = $parentTransaction->id;
        }
        $adjustment->saveOrFail();

        return $adjustment;
    }
}
