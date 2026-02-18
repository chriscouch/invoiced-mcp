<?php

namespace App\SubscriptionBilling\PricingRules;

/**
 * Base rule class.
 */
abstract class AbstractRule implements RuleInterface
{
    protected string $lastError = '';

    public function getLastValidationError(): string
    {
        return $this->lastError;
    }
}
