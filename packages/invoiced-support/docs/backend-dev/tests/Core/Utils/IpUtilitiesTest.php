<?php

namespace App\Tests\Core\Utils;

use App\Core\Utils\IpUtilities;
use App\Tests\AppTestCase;

class IpUtilitiesTest extends AppTestCase
{
    public function testIpInCidr(): void
    {
        $this->assertTrue(IpUtilities::ipInCidr('139.162.185.243', '139.162.160.0', 19));
        $this->assertTrue(IpUtilities::ipInCidr('139.162.151.155', '139.162.128.0', 19));
        $this->assertTrue(IpUtilities::ipInCidr('139.162.157.131', '139.162.128.0', 19));
        $this->assertFalse(IpUtilities::ipIncidr('172.11.107.25', '52.205.190.0', 24));
    }
}
