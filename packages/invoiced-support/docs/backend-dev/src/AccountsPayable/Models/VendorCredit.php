<?php

namespace App\AccountsPayable\Models;

use App\Chasing\Models\Task;
use App\Companies\Models\Member;
use App\Core\Orm\Property;

/**
 * @property VendorCreditLineItem[] $line_items
 */
class VendorCredit extends PayableDocument
{
    protected static function getProperties(): array
    {
        $properties = parent::getProperties();
        $properties['line_items'] = new Property(
            has_many: VendorCreditLineItem::class,
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
        $rejection = new VendorCreditRejection();
        $rejection->vendor_credit = $this;

        return $rejection;
    }

    public function createApproval(): PayableDocumentResolution
    {
        $approval = new VendorCreditApproval();
        $approval->vendor_credit = $this;

        return $approval;
    }

    public function stepFinished(): bool
    {
        $step = $this->approval_workflow_step;
        if (null === $step) {
            return true;
        }

        return VendorCreditApproval::where('vendor_credit_id', $this->id)
            ->where('approval_workflow_step_id', $step->id())
            ->count() >= $step->minimum_approvers;
    }

    public function getTaskAction(): string
    {
        return 'approve_vendor_credit';
    }

    public function getTasks(Member $member): array
    {
        return Task::where('vendor_credit_id', $this->id)
            ->where('user_id', $member->user_id)
            ->where('action', $this->getTaskAction())
            ->execute();
    }
}
