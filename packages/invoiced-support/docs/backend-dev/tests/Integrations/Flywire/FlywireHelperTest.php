<?php

namespace App\Tests\Integrations\Flywire;

use App\Integrations\Flywire\FlywireHelper;
use App\PaymentProcessing\Models\MerchantAccount;
use App\Tests\AppTestCase;

class FlywireHelperTest extends AppTestCase
{
    public function testGetPortalCodes(): void
    {
        $merchantAccount = new MerchantAccount();
        $merchantAccount->credentials = (object) [
            'flywire_portal_codes' => [
                (object) ['id' => ''],
                (object) ['id' => 'ABC'],
                (object) ['id' => 'DEF, GHI'],
            ],
        ];
        $this->assertEquals(['ABC', 'DEF', 'GHI'], FlywireHelper::getPortalCodes($merchantAccount));
    }
}
