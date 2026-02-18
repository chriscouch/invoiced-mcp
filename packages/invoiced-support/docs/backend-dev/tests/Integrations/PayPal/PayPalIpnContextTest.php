<?php

namespace App\Tests\Integrations\PayPal;

use App\Integrations\Libs\IpnContext;
use App\Tests\AppTestCase;

class PayPalIpnContextTest extends AppTestCase
{
    public function testCompanyIdEncryption(): void
    {
        $id = 1096;
        $context = new IpnContext('secret');

        // test encryption
        $encrypted = $context->encode($id);

        $this->assertNotEquals($id, $encrypted);

        // decrypt
        $decrypted = $context->decode($encrypted);
        $this->assertEquals($id, $decrypted);
    }
}
