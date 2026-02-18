<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Libs\PaymentTermsFactory;
use App\Core\I18n\ValueObjects\Money;
use App\Tests\AppTestCase;

class PaymentTermsTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testGetTerms(): void
    {
        $terms = PaymentTermsFactory::get('TEST');
        $this->assertEquals('TEST', $terms->name);
        $this->assertTrue($terms->persisted());

        $terms2 = PaymentTermsFactory::get('TEST');
        $this->assertEquals($terms->id, $terms2->id);
    }

    public function testDueDateNoTerms(): void
    {
        $terms = PaymentTermsFactory::get('');
        $this->assertFalse($terms->hasDueDate());
        $this->assertNull($terms->getDueDate());
        $this->assertFalse($terms->persisted());
    }

    public function testDueDateDueOnReceipt(): void
    {
        $terms = PaymentTermsFactory::get('Due on Receipt');
        $this->assertTrue($terms->hasDueDate());
        $this->assertEqualsDate(date('Y-m-d'), (int) $terms->getDueDate());
    }

    public function testDueDateRandomTerms(): void
    {
        $terms = PaymentTermsFactory::get('YOU OWE ME MONEY');
        $this->assertFalse($terms->hasDueDate());
        $this->assertNull($terms->getDueDate());
    }

    public function testDueDateNetD(): void
    {
        $oneDay = 86400; // seconds

        // NET terms
        $terms = PaymentTermsFactory::get('net 30');
        $this->assertTrue($terms->hasDueDate());
        $this->assertEquals($oneDay * 30, $terms->getDueDate(0));

        $terms = PaymentTermsFactory::get(' NET-14');
        $this->assertTrue($terms->hasDueDate());
        $this->assertEquals($oneDay * 14, $terms->getDueDate(0));
    }

    public function testDueDateEarlyDiscount(): void
    {
        $oneDay = 86400; // seconds

        // NET terms
        $terms = PaymentTermsFactory::get('2% 10 net 30');
        $this->assertTrue($terms->hasDueDate());
        $this->assertEquals($oneDay * 30, $terms->getDueDate(0));

        $terms = PaymentTermsFactory::get('3% 5 NET-14 ');
        $this->assertTrue($terms->hasDueDate());
        $this->assertEquals($oneDay * 14, $terms->getDueDate(0));
    }

    public function testEarlyDiscount(): void
    {
        $amount = new Money('usd', 10000);
        $oneDay = 86400; // seconds

        $terms = PaymentTermsFactory::get('2% 10 net 30');
        $this->assertTrue($terms->hasEarlyDiscount());

        $expected = [
            'amount' => 2.0,
            'expires' => $oneDay * 10,
        ];
        $this->assertEquals($expected, $terms->getEarlyDiscount($amount, 0));
    }

    public function testEarlyDiscountNone(): void
    {
        $amount = new Money('usd', 10000);
        $terms = PaymentTermsFactory::get('net 30');
        $this->assertFalse($terms->hasEarlyDiscount());
        $this->assertNull($terms->getEarlyDiscount($amount, 0));
    }
}
