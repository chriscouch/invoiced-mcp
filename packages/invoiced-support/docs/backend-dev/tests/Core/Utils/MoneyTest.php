<?php

namespace App\Tests\Core\Utils;

use App\Core\I18n\Exception\MismatchedCurrencyException;
use App\Core\I18n\ValueObjects\Money;
use App\Tests\AppTestCase;
use InvalidArgumentException;

class MoneyTest extends AppTestCase
{
    public function testInvalidCurrencyEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $money = new Money('', 100);
    }

    public function testInvalidAmountString(): void
    {
        $money = new Money('usd', '1'); /* @phpstan-ignore-line */
        $this->assertEquals(1, $money->amount);
    }

    public function testInvalidAmountBool(): void
    {
        $money = new Money('usd', true); /* @phpstan-ignore-line */
        $this->assertEquals(1, $money->amount);
    }

    public function testGetters(): void
    {
        $money = new Money('USD', 100);
        $this->assertEquals('usd', $money->currency);
        $this->assertEquals(100, $money->amount);

        $money = new Money('jpy', 500);
        $this->assertEquals('jpy', $money->currency);
        $this->assertEquals(500, $money->amount);

        $money = new Money('zar', -1234);
        $this->assertEquals('zar', $money->currency);
        $this->assertEquals(-1234, $money->amount);
    }

    public function testFromDecimal(): void
    {
        $money = Money::fromDecimal('usd', 100.23);
        $this->assertInstanceOf(Money::class, $money);
        $this->assertEquals(10023, $money->amount);
        $this->assertEquals('usd', $money->currency);

        $money = Money::fromDecimal('jpy', 100);
        $this->assertInstanceOf(Money::class, $money);
        $this->assertEquals(100, $money->amount);
        $this->assertEquals('jpy', $money->currency);
    }

    public function testToString(): void
    {
        $money = new Money('usd', 100);
        $this->assertEquals('1 USD', (string) $money);

        $money = new Money('inr', 50);
        $this->assertEquals('0.5 INR', (string) $money);
    }

    public function testJsonEncode(): void
    {
        $money = new Money('usd', 100);
        $this->assertEquals('{"currency":"usd","amount":100}', json_encode($money));
    }

    public function testToDecimal(): void
    {
        $money = new Money('usd', 123);
        $this->assertEquals(1.23, $money->toDecimal());

        $money = new Money('jpy', 123);
        $this->assertEquals(123, $money->toDecimal());
    }

    public function testIsPositive(): void
    {
        $a = new Money('usd', 100);
        $b = new Money('usd', 0);
        $c = new Money('usd', -100);

        $this->assertTrue($a->isPositive());
        $this->assertFalse($b->isPositive());
        $this->assertFalse($c->isPositive());
    }

    public function testIsNegative(): void
    {
        $a = new Money('usd', 100);
        $b = new Money('usd', 0);
        $c = new Money('usd', -100);

        $this->assertFalse($a->isNegative());
        $this->assertFalse($b->isNegative());
        $this->assertTrue($c->isNegative());
    }

    public function testIsZero(): void
    {
        $a = new Money('usd', 100);
        $b = new Money('usd', 0);
        $c = new Money('usd', -100);

        $this->assertFalse($a->isZero());
        $this->assertTrue($b->isZero());
        $this->assertFalse($c->isZero());
    }

    public function testHasSameCurrency(): void
    {
        $a = new Money('usd', 100);
        $b = new Money('eur', 0);
        $c = new Money('usd', -100);

        $this->assertFalse($a->hasSameCurrency($b));
        $this->assertFalse($b->hasSameCurrency($a));

        $this->assertTrue($a->hasSameCurrency($c));
        $this->assertTrue($c->hasSameCurrency($a));
    }

    public function testEqualsMismatchedCurrency(): void
    {
        $this->expectException(MismatchedCurrencyException::class);

        $a = new Money('eur', 100);
        $b = new Money('gbp', 100);

        $a->equals($b);
    }

    public function testEquals(): void
    {
        $a = new Money('mxd', 123);
        $b = new Money('mxd', 124);

        $this->assertFalse($a->equals($b));
        $this->assertFalse($b->equals($a));

        $a = new Money('rub', 5);
        $b = new Money('rub', 5);

        $this->assertTrue($a->equals($b));
        $this->assertTrue($b->equals($a));
    }

    public function testGreaterThanMismatchedCurrency(): void
    {
        $this->expectException(MismatchedCurrencyException::class);

        $a = new Money('eur', 100);
        $b = new Money('gbp', 100);

        $a->greaterThan($b);
    }

    public function testGreaterThan(): void
    {
        $a = new Money('mxd', 123);
        $b = new Money('mxd', 124);

        $this->assertFalse($a->greaterThan($b));
        $this->assertTrue($b->greaterThan($a));

        $a = new Money('rub', 5);
        $b = new Money('rub', 5);

        $this->assertFalse($a->greaterThan($b));
        $this->assertFalse($b->greaterThan($a));
    }

    public function testGreaterThanOrEqualMismatchedCurrency(): void
    {
        $this->expectException(MismatchedCurrencyException::class);

        $a = new Money('eur', 100);
        $b = new Money('gbp', 100);

        $a->greaterThanOrEqual($b);
    }

    public function testGreaterThanOrEquals(): void
    {
        $a = new Money('mxd', 123);
        $b = new Money('mxd', 124);

        $this->assertFalse($a->greaterThanOrEqual($b));
        $this->assertTrue($b->greaterThanOrEqual($a));

        $a = new Money('rub', 5);
        $b = new Money('rub', 5);

        $this->assertTrue($a->greaterThanOrEqual($b));
        $this->assertTrue($b->greaterThanOrEqual($a));
    }

    public function testLessThanMismatchedCurrency(): void
    {
        $this->expectException(MismatchedCurrencyException::class);

        $a = new Money('eur', 100);
        $b = new Money('gbp', 100);

        $a->lessThan($b);
    }

    public function testLessThan(): void
    {
        $a = new Money('mxd', 123);
        $b = new Money('mxd', 124);

        $this->assertTrue($a->lessThan($b));
        $this->assertFalse($b->lessThan($a));

        $a = new Money('rub', 5);
        $b = new Money('rub', 5);

        $this->assertFalse($a->lessThan($b));
        $this->assertFalse($b->lessThan($a));
    }

    public function testLessThanOrEqualMismatchedCurrency(): void
    {
        $this->expectException(MismatchedCurrencyException::class);

        $a = new Money('eur', 100);
        $b = new Money('gbp', 100);

        $a->lessThanOrEqual($b);
    }

    public function testLessThanOrEqual(): void
    {
        $a = new Money('mxd', 123);
        $b = new Money('mxd', 124);

        $this->assertTrue($a->lessThanOrEqual($b));
        $this->assertFalse($b->lessThanOrEqual($a));

        $a = new Money('rub', 5);
        $b = new Money('rub', 5);

        $this->assertTrue($a->lessThanOrEqual($b));
        $this->assertTrue($b->lessThanOrEqual($a));
    }

    public function testCompare(): void
    {
        $a = new Money('usd', 1);
        $b = new Money('usd', 2);
        $c = new Money('usd', 1);

        $this->assertEquals(0, $a->compare($c));
        $this->assertEquals(-1, $a->compare($b));
        $this->assertEquals(1, $b->compare($a));
    }

    public function testSort(): void
    {
        $monies = [
            new Money('eur', 4),
            new Money('eur', 2),
            new Money('eur', 3),
            new Money('eur', 1),
        ];

        usort($monies, function ($a, $b) {
            return $a->compare($b);
        });

        $result = [];
        foreach ($monies as $m) {
            $result[] = $m->amount;
        }

        $this->assertEquals([1, 2, 3, 4], $result);
    }

    public function testAddMismatchedCurrency(): void
    {
        $this->expectException(MismatchedCurrencyException::class);

        $a = new Money('eur', 100);
        $b = new Money('gbp', 100);

        $a->add($b);
    }

    public function testAdd(): void
    {
        $a = new Money('nzd', 100);
        $b = new Money('nzd', 200);

        $c = $a->add($b);

        $this->assertInstanceOf(Money::class, $c);
        $this->assertEquals('nzd', $c->currency);
        $this->assertEquals(300, $c->amount);
    }

    public function testSubtractMismatchedCurrency(): void
    {
        $this->expectException(MismatchedCurrencyException::class);

        $a = new Money('eur', 100);
        $b = new Money('gbp', 100);

        $a->subtract($b);
    }

    public function testSubtract(): void
    {
        $a = new Money('cad', 100);
        $b = new Money('cad', 100);

        $c = $a->subtract($b);

        $this->assertInstanceOf(Money::class, $c);
        $this->assertEquals('cad', $c->currency);
        $this->assertEquals(0, $c->amount);
    }

    public function testMultiplyMismatchedCurrency(): void
    {
        $this->expectException(MismatchedCurrencyException::class);

        $a = new Money('eur', 100);
        $b = new Money('gbp', 100);

        $a->multiply($b);
    }

    public function testMultiply(): void
    {
        $a = new Money('cad', 100);
        $b = new Money('cad', 100);

        $c = $a->multiply($b);

        $this->assertInstanceOf(Money::class, $c);
        $this->assertEquals('cad', $c->currency);
        $this->assertEquals(10000, $c->amount);
    }

    public function testDivideMismatchedCurrency(): void
    {
        $this->expectException(MismatchedCurrencyException::class);

        $a = new Money('eur', 100);
        $b = new Money('gbp', 100);

        $a->divide($b);
    }

    public function testDivide(): void
    {
        $a = new Money('cad', 100);
        $b = new Money('cad', 100);

        $c = $a->divide($b);

        $this->assertInstanceOf(Money::class, $c);
        $this->assertEquals('cad', $c->currency);
        $this->assertEquals(1, $c->amount);
    }

    public function testNegated(): void
    {
        $money = new Money('aud', 100);

        $negated = $money->negated();
        $this->assertInstanceOf(Money::class, $negated);
        $this->assertEquals('aud', $negated->currency);
        $this->assertEquals(-100, $negated->amount);

        $negated = $negated->negated();
        $this->assertInstanceOf(Money::class, $negated);
        $this->assertEquals('aud', $negated->currency);
        $this->assertEquals(100, $negated->amount);
    }

    public function testAbs(): void
    {
        $money = new Money('aud', 100);

        $abs = $money->abs();
        $this->assertInstanceOf(Money::class, $abs);
        $this->assertEquals('aud', $abs->currency);
        $this->assertEquals(100, $abs->amount);

        $money = new Money('aud', -100);

        $abs = $money->abs();
        $this->assertInstanceOf(Money::class, $abs);
        $this->assertEquals('aud', $abs->currency);
        $this->assertEquals(100, $abs->amount);
    }

    public function testMaxMismatchedCurrency(): void
    {
        $this->expectException(MismatchedCurrencyException::class);

        $a = new Money('eur', 100);
        $b = new Money('gbp', 100);

        $a->max($b);
    }

    public function testMax(): void
    {
        $a = new Money('aud', 100);
        $b = new Money('aud', 100);

        $this->assertEquals($a, $a->max($b));
        $this->assertEquals($b, $b->max($b));

        $a = new Money('aud', 100);
        $b = new Money('aud', 101);

        $this->assertEquals($b, $a->max($b));
        $this->assertEquals($b, $b->max($a));
    }

    public function testMinMismatchedCurrency(): void
    {
        $this->expectException(MismatchedCurrencyException::class);

        $a = new Money('eur', 100);
        $b = new Money('gbp', 100);

        $a->min($b);
    }

    public function testMin(): void
    {
        $a = new Money('aud', 100);
        $b = new Money('aud', 100);

        $this->assertEquals($a, $a->min($b));
        $this->assertEquals($b, $b->min($b));

        $a = new Money('aud', 100);
        $b = new Money('aud', 101);

        $this->assertEquals($a, $a->min($b));
        $this->assertEquals($a, $b->min($a));
    }
}
