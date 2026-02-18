<?php

namespace App\AccountsPayable\Models;

use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Throwable;

/**
 * @property string           $rules
 * @property int              $approval_workflow_id
 * @property ApprovalWorkflow $approval_workflow
 * @property array            $steps
 * @property int              $order
 * @property Member[]         $members
 * @property Role[]           $roles
 */
class ApprovalWorkflowPath extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'rules' => new Property(
                required: true,
                default: '',
            ),
            'approval_workflow' => new Property(
                required: true,
                belongs_to: ApprovalWorkflow::class,
            ),
            'steps' => new Property(
                has_many: ApprovalWorkflowStep::class,
            ),
        ];
    }

    /**
     * @return ApprovalWorkflowStep[]
     */
    public function getSteps(): array
    {
        $steps = $this->steps;
        usort($steps, fn (ApprovalWorkflowStep $a, ApprovalWorkflowStep $b) => $a->order <=> $b->order);

        return $steps;
    }

    public function evaluateVendorDocument(PayableDocument $doc): bool
    {
        if (!$this->rules) {
            return true;
        }
        $expressionLanguage = new ExpressionLanguage();
        $doc = (object) $doc->toArray();
        try {
            return (bool) $expressionLanguage->evaluate($this->rules, [
                'document' => $doc,
            ]);
        } catch (Throwable) {
            return false;
        }
    }

    public function toArray(): array
    {
        $result = parent::toArray();

        // expand relationships
        $result['steps'] = [];
        foreach ($this->getSteps() as $step) {
            $result['steps'][] = $step->toArray();
        }

        return $result;
    }
}
