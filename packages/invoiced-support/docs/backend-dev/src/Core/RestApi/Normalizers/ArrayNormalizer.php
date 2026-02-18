<?php

namespace App\Core\RestApi\Normalizers;

use App\Core\RestApi\Interfaces\NormalizerInterface;

class ArrayNormalizer implements NormalizerInterface
{
    public function normalize(mixed $input): ?array
    {
        if (!is_array($input)) {
            return null;
        }

        return $input;
    }
}
