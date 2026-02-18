<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Models\DocumentView;
use App\Core\Utils\ModelNormalizer;
use App\Tests\AppTestCase;

class DocumentViewTest extends AppTestCase
{
    private static DocumentView $view;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$view = new DocumentView();
        self::$view->document_type = 'invoice';
        self::$view->document_id = -1;
        self::$view->user_agent = 'Firefox';
        self::$view->ip = '127.0.0.1';
        $this->assertTrue(self::$view->save());
        $this->assertEquals(self::$company->id(), self::$view->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$view->timestamp = time();
        $this->assertTrue(self::$view->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$view->id,
            'object' => 'document_view',
            'timestamp' => self::$view->timestamp,
            'user_agent' => 'Firefox',
            'ip' => '127.0.0.1',
        ];

        $this->assertEquals($expected, self::$view->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEventObject(): void
    {
        $invoice = self::$view->document();
        $expected = [
            'id' => self::$view->id,
            'object' => 'document_view',
            'timestamp' => self::$view->timestamp,
            'user_agent' => 'Firefox',
            'ip' => '127.0.0.1',
            'invoice' => ModelNormalizer::toArray($invoice),
        ];

        $this->assertEquals($expected, self::$view->getEventObject());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$view->delete());
    }
}
