<?php

namespace App\Integrations\AccountingSync\ValueObjects;

use Carbon\CarbonImmutable;
use JsonSerializable;

final readonly class ReadQuery implements JsonSerializable
{
    public function __construct(
        public ?CarbonImmutable $lastSynced = null,
        public ?CarbonImmutable $startDate = null,
        public ?CarbonImmutable $endDate = null,
        public bool $openItemsOnly = false,
    ) {
    }

    public static function fromArray(array $args): self
    {
        $lastSynced = isset($args['last_synced']) ? new CarbonImmutable($args['last_synced']) : null;
        $startDate = isset($args['start_date']) ? new CarbonImmutable($args['start_date']) : null;
        $endDate = isset($args['end_date']) ? new CarbonImmutable($args['end_date']) : null;
        $openItemsOnly = $args['open_items_only'] ?? false;

        return new self($lastSynced, $startDate, $endDate, $openItemsOnly);
    }

    public function jsonSerialize(): array
    {
        return [
            'last_synced' => $this->lastSynced?->toIso8601String(),
            'start_date' => $this->startDate?->toDateString(),
            'end_date' => $this->endDate?->toDateString(),
            'open_items_only' => $this->openItemsOnly,
        ];
    }
}
