<?php

namespace App\Companies\Interfaces;

use App\Companies\ValueObjects\FraudEvaluationState;

/**
 * An interface for a check that scores the likelihood that
 * a new company sign up is fraudulent.
 */
interface FraudScoreInterface
{
    /**
     * Computes a fraud likelihood score for a new company sign up.
     * A score greater than 0 indicates there is a risk. A larger
     * score indicates a higher risk of fraud.
     *
     * @return int fraud score
     */
    public function calculateScore(FraudEvaluationState $state): int;
}
