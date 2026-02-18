<?php

namespace App\Tests\Core\Billing\Actions;

use App\Core\Billing\Models\OverageCharge;
use App\Tests\AppTestCase;

class BillOverageActionTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testBillCharge(): void
    {
        $biller = self::getService('test.overage_biller');

        $charge = new OverageCharge([
            'tenant_id' => self::$company->id,
            'month' => '201512',
            'dimension' => 'customer',
            'quantity' => 26,
            'price' => 1,
            'plan' => 'invoiced-basic-2019',
        ]);
        $this->assertTrue($biller->billCharge($charge));

        // verify result
        $this->assertTrue($charge->persisted());
        $this->assertTrue($charge->billed);

        // cannot re-bill for that month
        $this->assertFalse($biller->billCharge($charge));
    }
}
