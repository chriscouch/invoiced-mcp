<?php

namespace App\Integrations\AccountingSync\WriteSync;

use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\ValueObjects\PaymentBankDecision;
use App\Integrations\AccountingSync\ValueObjects\PaymentRoute;

/**
 * Determines where to route a payment for an accounting system. Factors
 * include payment method and currency. The router attempts
 * to apply the most strict match that it can.
 *
 * No Match:
 * If a match cannot be made then nothing is returned and caller code can handle accordingly.
 *
 * Multiple Matches:
 * If more than one account matches then the rule that was listed first will be returned.
 */
class PaymentAccountMatcher
{
    private const CONDITIONS = [
        'currency',
        'method',
        'merchant_account',
    ];

    public function __construct(private array $rules)
    {
    }

    /**
     * Matches a payment to the appropriate account according to the
     * mapping rules. This is done by scoring each matching account and
     * returning the first match with the highest score.
     *
     * @throws SyncException
     */
    public function match(PaymentRoute $route): PaymentBankDecision
    {
        $maxScore = -1;
        $matchedRule = null;
        foreach ($this->rules as $rule) {
            $score = $this->score($route, $rule);
            if ($score > $maxScore) {
                $maxScore = $score;
                $matchedRule = $rule;
            }
        }

        if (!$matchedRule) {
            throw new SyncException('Could not find a matching account for the payment. Please check your mapping configuration and try again.');
        }

        return new PaymentBankDecision($matchedRule['undeposited_funds'] ?? false, $matchedRule['account'] ?? '');
    }

    private function score(PaymentRoute $route, array $rule): int
    {
        $values = $route->toArray();
        // Each condition that is in the rule will increase the score by:
        // 1 if the rule is a default rule (*)
        // 2 if the rule is an exact match, or
        // return -1 if any condition fails
        $score = 0;
        foreach (self::CONDITIONS as $k) {
            if (!isset($rule[$k]) || '*' == $rule[$k]) {
                ++$score;
            } elseif ($values[$k] == $rule[$k]) {
                $score += 2;
            } else {
                return -1; // a condition did not match was found
            }
        }

        return $score;
    }
}
