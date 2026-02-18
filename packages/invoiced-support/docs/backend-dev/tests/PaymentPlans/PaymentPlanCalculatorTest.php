<?php

namespace App\Test\PaymentPlans;

use App\PaymentPlans\Exception\PaymentPlanCalculatorException;
use App\PaymentPlans\Libs\PaymentPlanCalculator;
use App\Tests\AppTestCase;

class PaymentPlanCalculatorTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getCalculator(): PaymentPlanCalculator
    {
        $calculator = new PaymentPlanCalculator(self::$company);

        return $calculator;
    }

    public function testBuildSpacingAndAmount(): void
    {
        $calculator = $this->getCalculator();

        $constraints = [
            'installment_amount' => 15,
            'installment_spacing' => '1 week',
        ];

        $expected = [[
            'date' => mktime(0, 0, 0, 10, 12, 2016),
            'amount' => 15,
        ], [
            'date' => mktime(0, 0, 0, 10, 19, 2016),
            'amount' => 15,
        ], [
            'date' => mktime(0, 0, 0, 10, 26, 2016),
            'amount' => 15,
        ], [
            'date' => mktime(0, 0, 0, 11, 2, 2016),
            'amount' => 5,
        ]];

        $schedule = $calculator->build((int) mktime(1, 2, 43, 10, 12, 2016), 'usd', 50, $constraints);
        $this->assertEquals($schedule, $expected);
    }

    public function testBuildAmountAndEndDate(): void
    {
        $calculator = $this->getCalculator();

        $constraints = [
            'installment_amount' => 15,
            'end_date' => mktime(1, 3, 42, 11, 3, 2016),
        ];

        $expected = [[
            'date' => mktime(0, 0, 0, 10, 12, 2016),
            'amount' => 15,
        ], [
            'date' => mktime(0, 0, 0, 10, 19, 2016),
            'amount' => 15,
        ], [
            'date' => mktime(0, 0, 0, 10, 26, 2016),
            'amount' => 15,
        ], [
            'date' => mktime(0, 0, 0, 11, 3, 2016),
            'amount' => 5,
        ]];

        $schedule = $calculator->build((int) mktime(1, 2, 43, 10, 12, 2016), 'usd', 50, $constraints);
        $this->assertEquals($schedule, $expected);
    }

    public function testBuildNumInstallmentsAndSpacing(): void
    {
        $calculator = $this->getCalculator();

        $constraints = [
            'num_installments' => 4,
            'installment_spacing' => '1 week',
        ];

        $expected = [[
            'date' => mktime(0, 0, 0, 10, 12, 2016),
            'amount' => 25,
        ], [
            'date' => mktime(0, 0, 0, 10, 19, 2016),
            'amount' => 25,
        ], [
            'date' => mktime(0, 0, 0, 10, 26, 2016),
            'amount' => 25,
        ], [
            'date' => mktime(0, 0, 0, 11, 2, 2016),
            'amount' => 25,
        ]];

        $schedule = $calculator->build((int) mktime(1, 2, 43, 10, 12, 2016), 'usd', 100, $constraints);
        $this->assertEquals($schedule, $expected);
    }

    public function testBuildNumInstallmentsAndEndDate(): void
    {
        $calculator = $this->getCalculator();

        $constraints = [
            'end_date' => mktime(1, 2, 43, 11, 3, 2016),
            'num_installments' => 4,
        ];

        $expected = [[
            'date' => mktime(0, 0, 0, 10, 12, 2016),
            'amount' => 15,
        ], [
            'date' => mktime(0, 0, 0, 10, 19, 2016),
            'amount' => 15,
        ], [
            'date' => mktime(0, 0, 0, 10, 26, 2016),
            'amount' => 15,
        ], [
            'date' => mktime(0, 0, 0, 11, 3, 2016),
            'amount' => 15.01,
        ]];

        $schedule = $calculator->build((int) mktime(1, 2, 43, 10, 12, 2016), 'usd', 60.01, $constraints);
        $this->assertEquals($expected, $schedule);
    }

    public function testBuildEndDateAndSpacing(): void
    {
        $calculator = $this->getCalculator();

        $constraints = [
            'installment_spacing' => '1 week',
            'end_date' => mktime(1, 3, 42, 11, 2, 2016),
        ];

        $expected = [[
            'date' => mktime(0, 0, 0, 10, 12, 2016),
            'amount' => 25,
        ], [
            'date' => mktime(0, 0, 0, 10, 19, 2016),
            'amount' => 25,
        ], [
            'date' => mktime(0, 0, 0, 10, 26, 2016),
            'amount' => 25,
        ], [
            'date' => mktime(0, 0, 0, 11, 2, 2016),
            'amount' => 25,
        ]];

        $schedule = $calculator->build((int) mktime(1, 2, 43, 10, 12, 2016), 'usd', 100, $constraints);
        $this->assertEquals($expected, $schedule);
    }

    public function testVerifyNoInstallments(): void
    {
        $this->expectException(PaymentPlanCalculatorException::class);
        $this->expectExceptionMessage('The schedule does not have any installments.');

        $calculator = $this->getCalculator();

        $schedule = [];

        $constraints = [];

        $calculator->verify('usd', $schedule, $constraints);
    }

    public function testVerifyNoConstraints(): void
    {
        $calculator = $this->getCalculator();

        $schedule = [['amount' => 10]];

        $constraints = [];

        $this->assertTrue($calculator->verify('usd', $schedule, $constraints));
    }

    public function testVerifyWrongNumInstallments(): void
    {
        $this->expectException(PaymentPlanCalculatorException::class);
        $this->expectExceptionMessage('The schedule does not have the required number of installments.');

        $calculator = $this->getCalculator();

        $schedule = [[]];

        $constraints = [
            'num_installments' => 2,
        ];

        $calculator->verify('usd', $schedule, $constraints);
    }

    public function testVerifyNegativeAmounts(): void
    {
        $this->expectException(PaymentPlanCalculatorException::class);
        $this->expectExceptionMessage('Installments can only have positive amounts.');

        $calculator = $this->getCalculator();

        $schedule = [[
            'amount' => 0,
        ], [
            'amount' => 15.01,
        ], [
            'amount' => 20,
        ]];

        $constraints = [];

        $calculator->verify('usd', $schedule, $constraints);
    }

    public function testVerifyMultipleNonMatchingAmounts(): void
    {
        $this->expectException(PaymentPlanCalculatorException::class);
        $this->expectExceptionMessage('The installment amount(s) did not match the given constraint.');

        $calculator = $this->getCalculator();

        $schedule = [[
            'amount' => 14.99,
        ], [
            'amount' => 15.01,
        ], [
            'amount' => 20,
        ]];

        $constraints = [
            'installment_amount' => 15,
        ];

        $calculator->verify('usd', $schedule, $constraints);
    }

    public function testVerifySingleNonMatchingAmount(): void
    {
        $this->expectException(PaymentPlanCalculatorException::class);
        $this->expectExceptionMessage('The installment amount(s) did not match the given constraint.');

        $calculator = $this->getCalculator();

        $schedule = [[
            'amount' => 500,
        ]];

        $constraints = [
            'installment_amount' => 100,
        ];

        $calculator->verify('usd', $schedule, $constraints);
    }

    public function testVerifyWrongTotal(): void
    {
        $this->expectException(PaymentPlanCalculatorException::class);
        $this->expectExceptionMessage('The installment amounts did not add up to the balance.');

        $calculator = $this->getCalculator();

        $schedule = [[
            'amount' => 14.99,
        ], [
            'amount' => 15.01,
        ], [
            'amount' => 20,
        ]];

        $constraints = [
            'total' => 100,
        ];

        $calculator->verify('usd', $schedule, $constraints);
    }

    public function testVerifyWrongStartDate(): void
    {
        $this->expectException(PaymentPlanCalculatorException::class);
        $this->expectExceptionMessage('Start date does not match the given constraint.');

        $calculator = $this->getCalculator();

        $schedule = [[
            'date' => mktime(0, 0, 0, 10, 13, 2016),
            'amount' => 10,
        ]];

        $constraints = [
            'start_date' => mktime(0, 0, 0, 10, 12, 2016),
        ];

        $calculator->verify('usd', $schedule, $constraints);
    }

    public function testVerifyWrongEndDate(): void
    {
        $this->expectException(PaymentPlanCalculatorException::class);
        $this->expectExceptionMessage('End date does not match the given constraint.');

        $calculator = $this->getCalculator();

        $schedule = [[
            'date' => mktime(0, 0, 0, 10, 13, 2016),
            'amount' => 10,
        ]];

        $constraints = [
            'end_date' => mktime(0, 0, 0, 10, 12, 2016),
            'amount' => 10,
        ];

        $calculator->verify('usd', $schedule, $constraints);
    }

    public function testVerify(): void
    {
        $calculator = $this->getCalculator();

        $schedule = [[
            'amount' => 10.25,
            'date' => mktime(0, 0, 0, 10, 13, 2016),
        ], [
            'amount' => 10.25,
            'date' => mktime(0, 0, 0, 10, 14, 2016),
        ], [
            'amount' => 10.25,
            'date' => mktime(0, 0, 0, 10, 15, 2016),
        ], [
            'amount' => 10.25,
            'date' => mktime(0, 0, 0, 10, 16, 2016),
        ], [
            'amount' => 15,
            'date' => mktime(0, 0, 0, 10, 17, 2016),
        ]];

        $constraints = [
            'num_installments' => 5,
            'total' => 56,
            'installment_amount' => 10.25,
            'start_date' => mktime(1, 3, 42, 10, 13, 2016),
            'end_date' => mktime(1, 3, 42, 10, 17, 2016),
        ];

        $this->assertTrue($calculator->verify('usd', $schedule, $constraints));
    }
}
