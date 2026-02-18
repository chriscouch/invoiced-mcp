<?php

namespace App\AccountsPayable\Models;

use App\AccountsPayable\Enums\PayableDocumentSource;
use App\AccountsPayable\Enums\PayableDocumentStatus;
use App\Chasing\Models\Task;
use App\Companies\Models\Member;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\I18n\Currencies;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Network\Models\NetworkDocument;
use DateTimeInterface;

/**
 * @property int                       $id
 * @property Vendor                    $vendor
 * @property int                       $vendor_id
 * @property string                    $number
 * @property DateTimeInterface         $date
 * @property string                    $currency
 * @property float                     $total
 * @property PayableDocumentStatus     $status
 * @property bool                      $voided
 * @property DateTimeInterface|null    $date_voided
 * @property NetworkDocument|null      $network_document
 * @property int|null                  $network_document_id
 * @property ApprovalWorkflowStep|null $approval_workflow_step
 * @property ApprovalWorkflow|null     $approval_workflow
 * @property PayableDocumentSource     $source
 */
abstract class PayableDocument extends MultitenantModel
{
    use AutoTimestamps;
    use ApiObjectTrait;

    protected static function getProperties(): array
    {
        return [
            'vendor' => new Property(
                required: true,
                belongs_to: Vendor::class,
            ),
            'date' => new Property(
                type: Type::DATE,
                required: true,
            ),
            'number' => new Property(
                type: Type::STRING,
                required: true,
                validate: ['string', 'min' => 1, 'max' => 255],
            ),
            'currency' => new Property(
                type: Type::STRING,
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'total' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'status' => new Property(
                type: Type::ENUM,
                required: true,
                default: PayableDocumentStatus::PendingApproval,
                enum_class: PayableDocumentStatus::class,
            ),
            'voided' => new Property(
                type: Type::BOOLEAN,
                required: true,
                default: false,
            ),
            'date_voided' => new Property(
                type: Type::DATE,
                null: true,
            ),
            'source' => new Property(
                type: Type::ENUM,
                default: PayableDocumentSource::Keyed,
                enum_class: PayableDocumentSource::class,
            ),
            'network_document' => new Property(
                null: true,
                belongs_to: NetworkDocument::class,
            ),
            'approval_workflow_step' => new Property(
                null: true,
                belongs_to: ApprovalWorkflowStep::class,
            ),
            'approval_workflow' => new Property(
                null: true,
                belongs_to: ApprovalWorkflow::class,
            ),
        ];
    }

    public function resolveTask(Member $member): void
    {
        $tasks = $this->getTasks($member);

        foreach ($tasks as $task) {
            $task->complete = true;
            $task->completed_date = time();
            $task->saveOrFail();
        }
    }

    abstract public function createRejection(): PayableDocumentResolution;

    abstract public function createApproval(): PayableDocumentResolution;

    abstract public function stepFinished(): bool;

    abstract public function getTaskAction(): string;

    /**
     * @return Task[]
     */
    abstract public function getTasks(Member $member): array;
}
