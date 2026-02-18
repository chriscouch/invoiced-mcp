<?php

namespace App\Chasing\Legacy;

/**
 * Represents a discrete step in a chasing schedule.
 *
 * @deprecated Part of the legacy feature 'legacy_chasing'
 */
class ChaseScheduleStep implements \Stringable
{
    const STEP_ISSUED = 'issued';

    const ACTION_EMAIL = 'email';
    const ACTION_FLAG = 'flag';

    public static function fromArray(array $row): self
    {
        return new self($row['step'], $row['action']);
    }

    public function __construct(private string $step, private string $action = self::ACTION_EMAIL)
    {
    }

    public function __toString(): string
    {
        return $this->step.$this->action;
    }

    /**
     * Converts the step to an array.
     */
    public function toArray(): array
    {
        return [
            'step' => $this->step,
            'action' => $this->action,
        ];
    }

    public function getStep(): string
    {
        return $this->step;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}
