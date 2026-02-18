<?php

namespace App\Tests\CustomerPortal;

use App\CustomerPortal\Models\CustomerPortalSettings;
use App\Companies\Models\Company;
use App\Core\Entitlements\Models\Product;
use App\Tests\AppTestCase;

class CustomerPortalProductTest extends AppTestCase
{
    public function testInstall(): void
    {
        $this->getService('test.tenant')->clear();

        $company = new Company();
        $company->name = 'TEST';
        $company->username = 'test'.time().rand();
        $company->country = 'US';
        $company->email = 'test@example.com';
        $company->creator_id = $this->getService('test.user_context')->get()->id();
        $company->saveOrFail();
        self::$company = $company;

        $this->getService('test.tenant')->set($company);

        self::getService('test.product_installer')->install(Product::where('name', 'Customer Portal')->one(), $company);

        // verify the settings were created
        $settings = CustomerPortalSettings::oneOrNull();
        $this->assertInstanceOf(CustomerPortalSettings::class, $settings);

        // installing a second time should not cause any error
        self::getService('test.product_installer')->install(Product::where('name', 'Customer Portal')->one(), $company);
    }
}
