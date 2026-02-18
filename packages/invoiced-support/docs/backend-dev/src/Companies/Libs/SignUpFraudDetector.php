<?php

namespace App\Companies\Libs;

use App\Companies\Enums\FraudOutcome;
use App\Companies\Interfaces\FraudScoreInterface;
use App\Companies\ValueObjects\FraudEvaluationState;
use App\Companies\ValueObjects\FraudScore;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\LoggerFactory;

/**
 * Scores how likely a new company sign up is to be fraudulent.
 */
class SignUpFraudDetector implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    /**
     * @param FraudScoreInterface[] $scorers
     */
    public function __construct(
        private LoggerFactory $loggerFactory,
        private iterable $scorers
    ) {
    }

    /**
     * Evaluates whether a new company sign up is likely to be fraudulent.
     */
    public function evaluate(array $userParams, array $companyParams, array $requestParams): FraudScore
    {
        $state = new FraudEvaluationState(
            userParams: $userParams,
            companyParams: $companyParams,
            requestParams: $requestParams,
            warnScoreThreshold: 1,
            blockScoreThreshold: 3,
        );

        $state->addLine('Calculating fraud score for new company sign up');
        $state->addLine('Company parameters: '.json_encode((object) $companyParams));
        unset($userParams['password']);
        $state->addLine('User parameters: '.json_encode((object) $userParams));
        $state->addLine('Request parameters: '.json_encode((object) $requestParams));

        $score = $this->score($state);
        $state->addLine('Score: '.$score);

        if ($score > $state->blockScoreThreshold) {
            $this->statsd->increment('security.fraudulent_account_block');
            $determination = FraudOutcome::Block;
        } elseif ($score > $state->warnScoreThreshold) {
            $this->statsd->increment('security.fraudulent_account_warning');
            $determination = FraudOutcome::Warning;
        } else {
            $determination = FraudOutcome::Pass;
        }

        $state->addLine('Outcome: '.$determination->value);
        $this->logMessage($state);

        return new FraudScore(
            score: $score,
            determination: $determination,
            log: $state->getMessage(),
        );
    }

    /**
     * Computes a fraud score for a company. A score
     * greater than 0 indicates there is a risk. A larger
     * score indicates a higher risk of fraud.
     */
    private function score(FraudEvaluationState $state): int
    {
        $score = 0;
        foreach ($this->scorers as $scorer) {
            $score += $scorer->calculateScore($state);
        }

        return $score;
    }

    private function logMessage(FraudEvaluationState $state): void
    {
        $logger = $this->loggerFactory->get('fraud');
        $logger->info($state->getMessage());
    }
}
