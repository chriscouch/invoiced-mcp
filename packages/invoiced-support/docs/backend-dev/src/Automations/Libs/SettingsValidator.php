<?php

namespace App\Automations\Libs;

use App\Automations\Actions\AutomationActionFactory;
use App\Automations\Exception\AutomationException;
use App\Automations\Models\AutomationWorkflowStep;
use App\Core\Utils\Enums\ObjectType;

class SettingsValidator
{
    public function __construct(
        private readonly AutomationActionFactory $actionFactory,
    ) {
    }

    /**
     * @throws AutomationException
     */
    public function validateSetting(AutomationWorkflowStep $step, ObjectType $sourceObject): void
    {
        $action = $this->actionFactory->make($step->action_type);
        $step->settings = $action->validateSettings($step->settings, $sourceObject);
    }
}
