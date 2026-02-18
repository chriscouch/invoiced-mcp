<?php

namespace App\Automations\Models;

use App\Automations\Enums\AutomationResult;
use App\Core\Multitenant\Models\MultitenantModel;
use DateTimeInterface;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property AutomationRun          $workflow_run
 * @property AutomationWorkflowStep $workflow_step
 * @property mixed                  $result
 * @property string|null            $error_message
 * @property DateTimeInterface|null $finished_at
 */
class AutomationStepRun extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'workflow_run' => new Property(
                required: true,
                belongs_to: AutomationRun::class,
            ),
            'workflow_step' => new Property(
                required: true,
                belongs_to: AutomationWorkflowStep::class,
            ),
            'result' => new Property(
                type: Type::ENUM,
                enum_class: AutomationResult::class,
            ),
            'error_message' => new Property(
                null: true,
            ),
            'finished_at' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
        ];
    }
}
