<?php

namespace App\Automations\Libs;

use App\Automations\Interfaces\NormalizerInterface;
use Carbon\CarbonImmutable;

/**
 * Shifts the date N days forward.
 */
class DaysFromNormalizer implements NormalizerInterface
{
    public function normalize(mixed $value): int
    {
        return CarbonImmutable::now()->addDays($value)->unix();
    }

    public static function getId(): string
    {
        return 'days_from';
    }
}
