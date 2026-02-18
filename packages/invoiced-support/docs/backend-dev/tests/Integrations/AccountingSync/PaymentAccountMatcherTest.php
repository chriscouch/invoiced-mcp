<?php

namespace App\Tests\Integrations\AccountingSync;

use App\Integrations\AccountingSync\ValueObjects\PaymentRoute;
use App\Integrations\AccountingSync\WriteSync\PaymentAccountMatcher;
use App\Tests\AppTestCase;

class PaymentAccountMatcherTest extends AppTestCase
{
    public function testCase1(): void
    {
        $rules = [
            ['currency' => 'cad', 'undeposited_funds' => true, 'account' => 'CAD-123'],
            ['currency' => 'usd', 'undeposited_funds' => true, 'account' => 'WF01'],
            ['currency' => '*', 'undeposited_funds' => false, 'account' => 'E1'],
        ];
        $matcher = new PaymentAccountMatcher($rules);

        $route = new PaymentRoute('cad', '', '');
        $result = $matcher->match($route);
        $this->assertTrue($result->isUndepositedFunds);
        $this->assertEquals('CAD-123', $result->account);

        $route = new PaymentRoute('eur', '', '');
        $result = $matcher->match($route);
        $this->assertFalse($result->isUndepositedFunds);
        $this->assertEquals('E1', $result->account);
    }

    public function testCase2(): void
    {
        $rules = [
            ['currency' => 'cad', 'method' => 'credit_card', 'undeposited_funds' => false, 'account' => 'Z123-12'],
            ['currency' => 'cad', 'method' => 'check', 'undeposited_funds' => false, 'account' => 'Z12311'],
            ['currency' => 'cad', 'method' => '*', 'undeposited_funds' => false, 'account' => 'E1'],
        ];
        $matcher = new PaymentAccountMatcher($rules);

        $route = new PaymentRoute('cad', 'credit_card', '');
        $result = $matcher->match($route);
        $this->assertFalse($result->isUndepositedFunds);
        $this->assertEquals('Z123-12', $result->account);

        $route = new PaymentRoute('cad', 'cash', '');
        $result = $matcher->match($route);
        $this->assertFalse($result->isUndepositedFunds);
        $this->assertEquals('E1', $result->account);
    }

    public function testCase3(): void
    {
        $rules = [
            ['currency' => 'cad', 'method' => 'credit_card', 'undeposited_funds' => false, 'account' => 'AA1'],
            ['currency' => 'cad', 'method' => 'check', 'undeposited_funds' => false, 'account' => 'Z12311'],
            ['currency' => 'cad', 'method' => '*', 'undeposited_funds' => false, 'account' => 'B1B2'],
        ];
        $matcher = new PaymentAccountMatcher($rules);

        $route = new PaymentRoute('cad', 'credit_card', '');
        $result = $matcher->match($route);
        $this->assertFalse($result->isUndepositedFunds);
        $this->assertEquals('AA1', $result->account);

        $route = new PaymentRoute('cad', 'cash', '');
        $result = $matcher->match($route);
        $this->assertFalse($result->isUndepositedFunds);
        $this->assertEquals('B1B2', $result->account);

        $route = new PaymentRoute('cad', 'check', '');
        $result = $matcher->match($route);
        $this->assertFalse($result->isUndepositedFunds);
        $this->assertEquals('Z12311', $result->account);
    }

    public function testCase4(): void
    {
        $rules = [
            ['currency' => 'cad', 'method' => 'credit_card', 'undeposited_funds' => false, 'account' => 'AA1'],
            ['currency' => 'cad', 'method' => 'check', 'undeposited_funds' => false, 'account' => 'Z12311'],
            ['currency' => 'usd', 'method' => 'credit_card', 'undeposited_funds' => false, 'account' => 'B1B2'],
            ['currency' => 'cad', 'method' => '*', 'undeposited_funds' => false, 'account' => '41134'],
        ];
        $matcher = new PaymentAccountMatcher($rules);

        $route = new PaymentRoute('cad', 'credit_card', '');
        $result = $matcher->match($route);
        $this->assertFalse($result->isUndepositedFunds);
        $this->assertEquals('AA1', $result->account);

        $route = new PaymentRoute('cad', 'cash', '');
        $result = $matcher->match($route);
        $this->assertFalse($result->isUndepositedFunds);
        $this->assertEquals('41134', $result->account);
    }

