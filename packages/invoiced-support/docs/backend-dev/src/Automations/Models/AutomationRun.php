<?php

namespace App\Automations\Models;

use App\Automations\Enums\AutomationResult;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Utils\Enums\ObjectType;
use App\ActivityLog\Models\Event;
use DateTimeInterface;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int                       $id
 * @property AutomationWorkflowVersion $workflow_version
 * @property AutomationWorkflowTrigger $trigger
 * @property AutomationResult|null     $result
 * @property DateTimeInterface|null    $finished_at
 * @property ObjectType                $object_type
 * @property string                    $object_id
 * @property Event|null                $event
 * @property int|null                  $event_id
 * @property int|null                  $event_type_id
 */
class AutomationRun extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'workflow_version' => new Property(
                required: true,
                belongs_to: AutomationWorkflowVersion::class,
            ),
            'trigger' => new Property(
                required: true,
                belongs_to: AutomationWorkflowTrigger::class,
            ),
            'result' => new Property(
                type: Type::ENUM,
                null: true,
                enum_class: AutomationResult::class,
            ),
            'object_type' => new Property(
                type: Type::ENUM,
                required: true,
                enum_class: ObjectType::class,
            ),
            'object_id' => new Property(
                required: true,
            ),
            'event' => new Property(
                null: true,
                belongs_to: Event::class,
            ),
            'event_type_id' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'finished_at' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
        ];
    }

    public function getObjectValue(): ?MultitenantModel
    {
        $model = $this->object_type->modelClass()::find($this->object_id);
        if (!$model instanceof MultitenantModel) {
            return null;
        }

        return $model;
    }

    public function getStepsValue(): array
    {
        /** @var AutomationStepRun[] $stepRuns */
        $stepRuns = AutomationStepRun::where('workflow_run_id', $this->id)
            ->with('workflow_step')
            ->execute();

        return array_map(function ($stepRun) {
            $step = $stepRun->workflow_step->toArray();
            $stepRun = $stepRun->toArray();
            $stepRun['workflow_step'] = $step;

            return $stepRun;
        }, $stepRuns);
    }
}
