<?php

namespace App\Tests\Themes;

use App\Tests\AppTestCase;
use App\Themes\Models\Template;

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
        self::$template->filename = 'pdf/invoice.twig';
        self::$template->content = 'test';
        $this->assertTrue(self::$template->save());

        $this->assertEquals(self::$company->id(), self::$template->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testCannotCreateNonUnique(): void
    {
        $template = new Template();
        $template->filename = 'pdf/invoice.twig';
        $template->content = 'test';
        $this->assertFalse($template->save());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$template->content = 'test2';
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
            'id' => self::$template->id(),
            'filename' => 'pdf/invoice.twig',
            'enabled' => true,
            'content' => 'test2',
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
