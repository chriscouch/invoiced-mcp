<?php

namespace App\Tests\Sending\Sms;

use App\Sending\Sms\Models\SmsTemplate;
use App\Tests\AppTestCase;

class SmsTemplateTest extends AppTestCase
{
    private static SmsTemplate $template;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$template = new SmsTemplate();
        $this->assertTrue(self::$template->create([
            'name' => 'Test',
            'message' => 'Your balance of {{account_balance}} is now due - {{url}}',
        ]));

        $this->assertEquals(self::$company->id(), self::$template->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $templates = SmsTemplate::all();

        $this->assertCount(1, $templates);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$template->name = 'Test Template';
        $this->assertTrue(self::$template->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$template->id,
            'name' => 'Test Template',
            'message' => 'Your balance of {{account_balance}} is now due - {{url}}',
            'language' => null,
            'template_engine' => 'twig',
            'created_at' => self::$template->created_at,
            'updated_at' => self::$template->updated_at,
        ];

        $this->assertEquals($expected, self::$template->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$template->delete());
    }
}
