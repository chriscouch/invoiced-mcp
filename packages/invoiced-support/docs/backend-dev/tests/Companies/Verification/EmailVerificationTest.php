<?php

namespace App\Tests\Companies\Verification;

use App\Companies\Verification\EmailVerification;
use App\Tests\AppTestCase;

class EmailVerificationTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getVerification(): EmailVerification
    {
        return self::getService('test.company_email_verification');
    }

    public function testComplete(): void
    {
        $verification = $this->getVerification();

        $companyEmail = $verification->start(self::$company);

        $this->assertNull($companyEmail->verified_at);

        $verification->complete($companyEmail);

        $this->assertNotNull($companyEmail->verified_at);
    }
}
