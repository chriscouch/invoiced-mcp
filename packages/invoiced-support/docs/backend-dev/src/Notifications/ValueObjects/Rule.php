<?php

namespace App\Notifications\ValueObjects;

/**
 * Rule class that takes conditions and match.
 *
 * @deprecated
 */
class Rule
{
    const MATCH_ALL = 'all';
    const MATCH_ANY = 'any';

    public function __construct(private string $match = 'all', private array $conditions = [])
    {
    }

    /**
     * Return conditions.
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * Return match.
     */
    public function getMatch(): string
    {
        return $this->match;
    }
}
