<?php

namespace App\Companies\FraudScore;

use App\Companies\Interfaces\FraudScoreInterface;
use App\Companies\ValueObjects\FraudEvaluationState;

/**
 * Scores the fraud likelihood of a sign up based on the company name.
 */
class CompanyNameFraudScore implements FraudScoreInterface
{
    private const PROTECTED_WORDS = [
        'amazon',
        'bank',
        'binance',
        'bit.do',
        'bit.ly',
        'coinbase',
        'crypto',
        'dhl',
        'facebook',
        'goo.gl',
        'ht.ly',
        'http',
        'instagram',
        'internal revenue service',
        'irs',
        'is.gd',
        'metamask',
        'microsoft',
        'money',
        'nft',
        'ow.ly',
        'paypal',
        'rebrand.ly',
        't.co',
        'tinyurl.com',
        'transfer',
        'union',
        'x.co',
    ];

    public function calculateScore(FraudEvaluationState $state): int
    {
        $score = 0;

        $name = $state->companyParams['name'] ?? '';

        // Protected Words
        foreach (self::PROTECTED_WORDS as $word) {
            if (str_contains(strtolower($name), $word)) {
                $state->addLine('Company has "'.$word.'" in the name');
                $score += 2;
            }
        }

        // Short name
        if (strlen($name) > 0 && strlen($name) <= 2) {
            $state->addLine("Suspiciously short company name \"{$name}\"");
            $score += 1 == strlen($name) ? 2 : 1;
        }

        // Long name
        if (strlen($name) > 100) {
            $state->addLine("Suspiciously long company name \"{$name}\"");
            ++$score;
        }

        return $score;
    }
}
