<?php

namespace App\Tests\AccountsPayable\Models;

use App\AccountsPayable\Enums\PayableDocumentStatus;
use App\ActivityLog\Libs\EventSpool;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class VendorCreditTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasVendor();
    }

    public function testCreate(): void
    {
        EventSpool::enable();

        $parameters = [
            'vendor' => self::$vendor,
            'number' => 'INV-00001',
            'date' => CarbonImmutable::now(),
            'line_items' => [
                [
                    'description' => 'Line Item 1',
                    'amount' => 100,
                ],
                [
                    'description' => 'Line Item 2',
                    'amount' => 200,
                ],
                [
                    'description' => 'Line Item 3',
                    'amount' => 300,
                ],
            ],
        ];
        self::$vendorCredit = self::getService('test.vendor_credit_create')->create($parameters);

        $this->assertEquals(self::$company->id, self::$vendorCredit->tenant_id);
        $this->assertEquals(600, self::$vendorCredit->total);
        $this->assertEquals('usd', self::$vendorCredit->currency);
        $this->assertEquals(PayableDocumentStatus::PendingApproval, self::$vendorCredit->status);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'created_at' => self::$vendorCredit->created_at,
            'currency' => 'usd',
            'date' => self::$vendorCredit->date,
            'id' => self::$vendorCredit->id,
            'object' => 'vendor_credit',
            'updated_at' => self::$vendorCredit->updated_at,
            'vendor_id' => self::$vendor->id,
            'voided' => false,
            'approval_workflow_id' => null,
            'approval_workflow_step_id' => null,
            'date_voided' => null,
            'network_document_id' => null,
            'number' => 'INV-00001',
            'source' => 'Keyed',
            'status' => 'PendingApproval',
            'total' => 600.0,
            'line_items' => [
                [
                    'description' => 'Line Item 1',
                    'amount' => 100.0,
                ],
                [
                    'description' => 'Line Item 2',
                    'amount' => 200.0,
                ],
                [
                    'description' => 'Line Item 3',
                    'amount' => 300.0,
                ],
            ],
        ];
        $arr = self::$vendorCredit->toArray();
        foreach ($arr['line_items'] as &$lineItem) {
            unset($lineItem['created_at']);
            unset($lineItem['id']);
            unset($lineItem['updated_at']);
        }
        $this->assertEquals($expected, $arr);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        EventSpool::enable();

        $parameters = [
            'line_items' => [
                [
                    'description' => 'New Item',
                    'amount' => 100,
                ],
            ],
        ];
        self::getService('test.vendor_credit_edit')->edit(self::$vendorCredit, $parameters);
        $this->assertEquals(100, self::$vendorCredit->total);
    }

    /**
     * @depends testCreate
     */
    public function testVoid(): void
    {
        EventSpool::enable();

        self::getService('test.vendor_credit_void')->void(self::$vendorCredit);

        $this->assertTrue(self::$vendorCredit->persisted());
        $this->assertTrue(self::$vendorCredit->voided);
        $this->assertNotNull(self::$vendorCredit->date_voided);
        $this->assertEquals(PayableDocumentStatus::Voided, self::$vendorCredit->status);
    }
}
