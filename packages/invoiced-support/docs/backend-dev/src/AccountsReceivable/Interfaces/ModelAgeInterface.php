<?php

namespace App\AccountsReceivable\Interfaces;

/**
 * @property int      $age
 * @property int|null $past_due_age
 */
interface ModelAgeInterface
{
    /**
     * Gets the age property.
     */
    public function getAgeValue(): int;

    /**
     * Gets the past_due_age property.
     */
    public function getPastDueAgeValue(): ?int;
}
