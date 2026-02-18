<?php

namespace App\Tests\CustomerPortal;

use App\CustomerPortal\Libs\CustomerPortalRateLimiter;
use App\Companies\Models\Company;
use App\Tests\AppTestCase;

class CustomerPortalRateLimiterTest extends AppTestCase
{
    private function getRateLimiter(): CustomerPortalRateLimiter
    {
        return new CustomerPortalRateLimiter(self::getService('test.redis'), 'invoicedtest', self::getService('test.encryption_key'));
    }

    public function testNeedsCaptchaVerification(): void
    {
        self::getService('test.redis')->del('invoicedtest:billing_portal_views.127.0.0.1');

        $company = new Company();
        $rateLimiter = $this->getRateLimiter();

        // record views on 10 different companies
        for ($i = 1; $i <= 10; ++$i) {
            $company->username = "test$i";

            // record 5 views on this company
            for ($j = 1; $j <= 5; ++$j) {
                if ($i <= 5) {
                    $this->assertFalse($rateLimiter->needsCaptchaVerification($company, '127.0.0.1'), "Should be able to access the customer portal after viewing $i unique portals, and this portal, {$company->username}, $j times");
                } else {
                    $this->assertTrue($rateLimiter->needsCaptchaVerification($company, '127.0.0.1'), "Should not be able to access the customer portal after viewing $i unique portals, and this portal, {$company->username}, $j times");
                }
            }
        }

        // mark it as verified
        $rateLimiter->verifiedCaptcha('127.0.0.1');

        $this->assertFalse($rateLimiter->needsCaptchaVerification($company, '127.0.0.1'));
    }

    public function testUrlParameter(): void
    {
        $rateLimiter = $this->getRateLimiter();
        $url = 'https://billing.example.com';
        $encrypted = $rateLimiter->encryptRedirectUrlParameter($url);
        $this->assertNotEquals($url, $encrypted);
        $decrypted = $rateLimiter->decryptRedirectUrlParameter($encrypted);
        $this->assertEquals($url, $decrypted);
    }
}
