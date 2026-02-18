<?php

namespace App\Automations\Interfaces;

use App\Companies\Models\Company;
use App\Core\Utils\Enums\ObjectType;
use App\ActivityLog\Models\Event;

interface AutomationEventInterface
{
    public function objectData(): array;

    public function eventType(): int;

    public function getId(): ?int;

    public function self(): ?Event;

    public function getObjectType(): ObjectType;

    public function tenant(): Company;
}
