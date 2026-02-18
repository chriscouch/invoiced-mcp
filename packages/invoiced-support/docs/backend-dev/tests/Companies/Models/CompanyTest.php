<?php

namespace App\Tests\Companies\Models;

use App\AccountsPayable\Models\AccountsPayableSettings;
use App\AccountsReceivable\Models\AccountsReceivableSettings;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\CashApplicationSettings;
use App\Companies\EmailVariables\CompanyEmailVariables;
use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\Authentication\Models\User;
use App\Core\Billing\Enums\BillingSubscriptionStatus;
use App\Core\Entitlements\Models\Product;
use App\Core\RestApi\Models\ApiKey;
use App\Core\Utils\InfuseUtility as U;
use App\CustomerPortal\Models\CustomerPortalSettings;
use App\Notifications\Models\NotificationEventCompanySetting;
use App\Sending\Email\Models\EmailParticipant;
use App\SubscriptionBilling\Models\SubscriptionBillingSettings;
use App\Tests\AppTestCase;
use App\Themes\Models\Theme;
use Doctrine\DBAL\Connection;

class CompanyTest extends AppTestCase
{
    private static Company $defaultContext;
    private static Company $company2;
    private static Company $testCompany;
    private static Company $blankCompany;

    private static array $users = [];
    private static Connection $database;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$database = self::getService('test.database');
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        if (isset(self::$defaultContext)) {
            self::$defaultContext->delete();
        }

        if (isset(self::$company2)) {
            self::$company2->delete();
        }

        if (isset(self::$testCompany)) {
            self::$testCompany->delete();
        }

        if (isset(self::$blankCompany)) {
            self::$blankCompany->delete();
        }

