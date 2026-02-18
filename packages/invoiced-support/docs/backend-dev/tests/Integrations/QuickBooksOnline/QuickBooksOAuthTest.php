<?php

namespace App\Tests\Integrations\QuickBooksOnline;

use App\Integrations\OAuth\OAuthAccessToken;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksOAuth;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\PaymentProcessing\Gateways\IntuitGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class QuickBooksOAuthTest extends AppTestCase
{
    private static string $jsonDir = __DIR__.'/json/quickbooks_connect';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getOAuth(): QuickBooksOAuth
    {
        return self::getService('test.oauth_integration_factory')->get('quickbooks_online');
    }

    public function testGetRedirectUrl(): void
    {
        $this->assertEquals('http://invoiced.localhost:1234/oauth/quickbooks_online/connect', $this->getOAuth()->getRedirectUrl());
    }

    public function testHandleAccessToken(): void
    {
        $oauth = $this->getOAuth();

        $accessTokenExpires = CarbonImmutable::now()->addHour();
        $refreshTokenExpires = CarbonImmutable::now()->addDays(100);
        $accessToken = new OAuthAccessToken('access_token', $accessTokenExpires, 'refresh_token', $refreshTokenExpires);

        $companyInfo = (string) file_get_contents(self::$jsonDir.'/quickbooks_connect_company_info.json');

        $qbo = Mockery::mock(QuickBooksApi::class);
        $qbo->shouldReceive('setAccount');
        $qbo->shouldReceive('getCompanyInfo')
            ->andReturn(json_decode($companyInfo)->CompanyInfo);
        $oauth->setQuickBooksApi($qbo);

        $request = new Request(['realmId' => 'qb_user_id']);

        $account = new QuickBooksAccount();

        $oauth->handleAccessToken($accessToken, $account, $request);

        $this->assertEquals('qb_user_id', $account->realm_id);
        $this->assertEquals('access_token', $account->access_token);
        $this->assertEquals('refresh_token', $account->refresh_token);
        $this->assertEquals($accessTokenExpires->getTimestamp(), $account->expires);
        $this->assertEquals($refreshTokenExpires->getTimestamp(), $account->refresh_token_expires);
        $account->persistOAuth();

        $accessToken = new OAuthAccessToken('access_token2', $accessTokenExpires, 'refresh_token', $refreshTokenExpires);

        $oauth->handleAccessToken($accessToken, $account, $request);

        $this->assertEquals('access_token2', $account->access_token);
    }

    /**
     * @depends testHandleAccessToken
     */
    public function testGetAccountForRealmId(): void
    {
        $oauth = $this->getOAuth();
        $account = $oauth->getAccountForRealmId('blah');
        $this->assertNull($account);

        $account = $oauth->getAccountForRealmId('qb_user_id');
        $this->assertInstanceOf(QuickBooksAccount::class, $account);
        $this->assertEquals(self::$company->id(), $account->tenant_id);
    }

    /**
     * @depends testHandleAccessToken
     */
    public function testHandleAccessTokenWithPayments(): void
    {
        $oauth = $this->getOAuth();

        $accessTokenExpires = CarbonImmutable::now()->addHour();
        $refreshTokenExpires = CarbonImmutable::now()->addDays(100);
        $accessToken = new OAuthAccessToken('access_token', $accessTokenExpires, 'refresh_token', $refreshTokenExpires);

        $companyInfo = (string) file_get_contents(self::$jsonDir.'/quickbooks_connect_company_info.json');

        $qbo = Mockery::mock(QuickBooksApi::class);
        $qbo->shouldReceive('setAccount');
        $qbo->shouldReceive('getCompanyInfo')
            ->andReturn(json_decode($companyInfo)->CompanyInfo);
        $oauth->setQuickBooksApi($qbo);

        $request = new Request(['realmId' => 'qb_user_id']);

        $account = QuickBooksAccount::one();

        // create an empty merchant account
        $merchantAccount = new MerchantAccount();
        $merchantAccount->name = 'test';
        $merchantAccount->top_up_threshold_num_of_days = 14;
        $merchantAccount->gateway_id = '0';
        $merchantAccount->gateway = IntuitGateway::ID;
        $merchantAccount->credentials = new \stdClass();
        $merchantAccount->saveOrFail();

        $oauth->handleAccessToken($accessToken, $account, $request);
        $account->persistOAuth();

        // should create a qbo account
        $this->assertEquals('qb_user_id', $account->realm_id);
        $this->assertEquals('access_token', $account->access_token);
        $this->assertEquals('refresh_token', $account->refresh_token);
        $this->assertEquals($accessTokenExpires->getTimestamp(), $account->expires);
        $this->assertEquals($refreshTokenExpires->getTimestamp(), $account->refresh_token_expires);

        // handle access token with payments and enabling payment methods
        $oauth->handleAccessToken($accessToken, $account);

        // should enable credit card and ach payments
        $cc = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
        $ach = PaymentMethod::instance(self::$company, PaymentMethod::ACH);
        $this->assertTrue($cc->enabled());
        $this->assertTrue($ach->enabled());
        $this->assertEquals('intuit', $cc->gateway);
        $this->assertEquals('intuit', $ach->gateway);
        $this->assertEquals($merchantAccount->id(), $cc->merchant_account_id);
        $this->assertEquals($merchantAccount->id(), $ach->merchant_account_id);

        // should update merchant account
        $this->assertEquals('intuit', $merchantAccount->refresh()->gateway);
        $this->assertEquals('qb_user_id', $merchantAccount->gateway_id);
        $expected = [
            'access_token' => 'access_token',
            'test_mode' => true,
        ];
        $this->assertEquals($expected, (array) $merchantAccount->credentials);
    }
}
