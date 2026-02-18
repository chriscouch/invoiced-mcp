<?php

namespace App\Tests\CustomerPortal\Models;

use App\CustomerPortal\Models\CspTrustedSite;
use App\Tests\AppTestCase;

class CspTrustedSiteTest extends AppTestCase
{
    private static CspTrustedSite $trustedSite;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testGetEnabledSources(): void
    {
        $site = new CspTrustedSite();
        $this->assertEquals([], $site->getEnabledSources());

        $site->connect = true;
        $site->font = true;
        $site->frame = true;
        $site->img = true;
        $site->media = true;
        $site->object = true;
        $site->script = true;
        $site->style = true;
        $this->assertEquals([
            'connect',
            'font',
            'frame',
            'img',
            'media',
            'object',
            'script',
            'style',
        ], $site->getEnabledSources());
    }

    public function testCreate(): void
    {
        self::$trustedSite = new CspTrustedSite();
        self::$trustedSite->url = 'https://example.com';
        self::$trustedSite->connect = true;
        self::$trustedSite->style = true;
        $this->assertTrue(self::$trustedSite->save());
        $this->assertEquals(self::$company->id(), self::$trustedSite->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$trustedSite->url = 'https://example2.com';
        $this->assertTrue(self::$trustedSite->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$trustedSite->id(),
            'connect' => true,
            'font' => null,
            'frame' => null,
            'img' => null,
            'media' => null,
            'object' => null,
            'script' => null,
            'style' => true,
            'url' => 'https://example2.com',
            'created_at' => self::$trustedSite->created_at,
            'updated_at' => self::$trustedSite->updated_at,
        ];

        $this->assertEquals($expected, self::$trustedSite->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$trustedSite->delete());
    }
}
