<?php

namespace App\Automations\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;

/**
 * @property int                $object_id
 * @property AutomationWorkflow $workflow
 * @property int                $workflow_id
 */
class AutomationWorkflowEnrollment extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'object_id' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'workflow' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                belongs_to: AutomationWorkflow::class,
            ),
        ];
    }
}
