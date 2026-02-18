<?php

namespace App\Core\RestApi\Interfaces;

/**
 * A normalization handler takes a given PHP object
 * and converts it into an array that is then passed
 * into the encoder.
 */
interface NormalizerInterface
{
    /**
     * Transforms a given input to an array.
     *
     * If the normalizer does not support the
     * given input type then null should be returned.
     */
    public function normalize(mixed $input): ?array;
}
