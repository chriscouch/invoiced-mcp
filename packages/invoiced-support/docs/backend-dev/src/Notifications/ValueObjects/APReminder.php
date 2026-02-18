<?php

namespace App\Notifications\ValueObjects;

class APReminder
{
    const THRESHOLD = 20;

    public function __construct(private readonly array $items)
    {
    }

    public function getSubject(): string
    {
        return 'You have assigned documents for approval';
    }

    public function getVariables(): array
    {
        return count($this->items) > static::THRESHOLD ? [
                'count' => count($this->items),
            ] : [
            'bills' => $this->items,
        ];
    }

    public function getTemplate(array $events): string
    {
        return count($this->items) > static::THRESHOLD
            ? 'ap-reminder-bulk'
            : 'ap-reminder';
    }
}
