<?php

namespace App\Tests\Network;

use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\VendorCredit;
use App\AccountsPayable\Models\VendorPayment;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\Companies\Models\Company;
use App\EntryPoint\QueueJob\SendNetworkDocumentQueueJob;
use App\Network\Enums\NetworkDocumentType;
use App\Network\Models\NetworkConnection;
use App\Network\Models\NetworkDocument;
use App\Tests\AppTestCase;
use DateTimeImmutable;

class SendNetworkDocumentQueueJobTest extends AppTestCase
{
    private static Company $company2;
    private static NetworkConnection $connection;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$company2 = self::getTestDataFactory()->createCompany();
        self::hasCompany();
        self::$connection = self::getTestDataFactory()->connectCompanies(self::$company, self::$company2);

        // Set up A/P models
        self::getService('test.tenant')->set(self::$company2);
        self::hasVendor();
        self::$vendor->network_connection = self::$connection;
        self::$vendor->saveOrFail();

        // Set up A/R models
        self::getService('test.tenant')->set(self::$company);
        self::hasCustomer();
        self::$customer->network_connection = self::$connection;
        self::$customer->saveOrFail();
        self::hasInvoice();
        self::hasEstimate();
        self::hasUnappliedCreditNote();
        self::hasPayment(self::$customer);
        self::$payment->applied_to = [['type' => PaymentItemType::Invoice->value, 'invoice' => self::$invoice, 'amount' => self::$invoice->balance]];
        self::$payment->saveOrFail();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        if (isset(self::$company2)) {
            self::$company2->delete();
        }
    }

    private function getJob(): SendNetworkDocumentQueueJob
    {
        return self::getService('test.send_network_document_queue_job');
    }

    public function testSendAllForCustomer(): void
    {
        $job = $this->getJob();
        $job->sendAllForCustomer(self::$customer);

        self::getService('test.tenant')->runAs(self::$company2, function () {
            // Should create a bill
            $bill = Bill::where('vendor_id', self::$vendor)
                ->where('number', 'INV-00001')
                ->oneOrNull();
            $this->assertInstanceOf(Bill::class, $bill);

            // Should create a vendor credit
            $vendorCredit = VendorCredit::where('vendor_id', self::$vendor)
                ->where('number', 'CN-00001')
                ->oneOrNull();
            $this->assertInstanceOf(VendorCredit::class, $vendorCredit);

            // Should create a quote network document
            $quote = NetworkDocument::where('type', NetworkDocumentType::Quotation->value)
                ->where('from_company_id', self::$company)
                ->where('reference', 'EST-00001')
                ->oneOrNull();
            $this->assertInstanceOf(NetworkDocument::class, $quote);

            // Should create a vendor payment
            $vendorPayment = VendorPayment::where('vendor_id', self::$vendor)
                ->oneOrNull();
            $this->assertInstanceOf(VendorPayment::class, $vendorPayment);
            $this->assertEquals(200, $vendorPayment->amount);
            $this->assertEquals('usd', $vendorPayment->currency);
            $this->assertEquals((new DateTimeImmutable('@'.self::$payment->date))->format('Y-m-d'), $vendorPayment->date->format('Y-m-d'));
            $this->assertEquals('', $vendorPayment->reference);
            $this->assertEquals('other', $vendorPayment->payment_method);
            $this->assertEquals('', $vendorPayment->notes);
            $expected = [
                [
                    'bill' => $bill->toArray(),
                    'vendor_credit' => null,
                    'amount' => 100.0,
                    'type' => 'application',
                ],
            ];
            $this->assertEquals($expected, $vendorPayment->applied_to);
        });
    }
}
