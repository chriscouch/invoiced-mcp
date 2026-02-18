<?php

namespace App\Tests\Core\Entitlements;

use App\Companies\Models\Company;
use App\Core\Entitlements\FeatureCollection;
use App\Core\Entitlements\Models\InstalledProduct;
use App\Core\Entitlements\Models\Product;
use App\PaymentProcessing\Gateways\TestGateway;
use App\Tests\AppTestCase;

class FeaturesTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function reset(): void
    {
        // disable all installed products and features
        self::getService('test.database')->executeStatement('TRUNCATE Features');
        self::getService('test.database')->executeStatement('TRUNCATE InstalledProducts');
        FeatureCollection::clearCache();
    }

    public function testOn(): void
    {
        $this->reset();
        $this->assertFalse(self::$company->features->has('card_payments'));
        $this->assertFalse(self::$company->features->has('intacct'));
    }

    public function testOnWithOverride(): void
    {
        $this->reset();
        $this->assertFalse(self::$company->features->has('netsuite'));

        self::$company->features->enable('netsuite');
        $this->assertTrue(self::$company->features->has('netsuite'));

        self::$company->features->disable('netsuite');
        $this->assertFalse(self::$company->features->has('netsuite'));
    }

    public function testRemoveOverride(): void
    {
        self::$company->features->enable('roles');
        self::$company->features->disable('roles');
        $this->assertFalse(self::$company->features->has('roles'));

        self::$company->features->enable('roles');
        self::$company->features->remove('roles');
        $this->assertFalse(self::$company->features->has('roles'));
    }

    public function testAllNoProducts(): void
    {
        $this->reset();
        $company = new Company();

        $expected = [];
        $this->assertEquals($expected, $company->features->all());
    }

    public function testAllAccountsPayableFreeProduct(): void
    {
        $expected = [
            'accounts_payable',
            'internationalization',
            'network',
            'notifications_v2_individual',
        ];
        $this->testProduct('Accounts Payable Free', $expected);
    }

    public function testAllAccountsReceivableFreeProduct(): void
    {
        $expected = [
            'accounts_receivable',
            'ach',
            'api',
            'billing_portal',
            'card_payments',
            'cash_application',
            'direct_debit',
            'email_sending',
            'estimates_v2',
            'internationalization',
            'network',
            'notifications_v2_individual',
            'payment_links',
        ];
        $this->testProduct('Accounts Receivable Free', $expected);
    }

    public function testAllAdvancedAccountPayableProduct(): void
    {
        $expected = [
            'accounts_payable',
            'api',
            'approval_workflow',
            'audit_log',
            'email_sending',
            'inboxes',
            'internationalization',
            'live_chat',
            'network',
            'network_invitations',
            'new_trials',
            'notifications_v2_individual',
            'phone_support',
            'roles',
        ];
        $this->testProduct('Advanced Accounts Payable', $expected);
    }

    public function testAllCashApplicationProduct(): void
    {
        $expected = [
            'cash_application',
            'cash_match',
        ];
        $this->testProduct('Cash Application', $expected);
    }

    public function testAllAdvancedAccountsReceivableProduct(): void
    {
        $expected = [
            'accounting_sync',
            'accounts_receivable',
            'ach',
            'api',
            'ar_inbox',
            'audit_log',
            'billing_portal',
            // NOTE: autopay is not here because an AutoPay payment method is not enabled
            'card_payments',
            'cash_application',
            'cash_match',
            'custom_templates',
            'direct_debit',
            'email_sending',
            'email_whitelabel',
            'estimates',
            'estimates_v2',
            'forecasting',
            'inboxes',
            'internationalization',
            'invoice_chasing',
            'letters',
            'live_chat',
            'network',
            'network_invitations',
            'new_trials',
            'notifications_v2_individual',
            'payment_links',
            'payment_plans',
            'phone_support',
            'roles',
            'smart_chasing',
            'sms',
            'unlimited_recipients',
        ];
        $this->testProduct('Advanced Accounts Receivable', $expected);
    }

    public function testAllCustomerPortalProduct(): void
    {
        $expected = [
            'billing_portal',
            'custom_domain',
        ];
        $this->testProduct('Customer Portal', $expected);
    }

    public function testAllIntacctProduct(): void
    {
        $expected = [
            'accounting_sync',
            'intacct',
        ];
        $this->testProduct('Intacct Integration', $expected);
    }

    public function testAllNetSuiteProduct(): void
    {
        $expected = [
            'accounting_sync',
            'netsuite',
        ];
        $this->testProduct('NetSuite Integration', $expected);
    }

    public function testAllReportingProduct(): void
    {
        $expected = [
            'forecasting',
            'report_builder',
        ];
        $this->testProduct('Advanced Reporting', $expected);
    }

    public function testAllSalesforceProduct(): void
    {
        $expected = [
            'salesforce',
        ];
        $this->testProduct('Salesforce Integration', $expected);
    }

    public function testAllSubscriptionBillingProduct(): void
    {
        $expected = [
            'accounts_receivable',
            'ach',
            'api',
            'audit_log',
            'billing_portal',
            // NOTE: autopay is not here because an AutoPay payment method is not enabled
            'card_payments',
            'consolidated_invoicing',
            'custom_templates',
            'direct_debit',
            'email_sending',
            'email_whitelabel',
            'estimates',
            'estimates_v2',
            'internationalization',
            'live_chat',
            'metered_billing',
            'network',
            'network_invitations',
            'new_trials',
            'notifications_v2_individual',
            'payment_links',
            'phone_support',
            'roles',
            'subscription_billing',
            'subscriptions',
            'unlimited_recipients',
        ];
        $this->testProduct('Subscription Billing', $expected);
    }

    public function testAutoPayFeature(): void
    {
        $this->reset();
        self::$company->features->enable('autopay');
        self::$company->features->enable('card_payments');

        $this->assertTrue(self::$company->features->has('autopay'));
        $this->assertFalse(in_array('autopay', self::$company->features->all()));

        // enable CC payments
        self::acceptsCreditCards(TestGateway::ID);

        $this->assertTrue(self::$company->features->has('autopay'));
        $this->assertTrue(in_array('autopay', self::$company->features->all()));
    }

    private function enableProduct(string $name): void
    {
        $product = Product::where('name', $name)->one();
        self::$company->features->enableProduct($product);
    }

    private function testProduct(string $name, array $expectedFeatures): void
    {
        $this->reset();
        $this->enableProduct($name);

        $this->assertEquals([
            $name,
        ], self::$company->features->allProducts());

        $this->assertEquals($expectedFeatures, self::$company->features->all());

        // Should create an installed product
        $installedProduct = InstalledProduct::join(Product::class, 'product_id', 'id')
            ->where('Products.name', $name)
            ->oneOrNull();
        $this->assertInstanceOf(InstalledProduct::class, $installedProduct);
    }
}
