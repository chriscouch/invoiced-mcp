<?php

namespace App\Tests\Integrations\Lob;

use App\Integrations\OAuth\Traits\OauthGatewayTrait;
use App\PaymentProcessing\Models\MerchantAccount;
use App\Tests\AppTestCase;

class OauthGatewayTraitTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testGetMerchantAccount(): void
    {
        self::hasMerchantAccount('account1', 'test');
        $merchant1 = self::$merchantAccount;

        self::hasMerchantAccount('account1', '0');
        $merchant2 = self::$merchantAccount;

        $oauthGateway = new class() {
            use OauthGatewayTrait;

            public function get(string $gateway, string $gatewayId): MerchantAccount
            {
                return $this->getMerchantAccount($gateway, $gatewayId) ?? $this->makeAccount();
            }
        };
        $account = $oauthGateway->get('account1', 'test');
        $this->assertEquals($merchant1->id, $account->id);

        $merchant1->delete();
        $this->assertTrue($merchant1->isDeleted());

        $account = $oauthGateway->get('account1', 'test');
        $this->assertEquals($merchant1->id, $account->id);
        $merchant1->refresh();
        $this->assertFalse($merchant1->isDeleted());
    }
}
