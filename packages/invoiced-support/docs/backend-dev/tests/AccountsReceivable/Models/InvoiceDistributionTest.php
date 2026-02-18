<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Models\InvoiceDistribution;
use App\Sending\Email\Models\EmailTemplate;
use App\Tests\AppTestCase;

class InvoiceDistributionTest extends AppTestCase
{
    private static InvoiceDistribution $distribution;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();

        $emailTemplate = new EmailTemplate();
        $emailTemplate->id = 'new_invoice_email';
        $emailTemplate->subject = 'subject';
        $emailTemplate->body = 'test';
        $emailTemplate->saveOrFail();
    }

    public function testCreate(): void
    {
        self::$distribution = new InvoiceDistribution();
        self::$distribution->invoice_id = (int) self::$invoice->id();
        self::$distribution->template = 'new_invoice_email';
        self::$distribution->enabled = true;
        $this->assertTrue(self::$distribution->save());

        $this->assertEquals(self::$company->id(), self::$distribution->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $distributions = InvoiceDistribution::all();

        $this->assertCount(1, $distributions);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$distribution->id(),
            'department' => null,
            'template' => 'new_invoice_email',
            'enabled' => true,
            'created_at' => self::$distribution->created_at,
            'updated_at' => self::$distribution->updated_at,
        ];

        $this->assertEquals($expected, self::$distribution->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$distribution->enabled = false;
        $this->assertTrue(self::$distribution->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$distribution->delete());
    }
}
