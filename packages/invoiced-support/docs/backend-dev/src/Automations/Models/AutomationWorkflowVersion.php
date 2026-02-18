<?php

namespace App\Automations\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int                         $id
 * @property AutomationWorkflow          $automation_workflow
 * @property int                         $automation_workflow_id
 * @property int                         $version
 * @property AutomationWorkflowTrigger[] $triggers
 * @property AutomationWorkflowStep[]    $steps
 */
class AutomationWorkflowVersion extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'automation_workflow' => new Property(
                required: true,
                in_array: false,
                belongs_to: AutomationWorkflow::class,
            ),
            'version' => new Property(
                type: Type::INTEGER,
                default: 1,
            ),
            'triggers' => new Property(
                foreign_key: 'workflow_version_id',
                has_many: AutomationWorkflowTrigger::class,
            ),
            'steps' => new Property(
                foreign_key: 'workflow_version_id',
                has_many: AutomationWorkflowStep::class,
            ),
        ];
    }

    public function toArray(): array
    {
        $result = parent::toArray();

        $result['triggers'] = [];
        foreach ($this->triggers as $trigger) {
            $result['triggers'][] = $trigger->toArray();
        }

        $result['steps'] = [];
        foreach ($this->steps as $step) {
            $result['steps'][] = $step->toArray();
        }

        return $result;
    }
}
