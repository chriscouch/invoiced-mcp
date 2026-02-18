<?php

namespace App\Tests\PaymentProcessing\Models;

use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;
use stdClass;

class MerchantAccountTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
    }

    public function testCreate(): void
    {
        $credentials = new stdClass();
        $credentials->access_token = 'tok_test';
        $credentials->refresh_token = 'tok_refresh';
        $credentials->publishable_key = 'tok_pub';

        self::$merchantAccount = new MerchantAccount();
        self::$merchantAccount->gateway = 'invoiced';
        self::$merchantAccount->gateway_id = 'user_test';
        self::$merchantAccount->name = 'Test Company';
        self::$merchantAccount->top_up_threshold_num_of_days = 14;
        self::$merchantAccount->credentials = $credentials;

        $this->assertTrue(self::$merchantAccount->save());

        // verify credentials encryption
        $this->assertEquals($credentials, self::$merchantAccount->credentials);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$merchantAccount->id(),
            'gateway' => 'invoiced',
            'gateway_id' => 'user_test',
            'name' => 'Test Company',
            'top_up_threshold_num_of_days' => 14,
            'settings' => new stdClass(),
            'created_at' => self::$merchantAccount->created_at,
            'updated_at' => self::$merchantAccount->updated_at,
        ];

        $this->assertEquals($expected, self::$merchantAccount->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        $credentials = self::$merchantAccount->credentials;
        $credentials->access_token = 'tok_test_2';
        self::$merchantAccount->credentials = $credentials;
        $this->assertTrue(self::$merchantAccount->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        // It should not be possible to delete a merchant account with an active card
        self::hasCard('invoiced', 'card_1234');
        $this->assertFalse(self::$merchantAccount->delete());
        self::$card->delete();

        // It should not be possible to delete a merchant account with an active bank account
        self::hasBankAccount('invoiced', 'ba_1234');
        $this->assertFalse(self::$merchantAccount->delete());
        self::$bankAccount->delete();

        // It should not be possible to delete a merchant account that is the default for a payment method
        self::acceptsCreditCards(StripeGateway::ID);
        $method = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
        $method->setMerchantAccount(self::$merchantAccount);
        $method->saveOrFail();
        $this->assertFalse(self::$merchantAccount->delete());
        $method->merchant_account_id = null;
        $method->saveOrFail();

        // When the merchant account is no longer used it should be possible to delete
        $this->assertTrue(self::$merchantAccount->delete());
        $this->assertTrue(self::$merchantAccount->persisted());
        $this->assertTrue(self::$merchantAccount->deleted);
    }
}
