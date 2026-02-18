<?php

namespace App\AccountsPayable\Models;

use App\Chasing\Models\Task;
use App\Companies\Models\Member;
use DateTimeInterface;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property DateTimeInterface|null $due_date
 * @property BillLineItem[]         $line_items
 */
class Bill extends PayableDocument
{
    protected static function getProperties(): array
    {
        $properties = parent::getProperties();
        $properties['due_date'] = new Property(
            type: Type::DATE,
            null: true,
        );
        $properties['line_items'] = new Property(
            has_many: BillLineItem::class,
        );

        return $properties;
    }

    public function toArray(): array
    {
        $result = parent::toArray();

        $result['line_items'] = [];
        if ($this->line_items) {
            foreach ($this->line_items as $lineItem) {
                $result['line_items'][] = $lineItem->toArray();
            }
        }

        return $result;
    }

    public function createRejection(): PayableDocumentResolution
    {
        $rejection = new BillRejection();
        $rejection->bill = $this;

        return $rejection;
    }

    public function createApproval(): PayableDocumentResolution
    {
        $approval = new BillApproval();
        $approval->bill = $this;

        return $approval;
    }

    public function getTaskAction(): string
    {
        return 'approve_bill';
    }

    public function stepFinished(): bool
    {
        $step = $this->approval_workflow_step;
        if (null === $step) {
            return true;
        }

        return BillApproval::where('bill_id', $this->id)
            ->where('approval_workflow_step_id', $step->id())
            ->count() >= $step->minimum_approvers;
    }

    public function getTasks(Member $member): array
    {
        return Task::where('bill_id', $this->id)
            ->where('user_id', $member->user_id)
            ->where('action', $this->getTaskAction())
            ->execute();
    }
}
