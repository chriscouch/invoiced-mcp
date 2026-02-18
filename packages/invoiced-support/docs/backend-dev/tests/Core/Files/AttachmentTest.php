<?php

namespace App\Tests\Core\Files;

use App\Core\Files\Models\Attachment;
use App\Tests\AppTestCase;

class AttachmentTest extends AppTestCase
{
    private static Attachment $attachment;

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
        $attachment = new Attachment();
        $attachment->parent_type = 'invoice';
        $attachment->parent_id = (int) self::$invoice->id();
        $attachment->file_id = 12384234;
        $this->assertFalse($attachment->save());
    }

    public function testCreate(): void
    {
        self::$attachment = new Attachment();
        $this->assertTrue(self::$attachment->create([
            'parent_type' => 'invoice',
            'parent_id' => self::$invoice->id(),
            'file_id' => self::$file->id(),
        ]));

        $this->assertEquals(self::$company->id(), self::$attachment->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $attachments = Attachment::all();

        $this->assertCount(1, $attachments);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'object' => 'attachment',
            'file' => self::$file->toArray(),
            'location' => Attachment::LOCATION_ATTACHMENT,
            'created_at' => self::$attachment->created_at,
            'updated_at' => self::$attachment->updated_at,
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
