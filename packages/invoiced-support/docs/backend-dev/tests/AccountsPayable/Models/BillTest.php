<?php

namespace App\Tests\AccountsPayable\Models;

use App\AccountsPayable\Enums\PayableDocumentStatus;
use App\ActivityLog\Libs\EventSpool;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class BillTest extends AppTestCase
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
            'due_date' => CarbonImmutable::now()->addMonth(),
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
        self::$bill = self::getService('test.bill_create')->create($parameters);

        $this->assertEquals(self::$company->id, self::$bill->tenant_id);
        $this->assertEquals(600, self::$bill->total);
        $this->assertEquals('usd', self::$bill->currency);
        $this->assertEquals(PayableDocumentStatus::PendingApproval, self::$bill->status);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'created_at' => self::$bill->created_at,
            'currency' => 'usd',
            'date' => self::$bill->date,
            'id' => self::$bill->id,
            'object' => 'bill',
            'updated_at' => self::$bill->updated_at,
            'vendor_id' => self::$vendor->id,
            'voided' => false,
            'approval_workflow_id' => null,
            'approval_workflow_step_id' => null,
            'date_voided' => null,
            'due_date' => self::$bill->due_date,
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
        $arr = self::$bill->toArray();
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
        self::getService('test.bill_edit')->edit(self::$bill, $parameters);
        $this->assertEquals(100, self::$bill->total);
    }

    /**
     * @depends testCreate
     */
    public function testVoid(): void
    {
        EventSpool::enable();

        self::getService('test.bill_void')->void(self::$bill);

        $this->assertTrue(self::$bill->persisted());
        $this->assertTrue(self::$bill->voided);
        $this->assertNotNull(self::$bill->date_voided);
        $this->assertEquals(PayableDocumentStatus::Voided, self::$bill->status);
    }
}
