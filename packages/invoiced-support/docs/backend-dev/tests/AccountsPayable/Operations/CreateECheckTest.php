<?php

namespace App\Tests\AccountsPayable\Operations;

use App\AccountsPayable\Models\ECheck;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\ValueObjects\PayVendorPayment;
use App\Core\I18n\ValueObjects\Money;
use App\Tests\AppTestCase;
use App\Core\Orm\Exception\ModelException;

class CreateECheckTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();

        self::hasCompanyBankAccount();
        self::$companyBankAccount->saveOrFail();
        self::hasBatchPayment();
        self::hasVendor();
        self::$vendor->address1 = 'address1';
        self::$vendor->address2 = 'address2';
        self::$vendor->city = 'city';
        self::$vendor->state = 'TX';
        self::$vendor->postal_code = '78738';
        self::$vendor->country = 'US';
        self::$vendor->saveOrFail();
        self::hasBill();
        self::hasBatchPaymentBill();
    }

    public function testCreate(): void
    {
        $create = self::getService('test.create_echeck');

        $parameters = [
            'email' => 'test',
        ];
        $payment = new PayVendorPayment(self::$vendor);
        $payment->addBatchBill(self::$batchPaymentBill);
        try {
            $create->create($payment, $parameters, self::$companyBankAccount, self::$vendor);
        } catch (ModelException $e) {
            $this->assertEquals('Email is invalid', $e->getMessage());
        }

        self::$company->country = 'UK';
        try {
            $parameters = [
                'email' => 'test@test.com',
            ];
            $create->create($payment, $parameters, self::$companyBankAccount, self::$vendor);
        } catch (ModelException $e) {
            $this->assertEquals('The feature available only in the USA', $e->getMessage());
        }

        self::$company->country = 'US';
        $parameters = [
            'email' => 'test@test.com',
            'amount' => new Money('usd', 20000),
            'account_id' => self::$companyBankAccount->id,
            'check_number' => self::$companyBankAccount->check_number,
            'address1' => self::$vendor->address1,
            'address2' => self::$vendor->address2,
            'city' => self::$vendor->city,
            'state' => self::$vendor->state,
            'postal_code' => self::$vendor->postal_code,
            'country' => self::$vendor->country,
        ];

        $checkNumber = self::$companyBankAccount->check_number;
        $this->assertEquals(0, VendorPayment::count());
        $this->assertEquals(0, ECheck::count());

        $bill = self::$batchPaymentBill;
        self::hasBill();
        self::hasBatchPaymentBill();
        $payment->addBatchBill(self::$batchPaymentBill);
        $vendorPayment = $create->create($payment, $parameters, self::$companyBankAccount, self::$vendor, self::$batchPayment);

        $this->assertEquals([
            'amount' => 200,
            'bank_account_id' => self::$companyBankAccount->id,
            'card_id' => null,
            'created_at' => $vendorPayment->created_at,
            'currency' => self::$bill->currency,
            'date' => $vendorPayment->date,
            'expected_arrival_date' => null,
            'id' => $vendorPayment->id,
            'notes' => null,
            'number' => 'PAY-00001',
            'object' => 'vendor_payment',
            'payment_method' => 'echeck',
            'reference' => $checkNumber,
            'updated_at' => $vendorPayment->updated_at,
            'vendor_id' => self::$bill->vendor_id,
            'vendor_payment_batch_id' => self::$batchPayment->id,
            'voided' => false,
        ], $vendorPayment->toArray());

        $this->assertEquals([
            [
                'amount' => 100.0,
                'bill_id' => $bill->bill->id,
                'vendor_payment_id' => $vendorPayment->id,
            ],
            [
                'amount' => 100.0,
                'bill_id' => self::$bill->id,
                'vendor_payment_id' => $vendorPayment->id,
            ],
        ], array_map(fn ($item) => array_intersect_key($item->toArray(), [
            'amount' => 1,
            'bill_id' => 1,
            'vendor_payment_id' => 1,
        ]), $vendorPayment->getItems()));

        $eCheck = ECheck::execute()[0];
        $this->assertEquals([
            'email' => 'test@test.com',
            'amount' => 200.0,
            'check_number' => self::$companyBankAccount->check_number,
            'account_id' => self::$companyBankAccount->id,
            'address1' => 'address1',
            'address2' => 'address2',
            'city' => 'city',
            'country' => 'US',
            'created_at' => $eCheck->created_at,
            'id' => $eCheck->id,
            'payment_id' => $vendorPayment->id,
            'postal_code' => 78738,
            'hash' => $eCheck->hash,
            'state' => 'TX',
            'updated_at' => $eCheck->updated_at,
            'viewed' => 0,
        ], $eCheck->toArray());
        $this->assertEquals($checkNumber, self::$companyBankAccount->refresh()->check_number);
    }
}
