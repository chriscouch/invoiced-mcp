<?php

namespace App\Tests\Core\Entitlements;

use App\Core\Entitlements\FeatureManagement;
use App\Companies\Models\Company;
use App\Tests\AppTestCase;

class FeatureManagementTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getManager(): FeatureManagement
    {
        return new FeatureManagement(self::getService('test.database'));
    }

    public function testIsProtected(): void
    {
        $manager = $this->getManager();
        $this->assertTrue($manager->isProtected('autopay'));
        $this->assertTrue($manager->isProtected('cash_application'));
        $this->assertTrue($manager->isProtected('gl_accounts'));
        $this->assertFalse($manager->isProtected('grad.autopay'));
        $this->assertFalse($manager->isProtected('grad.my.feature'));
    }

    public function testEnableAndDisable(): void
    {
        $manager = $this->getManager();
        $n = Company::count();
        $this->assertFalse(self::$company->features->has('grad.test'));

        $manager->enableFeature('grad.test', $n);
        $this->assertTrue(self::$company->features->has('grad.test'));
        $this->assertEquals($n, $manager->getFeatureUsage('grad.test'));

        $manager->disableFeature('grad.test', $n);
        $this->assertFalse(self::$company->features->has('grad.test'));
        $this->assertEquals(0, $manager->getFeatureUsage('grad.test'));
    }
}
