<?php

namespace App\CashApplication\Libs;

use App\CashApplication\Models\BankFeedTransaction;
use App\CashApplication\Models\CashApplicationRule;
use App\Companies\Models\Company;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Iterator;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Throwable;

class CashApplicationRulesEvaluator
{
    private ExpressionLanguage $expressionLanguage;
    private Iterator $rules;

    /**
     * Validates a cash application rule formula.
     *
     * @throws ListenerException
     */
    public function validateRule(string $formula): void
    {
        try {
            $this->getExpressionLanguage()->compile($formula, ['transaction']);
        } catch (SyntaxError $e) {
            throw new ListenerException('Invalid formula: '.$e->getMessage(), ['field' => 'formula']);
        }
    }

    /**
     * Gets the rules that should be evaluated.
     *
     * @return CashApplicationRule[]
     */
    public function getMatchedRules(BankFeedTransaction $bankFeedTransaction): array
    {
        // run through all rules
        // to see if there is a match
        $rules = $this->getRules($bankFeedTransaction->tenant());
        $matchedRules = [];
        foreach ($rules as $rule) {
            if ($this->ruleMatches($rule, $bankFeedTransaction)) {
                $matchedRules[] = $rule;
            }
        }

        return $matchedRules;
    }

    /**
     * Checks if a rule matches a bank feed transaction based
     * on its formula.
     */
    private function ruleMatches(CashApplicationRule $rule, BankFeedTransaction $bankFeedTransaction): bool
    {
        try {
            return (bool) @$this->getExpressionLanguage()->evaluate($rule->formula, [
                'transaction' => (object) $bankFeedTransaction->toArray(),
            ]);
        } catch (Throwable) {
            return false;
        }
    }

    private function getExpressionLanguage(): ExpressionLanguage
    {
        if (!isset($this->expressionLanguage)) {
            $this->expressionLanguage = new ExpressionLanguage();
        }

        return $this->expressionLanguage;
    }

    /**
     * Gets the cash application rules for a given company.
     *
     * @return Iterator<CashApplicationRule>
     */
    private function getRules(Company $company): Iterator
    {
        if (!isset($this->rules)) {
            $this->rules = CashApplicationRule::queryWithTenant($company)->all();
        }

        return $this->rules;
    }
}
