<?php

namespace App\Tests\CustomerPortal;

use App\AccountsReceivable\Libs\CustomerHierarchy;
use App\AccountsReceivable\Models\Customer;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\CustomerPortalFactory;
use App\Companies\Libs\CompanyRepository;
use App\Companies\Models\Company;
use App\PaymentProcessing\ValueObjects\PaymentFormSettings;
use App\Tests\AppTestCase;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class CustomerPortalTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::$company->custom_domain = 'billing.example.com';
        self::$company->saveOrFail();
        self::hasCustomer();
    }

    private function getFactory(): CustomerPortalFactory
    {
        return new CustomerPortalFactory(new CompanyRepository(self::getService('test.database')), new CustomerHierarchy(self::getService('test.database')));
    }

    private function getPortal(Company $company): CustomerPortal
    {
        return $this->getFactory()->make($company);
    }

    public function testMakeForUsername(): void
    {
        $factory = $this->getFactory();
        $this->assertNull($factory->makeForUsername(''));

        $this->assertNull($factory->makeForUsername('a'));

        $portal = $factory->makeForUsername(self::$company->username);

        $this->assertInstanceOf(CustomerPortal::class, $portal);
        $this->assertEquals(self::$company->id(), $portal->company()->id());
    }

    public function testGetForUsernameCustomDomain(): void
    {
        $factory = $this->getFactory();
        $this->assertNull($factory->makeForUsername('custom:'));

        $this->assertNull($factory->makeForUsername('custom:a'));

        $portal = $factory->makeForUsername('custom:billing.example.com');

        $this->assertInstanceOf(CustomerPortal::class, $portal);
        $this->assertEquals(self::$company->id(), $portal->company()->id());
    }

    public function testEnabled(): void
    {
        $portal = $this->getPortal(self::$company);
        $this->assertTrue($portal->enabled());

        self::$company->customer_portal_settings->enabled = false;
        $this->assertFalse($portal->enabled());
    }

    public function testGenerateLoginToken(): void
    {
        $portal = $this->getPortal(self::$company);

        $t = time();
        $token = $portal->generateLoginToken(self::$customer, 3600);

        $decrypted = (array) JWT::decode($token, new Key(self::$company->sso_key, 'HS256'));

        $expected = [
            'iat' => $t,
            'exp' => $t + 3600,
            'sub' => self::$customer->id(),
            'iss' => self::$company->id(),
        ];
        $this->assertEquals($expected, $decrypted);
    }

    public function testGetCustomerFromToken(): void
    {
        $portal = $this->getPortal(self::$company);

        $this->assertNull($portal->getCustomerFromToken('blah'));

        $token = $portal->generateLoginToken(self::$customer, 3600);

        $customer = $portal->getCustomerFromToken($token);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals(self::$customer->id(), $customer->id());
    }

    public function testRequiresAuthentication(): void
    {
        $portal = $this->getPortal(self::$company);
        $this->assertFalse($portal->requiresAuthentication());

        self::$company->customer_portal_settings->require_authentication = true;
        $this->assertTrue($portal->requiresAuthentication());
    }

    public function testShowCompanyName(): void
    {
        $portal = $this->getPortal(self::$company);
        $this->assertTrue($portal->showCompanyName());

        self::$company->customer_portal_settings->billing_portal_show_company_name = false;
        $this->assertFalse($portal->showCompanyName());
    }

    public function testInvoicePaymentToItemSelection(): void
    {
        $portal = $this->getPortal(self::$company);
        $this->assertTrue($portal->invoicePaymentToItemSelection());

        self::$company->customer_portal_settings->invoice_payment_to_item_selection = false;
        $this->assertFalse($portal->invoicePaymentToItemSelection());
    }

    public function testGetPaymentFormSettings(): void
    {
        $portal = $this->getPortal(self::$company);
        $expected = new PaymentFormSettings(self::$company, true, false, false, false);
        $this->assertEquals($expected, $portal->getPaymentFormSettings());
    }
}
