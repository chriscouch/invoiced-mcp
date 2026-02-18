<?php

namespace App\Automations\ValueObjects;

use App\Automations\Enums\AutomationEventType;
use App\Automations\Interfaces\AutomationEventInterface;
use App\Companies\Models\Company;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Enums\EventType;
use RuntimeException;

final readonly class AutomationEvent implements AutomationEventInterface
{
    public function __construct(
        private MultitenantModel $model,
        private int $eventType,
    ) {
    }

    public function getId(): ?int
    {
        return null;
    }

    public function objectData(): array
    {
        return ModelNormalizer::toArray($this->model);
    }

    public function eventType(): int
    {
        return $this->eventType;
    }

    public static function fromEventType(string $eventType): int
    {
        return EventType::tryFrom($eventType)?->toInteger() ?? AutomationEventType::from($eventType)->toInteger();
    }

    public static function fromInteger(int $type): string
    {
        foreach (EventType::cases() as $case) {
            if ($case->toInteger() == $type) {
                return $case->value;
            }
        }
        foreach (AutomationEventType::cases() as $case) {
            if ($case->toInteger() == $type) {
                return $case->value;
            }
        }

        throw new RuntimeException('No such event type: '.$type);
    }

    public function self(): null
    {
        return null;
    }

    public function getObjectType(): ObjectType
    {
        return ObjectType::fromModel($this->model);
    }

    public function tenant(): Company
    {
        return $this->model->tenant();
    }
}
