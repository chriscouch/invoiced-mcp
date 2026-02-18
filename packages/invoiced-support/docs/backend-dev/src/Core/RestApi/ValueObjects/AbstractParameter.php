<?php

namespace App\Core\RestApi\ValueObjects;

abstract class AbstractParameter
{
    private const MISSING_DEFAULT = '_____missing_default_____';

    public readonly bool $hasDefaultValue;
    public readonly mixed $defaultValue;

    public function __construct(
        public readonly bool $required = false,
        public readonly ?array $types = null,
        public readonly ?array $allowedValues = null,
        mixed $default = self::MISSING_DEFAULT,
    ) {
        $this->hasDefaultValue = self::MISSING_DEFAULT !== $default;
        $this->defaultValue = $this->hasDefaultValue ? $default : null;
    }
}
