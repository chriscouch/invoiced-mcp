<?php

namespace App\Tests\CashApplication\Libs;

use App\CashApplication\Libs\CashApplicationRulesEvaluator;
use App\CashApplication\Models\BankFeedTransaction;
use App\CashApplication\Models\CashApplicationRule;
use App\Core\Orm\Exception\ListenerException;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class CashApplicationRulesEvaluatorTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testValidateRuleInvalid(): void
    {
        $this->expectException(ListenerException::class);
        $this->getEvaluator()->validateRule('Invalid Syntax');
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testValidateRuleValid(): void
    {
        $this->getEvaluator()->validateRule('transaction.description contains "Test"');
    }

    public function testGetMatchedRules(): void
    {
        $evaluator = $this->getEvaluator();

        $bankFeedTransaction = new BankFeedTransaction();
        $bankFeedTransaction->transaction_id = 'TEST';
        $bankFeedTransaction->amount = -200;
        $bankFeedTransaction->description = 'ORIG CO NAME: Test';
        $bankFeedTransaction->payment_method = 'ACH';
        $bankFeedTransaction->payment_payer = 'Payer Name';
        $bankFeedTransaction->payment_reference_number = 'INV-12341234';
        $bankFeedTransaction->date = CarbonImmutable::now();

        $this->makeRule('transaction.description contains "Test"');
        $this->makeRule('transaction.description contains "ADP"');
        $this->makeRule('transaction.description contains "STRIPE"');
        $this->makeRule('transaction.payment_payer == "Some Payer"');
        $this->makeRule('transaction.payment_reference_number == null');
        $this->makeRule('transaction.payment_method == "ACH"');

        $rules = $evaluator->getMatchedRules($bankFeedTransaction);
        $this->assertCount(2, $rules);
        $formulas = [];
        foreach ($rules as $rule) {
            $formulas[] = $rule->formula;
        }
        $this->assertTrue(in_array('transaction.description contains "Test"', $formulas));
        $this->assertTrue(in_array('transaction.payment_method == "ACH"', $formulas));
    }

    private function makeRule(string $formula): void
    {
        $rule = new CashApplicationRule();
        $rule->formula = $formula;
        $rule->ignore = true;
        $rule->saveOrFail();
    }

    private function getEvaluator(): CashApplicationRulesEvaluator
    {
        return new CashApplicationRulesEvaluator();
    }
}
