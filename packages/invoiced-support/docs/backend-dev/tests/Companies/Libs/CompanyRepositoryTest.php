<?php

namespace App\Tests\Companies\Libs;

use App\Companies\Libs\CompanyRepository;
use App\Companies\Models\Company;
use App\Tests\AppTestCase;

class CompanyRepositoryTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::$company->custom_domain = 'billing.example.com';
        self::$company->save();
    }

    public function testGetCompanyForUsername(): void
    {
        $repository = new CompanyRepository(self::getService('test.database'));

        $this->assertNull($repository->getForUsername('x'));
        $this->assertNull($repository->getForUsername('blog'));
        $this->assertNull($repository->getForUsername('usernamenotfound'));

        /** @var Company $company */
        $company = $repository->getForUsername(self::$company->username);

        $this->assertEquals(self::$company->id(), $company->id());
    }

    public function testGetCompanyForCustomDomain(): void
    {
        $repository = new CompanyRepository(self::getService('test.database'));

        $this->assertNull($repository->getForCustomDomain('x'));
        $this->assertNull($repository->getForCustomDomain('blog'));
        $this->assertNull($repository->getForCustomDomain('billing.invoiced.com'));

        /** @var Company $company */
        $company = $repository->getForCustomDomain('billing.example.com');

        $this->assertEquals(self::$company->id(), $company->id());
    }
}
