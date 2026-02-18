<?php

namespace App\Companies\ValueObjects;

use App\Companies\Enums\FraudOutcome;

final class FraudScore
{
    public function __construct(
        public readonly int $score,
        public readonly FraudOutcome $determination,
        public readonly string $log,
    ) {
    }
}
