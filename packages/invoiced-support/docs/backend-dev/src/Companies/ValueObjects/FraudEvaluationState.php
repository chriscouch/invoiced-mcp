<?php

namespace App\Companies\ValueObjects;

class FraudEvaluationState
{
    private string $message = '';

    /**
     * @param int $warnScoreThreshold  activity scoring greater than this number produces a warning
     * @param int $blockScoreThreshold activity scoring greater than this number are automatically blocked
     */
    public function __construct(
        public readonly array $userParams = [],
        public readonly array $companyParams = [],
        public readonly array $requestParams = [],
        public readonly int $warnScoreThreshold = 1,
        public readonly int $blockScoreThreshold = 3,
    ) {
    }

    public function addLine(string $message): void
    {
        $this->message = trim($this->message."\n".$message);
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
