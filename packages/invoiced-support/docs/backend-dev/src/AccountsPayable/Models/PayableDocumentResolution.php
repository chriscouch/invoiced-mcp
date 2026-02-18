<?php

namespace App\AccountsPayable\Models;

use App\Companies\Models\Member;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property Member                    $member
 * @property ApprovalWorkflowStep|null $approval_workflow_step
 * @property string|null               $note
 */
abstract class PayableDocumentResolution extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'member' => new Property(
                required: true,
                belongs_to: Member::class,
            ),
            'approval_workflow_step' => new Property(
                null: true,
                belongs_to: ApprovalWorkflowStep::class,
            ),
            'note' => new Property(
                type: Type::STRING,
                null: true,
            ),
        ];
    }

    public function getApprovalWorkflowValue(): ?ApprovalWorkflow
    {
        return $this->approval_workflow_step?->approval_workflow_path->approval_workflow;
    }
}