        foreach (self::$users as $user) {
            $user->delete();
        }
    }

    protected function tearDown(): void
    {
        if (isset(self::$defaultContext)) {
            self::getService('test.tenant')->set(self::$defaultContext);
        }
    }

    public function testValidateUsername(): void
    {
        $username = 'testcorp8934';
        $this->assertTrue(Company::validateUsername($username));
        $username = 'acm';
        $this->assertFalse(Company::validateUsername($username));
        $username = 'help';
        $this->assertFalse(Company::validateUsername($username));
        $username = 'crafty_what';
        $this->assertFalse(Company::validateUsername($username));
        $username = 'crafty-what';
        $this->assertFalse(Company::validateUsername($username));
    }

    public function testValidateEmail(): void
    {
        $email = 'test@invoicedmail.comx';
        $this->assertTrue(Company::validateEmail($email));
        $email = 'test@invoicedmail.com';
        $this->assertFalse(Company::validateEmail($email));
    }

    public function testLogo(): void
    {
        $company = new Company();
        $this->assertNull($company->logo);

        $company->logo = 'test';
        $this->assertEquals('https://logos.invoiced.com/test', $company->logo);
    }

    public function testGetLocale(): void
    {
        $company = new Company();
        $company->country = 'US';
        $this->assertEquals('en_US', $company->getLocale());

        $company->country = 'FR';
        $this->assertEquals('en_FR', $company->getLocale());
    }

    public function testGetDisplayName(): void
    {
        $company = new Company();
        $company->name = 'Example, Inc.';
        $this->assertEquals('Example, Inc.', $company->getDisplayName());

        $company->nickname = 'Example';
        $this->assertEquals('Example', $company->getDisplayName());
    }

    public function testUrl(): void
    {
        $company = new Company();

        $this->assertNull($company->url);

        $company->username = 'test';
        $this->assertEquals('http://test.invoiced.localhost:1234', $company->url);

        $company->custom_domain = 'billing.example.com';
        $this->assertEquals('https://billing.example.com', $company->url);
    }

    public function testMoneyFormat(): void
    {
        $company = new Company();
        $company->country = 'US';
        $expected = ['use_symbol' => true, 'locale' => 'en_US'];
        $this->assertEquals($expected, $company->moneyFormat());

        $company = new Company();
        $company->language = 'hi';
        $company->country = 'IN';
        $expected = ['use_symbol' => true, 'locale' => 'hi_IN'];
        $this->assertEquals($expected, $company->moneyFormat());

        $company = new Company();
        $company->country = 'GB';
        $company->show_currency_code = true;
        $expected = ['use_symbol' => false, 'locale' => 'en_GB'];
        $this->assertEquals($expected, $company->moneyFormat());
    }

    public function testName(): void
    {
        $company = new Company();
        $company->name = 'TEST';

        $this->assertEquals('TEST', $company->name());
        $this->assertEquals('TEST', $company->name(true));
    }

    public function testAddress(): void
    {
        $company = new Company();
        $company->name = 'Sherlock Holmes';
        $company->address1 = '221B Baker St';
        $company->address2 = 'Unit 1';
        $company->city = 'London';
        $company->state = 'England';
        $company->country = 'GB';
        $company->postal_code = '1234';
        $company->type = 'company';
        $company->tax_id = '12345';
        $company->address_extra = 'Test';

        $this->assertEquals('Sherlock Holmes
221B Baker St
Unit 1
London
1234
VAT Reg No: 12345
Test', $company->address());

        $this->assertEquals('Sherlock Holmes
221B Baker St
Unit 1
London
1234
United Kingdom
VAT Reg No: 12345
Test', $company->address(true));
    }

    public function testSettings(): void
    {
        $company = new Company(['id' => 100]);
        $this->assertInstanceOf(AccountsReceivableSettings::class, $company->accounts_receivable_settings);
        $this->assertEquals(100, $company->accounts_receivable_settings->tenant_id);

        $this->assertInstanceOf(AccountsPayableSettings::class, $company->accounts_payable_settings);
        $this->assertEquals(100, $company->accounts_payable_settings->tenant_id);

        $this->assertInstanceOf(CashApplicationSettings::class, $company->cash_application_settings);
        $this->assertEquals(100, $company->cash_application_settings->tenant_id);

        $this->assertInstanceOf(CustomerPortalSettings::class, $company->customer_portal_settings);
        $this->assertEquals(100, $company->customer_portal_settings->tenant_id);

        $this->assertInstanceOf(SubscriptionBillingSettings::class, $company->subscription_billing_settings);
        $this->assertEquals(100, $company->subscription_billing_settings->tenant_id);
    }

    public function testGetEmailVariables(): void
    {
        $company = new Company();
        $this->assertInstanceOf(CompanyEmailVariables::class, $company->getEmailVariables());
    }

    public function testCreate(): void
    {
        self::$company = new Company();
        $this->assertTrue(self::$company->create([
            'name' => 'TEST',
            'email' => 'test@example.com',
            'address1' => '221B Baker St',
            'address2' => 'Unit 1',
            'city' => 'London',
            'state' => 'England',
            'postal_code' => '1234',
            'address_extra' => 'Test',
            'username' => 'test'.time(),
            'country' => 'GB',
            'tax_id' => 12345,
            'creator_id' => self::getService('test.user_context')->get()->id(),
            'trial_started' => time(),
            'trial_ends' => strtotime('+14 days'),
        ]));
        self::$defaultContext = self::$company;

        $this->assertEquals(63, strlen(self::$company->sso_key));
        $this->assertEquals(24, strlen(self::$company->identifier));
        $this->assertEquals(self::$company->sso_key, self::$company->sso_key_enc);
        $this->assertEquals('gbp', self::$company->currency);

        self::getService('test.tenant')->set(self::$company);

        // verify administrator role was created
        $role = Role::where('id', Role::ADMINISTRATOR)->oneOrNull();
        $this->assertInstanceOf(Role::class, $role);

        // verify creator was added as an administrator
        $userId = self::getService('test.user_context')->get()->id();
        $member = Member::where('user_id', $userId)->oneOrNull();
        $this->assertInstanceOf(Member::class, $member);
        $this->assertEquals(Role::ADMINISTRATOR, $member->role);

        // verify notification settings were created
        $events = NotificationEventCompanySetting::execute();
        $this->assertCount(23, $events);
        $result = array_map(fn (NotificationEventCompanySetting $item) => (int) $item->notification_type, $events);
        sort($result);
        $this->assertEquals([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24], $result);
        $this->assertEquals(23, array_sum(array_map(fn (NotificationEventCompanySetting $item) => $item->frequency, $events)));

        // verify email participant was created
        $participant = EmailParticipant::where('tenant_id', self::$company->id)
            ->where('email_address', 'test@example.com')
            ->one();
        $this->assertEquals('TEST', $participant->name);

        self::getService('test.product_installer')->install(Product::where('name', 'Accounts Receivable Free')->one(), self::$company);

        self::getService('test.tenant')->clear();

        self::$company2 = new Company();
        $this->assertTrue(self::$company2->create([
            'name' => 'TEST 2',
            'username' => 'test2'.time(),
            'created_at' => U::unixToDb(time() - (86400 * 31)),
            'creator_id' => self::getService('test.user_context')->get()->id(),
        ]));

        self::getService('test.tenant')->set(self::$company2);

        $member = Member::where('user_id', self::getService('test.user_context')->get()->id())->oneOrNull();
        $this->assertInstanceOf(Member::class, $member);

        $this->assertNotEquals(self::$company->sso_key, self::$company2->sso_key);
        $this->assertNotEquals(self::$company->sso_key_enc, self::$company2->sso_key_enc);
        $this->assertNotEquals(self::$company->identifier, self::$company2->identifier);
        $this->assertNull(self::$company2->country);
        $this->assertEquals('', self::$company2->currency);

        self::getService('test.tenant')->set(self::$company);
    }

    public function testCreateBlankCompany(): void
    {
        self::getService('test.tenant')->clear();

        self::$blankCompany = new Company();
        self::$blankCompany->username = 'ausername';
        $this->assertTrue(self::$blankCompany->save());
    }

    public function testCreateTestMode(): void
    {
        self::getService('test.tenant')->clear();

        self::$testCompany = new Company();
        self::$testCompany->name = 'TEST MODE';
        self::$testCompany->username = 'testmode'.time();
        self::$testCompany->test_mode = true;
        self::$testCompany->email = 'test@example.com';
        $this->assertTrue(self::$testCompany->save());
        self::getService('test.product_installer')->install(Product::where('name', 'Accounts Receivable Free')->one(), self::$testCompany);

        $this->assertTrue(self::$testCompany->test_mode);
        $this->assertNull(self::$testCompany->trial_ends);
        $this->assertEquals(BillingSubscriptionStatus::Active, self::$testCompany->billingStatus());
        $this->assertEquals('null', self::$testCompany->accounts_receivable_settings->email_provider);
    }

    public function testCreateUsernameNotUnique(): void
    {
        $company = new Company();
        $errors = $company->getErrors();
        $this->assertFalse($company->create([
            'name' => 'TEST 3',
            'username' => self::$company->username, ]));
        $this->assertCount(1, $errors);
        $this->assertEquals('The Username you chose has already been taken. Please try a different Username.', $errors->all()[0]);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$company->type = 'person';
        $this->assertTrue(self::$company->save());

        self::$company->username = self::$company->username;
        $this->assertTrue(self::$company->save());
    }

    /**
     * @depends testEdit
     */
    public function testEditUsernameNotUnique(): void
    {
        $errors = self::$company2->getErrors();

        self::$company2->username = self::$company->username;
        $this->assertFalse(self::$company2->save());

        $this->assertCount(1, $errors);
        $this->assertEquals('The Username you chose has already been taken. Please try a different Username.', $errors->all()[0]);
    }

    /**
     * @depends testCreate
     */
    public function testActivateTrial(): void
    {
        $this->assertTrue(self::$company->save());
        $this->assertGreaterThan(0, self::$company->trial_started);
        $this->assertGreaterThan(strtotime('+14 days'), self::$company->trial_ends + 3); // add a buffer to allow for slow test runners
        $this->assertEquals(BillingSubscriptionStatus::Trialing, self::$company->billingStatus());
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $companies = Company::all();

        $this->assertCount(4, $companies);
        $this->assertEquals(self::$company->id(), $companies[0]->id());
        $this->assertEquals(self::$company2->id(), $companies[1]->id());
        $this->assertEquals(self::$blankCompany->id(), $companies[2]->id());
        $this->assertEquals(self::$testCompany->id(), $companies[3]->id());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$company->id(),
            'name' => 'TEST',
            'nickname' => null,
            'username' => self::$company->username,
            'type' => 'person',
            'creator_id' => self::getService('test.user_context')->get()->id(),
            'test_mode' => false,
            'email' => 'test@example.com',
            'industry' => null,
            'address1' => '221B Baker St',
            'address2' => 'Unit 1',
            'city' => 'London',
            'state' => 'England',
            'postal_code' => '1234',
            'country' => 'GB',
            'tax_id' => '12345',
            'address_extra' => 'Test',
            'logo' => false,
            'highlight_color' => '#303030',
            'currency' => 'gbp',
            'language' => 'en',
            'show_currency_code' => false,
            'date_format' => 'M j, Y',
            'time_zone' => '',
            'canceled' => false,
            'url' => self::$company->url,
            'created_at' => self::$company->created_at,
            'updated_at' => self::$company->updated_at,
            'trial_ends' => self::$company->trial_ends,
            'phone' => null,
            'website' => null,
        ];

        $this->assertEquals($expected, self::$company->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testDashboardApiKey(): void
    {
        $source = ApiKey::SOURCE_DASHBOARD;

        $genericSecret = self::$company->getProtectedApiKey($source)->secret;

        $user = self::getService('test.user_context')->get();
        $expires = strtotime('+30 minutes');
        $dashboardKey = self::$company->getProtectedApiKey($source, $user, $expires)->secret;
        $this->assertNotEquals($genericSecret, $dashboardKey);

        $key = self::$company->getProtectedApiKey($source, $user);
        $this->assertEquals($key->secret, $dashboardKey);
        $this->assertEquals($expires, $key->expires);

        $this->assertEquals($dashboardKey, self::$company->dashboard_api_key); /* @phpstan-ignore-line */
    }

    /**
     * @depends testCreate
     */
    public function testCurrencies(): void
    {
        self::hasCustomer();
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->currency = 'jpy';
        $this->assertTrue($invoice->save());

        // new currencies are not returned unless multi-currency is enabled
        $this->assertEquals(['gbp'], self::$company->getCurrencies());

        self::$company->features->enable('multi_currency');

        for ($i = 0; $i < 3; ++$i) {
            $this->assertEquals(['gbp', 'jpy'], self::$company->getCurrencies());
        }

        $previousCurrency = self::$company->currency;
        self::$company->currency = 'usd';
        $this->assertFalse(self::$company->save());
        $this->assertEquals('Cannot change currency because one or more transactions already exist.', (string) self::$company->getErrors());

        self::$company->currency = $previousCurrency;
        $this->assertTrue(self::$company->save());
    }

    /**
     * @depends testCreate
     */
    public function testBilling(): void
    {
        $expected = [
            'status' => 'trialing',
            'quota' => [
                'no_invoices' => null,
                'no_customers' => null,
                'no_users' => null,
            ],
            'provider' => 'null',
        ];
        $this->assertEquals($expected, self::$company->billing);
    }

    /**
     * @depends testCreate
     */
    public function testIsMember(): void
    {
        $company = new Company();
        $user = new User();
        $this->assertFalse($company->isMember($user));

        $this->assertTrue(self::$company->isMember(self::getService('test.user_context')->get()));

        // hopefully a user with this id does not exist
        $user = new User(['id' => 1234567890]);

        $this->assertFalse(self::$company->isMember($user));
    }

    public function testDefaultThemeNoDefault(): void
    {
        $company = new Company();

        $theme = $company->defaultTheme();
        $this->assertInstanceOf(Theme::class, $theme);
        $this->assertEquals(null, $theme->id);
    }

    /**
     * @depends testCreate
     */
    public function testDefaultThemeWithDefault(): void
    {
        $newTheme = new Theme();
        $newTheme->id = 'test';
        $newTheme->name = 'Test';
        $newTheme->saveOrFail();

        self::$company->accounts_receivable_settings->default_theme_id = 'test';
        self::$company->accounts_receivable_settings->saveOrFail();

        // need to create new company object bc old theme is cached
        $company = new Company(['id' => self::$company->id()]);
        $theme = $company->defaultTheme();
        $this->assertInstanceOf(Theme::class, $theme);
        $this->assertEquals($newTheme->id(), $theme->id());
    }

    /**
     * @depends testCreate
     */
    public function testGetProtectedApiKey(): void
    {
        $key = self::$company->getProtectedApiKey(ApiKey::SOURCE_DASHBOARD);
        $this->assertInstanceOf(ApiKey::class, $key);
        $this->assertTrue($key->protected);
        $this->assertEquals(ApiKey::SOURCE_DASHBOARD, $key->source);
        $this->assertNull($key->expires);

        for ($i = 1; $i <= 5; ++$i) {
            $key2 = self::$company->getProtectedApiKey(ApiKey::SOURCE_DASHBOARD);
            $this->assertInstanceOf(ApiKey::class, $key2);
            $this->assertTrue($key2->protected);
            $this->assertEquals($key->id(), $key2->id());
        }

        $company = new Company(['id' => self::$company->id()]);
        $this->assertEquals($key->id(), $company->getProtectedApiKey(ApiKey::SOURCE_DASHBOARD)->id());

        // each user should get their own API key
        for ($i = 1; $i <= 5; ++$i) {
            $user = new User();
            $user->first_name = 'Test';
            $user->password = ['GdZMwwCiW[JTM89', 'GdZMwwCiW[JTM89']; /* @phpstan-ignore-line */
            $user->email = 'test'.$i.'@example.com';
            $user->ip = '127.0.0.1';
            $user->saveOrFail();
            self::$users[] = $user;

            $key2 = self::$company->getProtectedApiKey(ApiKey::SOURCE_DASHBOARD, $user);
            $this->assertInstanceOf(ApiKey::class, $key2);
            $this->assertTrue($key2->protected);
            $this->assertNotEquals($key->id(), $key2->id());
        }

        // create a key with an expiry date
        $t = strtotime('+30 minutes');
        $key = self::$company->getProtectedApiKey('expiring_key', null, $t);
        $this->assertInstanceOf(ApiKey::class, $key);
        $this->assertTrue($key->protected);
        $this->assertEquals($t, $key->expires);
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        self::hasCustomer();
        self::hasInvoice();
        self::hasTransaction();
        self::hasPayment();
        $tenantid = self::$company->id;

        $this->assertEquals(1, self::$database->fetchOne("select 1 from Invoices where tenant_id = $tenantid"));
        $this->assertEquals(1, self::$database->fetchOne("select 1 from Customers where tenant_id = $tenantid"));
        $this->assertEquals(1, self::$database->fetchOne("select 1 from Payments where tenant_id = $tenantid"));
        $this->assertEquals(1, self::$database->fetchOne("select 1 from Transactions where tenant_id = $tenantid"));
        $this->assertTrue(self::$company->delete());
        $this->assertEquals(0, self::$database->fetchOne("select 1 from Invoices where tenant_id = $tenantid"));
        $this->assertEquals(0, self::$database->fetchOne("select 1 from Customers where tenant_id = $tenantid"));
        $this->assertEquals(0, self::$database->fetchOne("select 1 from Payments where tenant_id = $tenantid"));
        $this->assertEquals(0, self::$database->fetchOne("select 1 from Transactions where tenant_id = $tenantid"));
    }
}
