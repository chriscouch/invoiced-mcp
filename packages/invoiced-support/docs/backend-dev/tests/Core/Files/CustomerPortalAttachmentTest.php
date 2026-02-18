<?php

namespace App\Tests\Core\Files;

use App\Core\Files\Models\CustomerPortalAttachment;
use App\Tests\AppTestCase;

class CustomerPortalAttachmentTest extends AppTestCase
{
    private static CustomerPortalAttachment $attachment;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasFile();
    }

    public function testCreateInvalidFile(): void
    {
        $attachment = new CustomerPortalAttachment();
        $attachment->file_id = 12384234;
        $this->assertFalse($attachment->save());
    }

    public function testCreate(): void
    {
        self::$attachment = new CustomerPortalAttachment();
        self::$attachment->file = self::$file;
        $this->assertTrue(self::$attachment->save());
        $this->assertEquals(self::$company->id(), self::$attachment->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $attachments = CustomerPortalAttachment::all();

        $this->assertCount(1, $attachments);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'object' => 'customer_portal_attachment',
            'file' => self::$file->toArray(),
            'created_at' => self::$attachment->created_at,
            'updated_at' => self::$attachment->updated_at,
            'file_id' => self::$file->id,
            'id' => self::$attachment->id,
        ];

        $this->assertEquals($expected, self::$attachment->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$attachment->delete());
    }
}
