<?php

namespace App\Automations\Interfaces;

use App\Automations\Exception\AutomationException;
use App\Automations\ValueObjects\AutomationContext;
use App\Automations\ValueObjects\AutomationOutcome;
use App\Core\Utils\Enums\ObjectType;

interface AutomationActionInterface
{
    /**
     * @throws AutomationException when the settings are invalid
     */
    public function validateSettings(object $settings, ObjectType $sourceObject): object;

    public function perform(object $settings, AutomationContext $context): AutomationOutcome;
}
