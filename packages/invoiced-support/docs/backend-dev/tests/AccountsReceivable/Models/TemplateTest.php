<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Models\Template;
use App\Tests\AppTestCase;

class TemplateTest extends AppTestCase
{
    private static Template $template;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$template = new Template();
        $this->assertTrue(self::$template->create(['name' => 'Test']));
        $this->assertEquals(self::$company->id(), self::$template->tenant_id);
        $this->assertEquals('Test', self::$template->name); /* @phpstan-ignore-line */
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$template->currency = 'eur'; /* @phpstan-ignore-line */
        $this->assertTrue(self::$template->save());
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $templates = Template::all();

        $this->assertCount(1, $templates);
        $this->assertEquals(self::$template->id(), $templates[0]->id());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$template->id, /* @phpstan-ignore-line */
            'name' => 'Test',
            'currency' => 'eur',
            'chase' => false,
            'payment_terms' => null,
            'items' => [],
            'discounts' => [],
            'taxes' => [],
            'notes' => null,
            'created_at' => self::$template->created_at,
            'updated_at' => self::$template->updated_at,
        ];

        $this->assertEquals($expected, self::$template->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testGet(): void
    {
        $this->assertEquals(self::$company->id(), self::$template->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$template->delete());
    }
}
