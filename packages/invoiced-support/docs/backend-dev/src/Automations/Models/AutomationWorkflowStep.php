<?php

namespace App\Automations\Models;

use App\Automations\Enums\AutomationActionType;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int                       $id
 * @property AutomationWorkflowVersion $workflow_version
 * @property AutomationActionType      $action_type
 * @property object                    $settings
 * @property int                       $order
 */
class AutomationWorkflowStep extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'workflow_version' => new Property(
                required: true,
                in_array: false,
                belongs_to: AutomationWorkflowVersion::class,
            ),
            'action_type' => new Property(
                type: Type::ENUM,
                required: true,
                enum_class: AutomationActionType::class,
            ),
            'settings' => new Property(
                type: Type::OBJECT,
                required: true,
            ),
            'order' => new Property(
                type: Type::INTEGER,
                required: true,
                in_array: false,
            ),
        ];
    }
}