    public function testCase5(): void
    {
        $rules = [
            ['currency' => 'cad', 'method' => 'credit_card', 'undeposited_funds' => false, 'account' => 'AA1'],
            ['currency' => 'cad', 'method' => 'check', 'undeposited_funds' => false, 'account' => 'Z12311'],
            ['currency' => 'cad', 'method' => 'credit_card', 'undeposited_funds' => false, 'account' => 'B1B2'],
            ['currency' => 'cad', 'method' => '*', 'undeposited_funds' => false, 'account' => 'T!23'],
            ['currency' => 'cad', 'undeposited_funds' => false, 'account' => '41134'],
            ['undeposited_funds' => false, 'account' => 'YOLO'],
        ];
        $matcher = new PaymentAccountMatcher($rules);

        $route = new PaymentRoute('cad', 'write_transfer', '');
        $result = $matcher->match($route);
        $this->assertFalse($result->isUndepositedFunds);
        $this->assertEquals('T!23', $result->account);

        $route = new PaymentRoute('usd', 'credit_card', '');
        $result = $matcher->match($route);
        $this->assertFalse($result->isUndepositedFunds);
        $this->assertEquals('YOLO', $result->account);
    }

    public function testCase6(): void
    {
        $rules = [
            ['currency' => 'cad', 'method' => 'credit_card', 'undeposited_funds' => false, 'account' => 'AA1'],
            ['currency' => '*', 'method' => 'check', 'undeposited_funds' => false, 'account' => 'Z12311'],
            ['currency' => 'cad', 'method' => 'credit_card', 'undeposited_funds' => false, 'account' => 'B1B2'],
            ['currency' => 'cad', 'undeposited_funds' => false, 'account' => '41134'],
            ['currency' => 'cad', 'method' => '*', 'undeposited_funds' => false, 'account' => 'T!23'],
            ['method' => '*', 'undeposited_funds' => false, 'account' => 'YOLO'],
        ];
        $matcher = new PaymentAccountMatcher($rules);

        $route = new PaymentRoute('zye', 'check', '');
        $result = $matcher->match($route);
        $this->assertFalse($result->isUndepositedFunds);
        $this->assertEquals('Z12311', $result->account);
    }

    public function testMatchedRuleNoAccount(): void
    {
        $rules = [
            ['currency' => '*', 'method' => '*', 'account' => null],
        ];
        $matcher = new PaymentAccountMatcher($rules);

        $route = new PaymentRoute('cad', 'check', '');
        $result = $matcher->match($route);

        $this->assertFalse($result->isUndepositedFunds);
        $this->assertEquals('', $result->account);
    }

    public function testMatchedRulePaymentMerchantAccount(): void
    {
        $rules = [
            ['currency' => 'usd', 'method' => 'ach', 'merchant_account' => '', 'undeposited_funds' => false, 'account' => 'AA1'],
            ['currency' => 'usd', 'method' => 'ach', 'merchant_account' => '1234', 'undeposited_funds' => false, 'account' => 'AA2'],
            ['currency' => 'usd', 'method' => 'check', 'undeposited_funds' => false, 'account' => 'Z12311'],
            ['currency' => 'usd', 'method' => 'credit_card', 'merchant_account' => '*', 'undeposited_funds' => false, 'account' => 'B1B2'],
            ['currency' => 'usd', 'method' => '*', 'undeposited_funds' => false, 'account' => 'T!23'],
            ['currency' => 'usd', 'undeposited_funds' => false, 'account' => '41134'],
            ['undeposited_funds' => false, 'account' => 'YOLO'],
        ];
        $matcher = new PaymentAccountMatcher($rules);

        $routeAchNoMerchantAccount = new PaymentRoute('usd', 'ach', '');
        $routeAchWithMerchantAccount = new PaymentRoute('usd', 'ach', '1234');
        $routeAchWithMerchantAccount2 = new PaymentRoute('usd', 'ach', '12345');
        $routeCardNoMerchantAccount = new PaymentRoute('usd', 'credit_card', '');
        $routeCardWithMerchantAccount = new PaymentRoute('usd', 'credit_card', '1234');

        // test with an empty merchant account rule
        $result = $matcher->match($routeAchNoMerchantAccount);
        $this->assertFalse($result->isUndepositedFunds);
        $this->assertEquals('AA1', $result->account);

        // test with a specific merchant account rule
        $result = $matcher->match($routeAchWithMerchantAccount);
        $this->assertFalse($result->isUndepositedFunds);
        $this->assertEquals('AA2', $result->account);

        $result = $matcher->match($routeAchWithMerchantAccount2);
        $this->assertFalse($result->isUndepositedFunds);
        $this->assertEquals('T!23', $result->account);

        // test with an any merchant account rule
        $result = $matcher->match($routeCardNoMerchantAccount);
        $this->assertFalse($result->isUndepositedFunds);
        $this->assertEquals('B1B2', $result->account);
        $result = $matcher->match($routeCardWithMerchantAccount);
        $this->assertFalse($result->isUndepositedFunds);
        $this->assertEquals('B1B2', $result->account);
    }
}
