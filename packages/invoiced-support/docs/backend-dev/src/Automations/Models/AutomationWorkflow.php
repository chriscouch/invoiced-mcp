<?php

namespace App\Automations\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Traits\SoftDelete;
use App\Core\Orm\Type;

/**
 * @property int                            $id
 * @property ObjectType                     $object_type
 * @property string                         $name
 * @property string|null                    $description
 * @property AutomationWorkflowVersion|null $current_version
 * @property int|null                       $current_version_id
 * @property AutomationWorkflowVersion|null $draft_version
 * @property int|null                       $draft_version_id
 * @property bool                           $enabled
 */
class AutomationWorkflow extends MultitenantModel
{
    use SoftDelete;
    use AutoTimestamps;

    private ?AutomationWorkflowEnrollment $enrollment = null;

    protected static function getProperties(): array
    {
        return [
            'object_type' => new Property(
                type: Type::ENUM,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                enum_class: ObjectType::class,
            ),
            'name' => new Property(
                required: true,
                validate: [
                    ['unique', 'column' => 'name'],
                ],
            ),
            'description' => new Property(
                null: true,
            ),
            'current_version' => new Property(
                null: true,
                belongs_to: AutomationWorkflowVersion::class,
            ),
            'draft_version' => new Property(
                null: true,
                belongs_to: AutomationWorkflowVersion::class,
            ),
            'enabled' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
        ];
    }

    public function setEnrollment(?AutomationWorkflowEnrollment $enrollment): void
    {
        $this->enrollment = $enrollment;
    }

    public function getEnrollmentValue(): int|string|false|null
    {
        return $this->enrollment?->id();
    }

    public function toArray(): array
    {
        $result = parent::toArray();

        $result['trigger_types'] = [];

        if ($this->current_version && sizeof($this->current_version->triggers) > 0) {
            foreach ($this->current_version->triggers as $trigger) {
                $result['trigger_types'][] = $trigger->trigger_type->name;
            }
        }

        return $result;
    }
}
