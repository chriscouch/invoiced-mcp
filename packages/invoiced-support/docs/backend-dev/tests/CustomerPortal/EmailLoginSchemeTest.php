<?php

namespace App\Tests\CustomerPortal;

use App\AccountsReceivable\Libs\CustomerHierarchy;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\LoginSchemes\EmailLoginScheme;
use App\Tests\AppTestCase;

class EmailLoginSchemeTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
    }

    private function getScheme(): EmailLoginScheme
    {
        return self::getService('test.email_login_scheme');
    }

    public function testRequestLogin(): void
    {
        $customerPortal = new CustomerPortal(self::$company, new CustomerHierarchy(self::getService('test.database')));
        $scheme = $this->getScheme();

        $this->assertTrue($scheme->requestLogin($customerPortal, 'sherlock@example.com', '127.0.0.1'));
        $this->assertTrue($scheme->requestLogin($customerPortal, 'test@example.com', '127.0.0.1'));

        // repeat requests should be debounced
        for ($i = 0; $i < 5; ++$i) {
            $this->assertFalse($scheme->requestLogin($customerPortal, 'sherlock@example.com', '127.0.0.1'));
        }
    }
}
