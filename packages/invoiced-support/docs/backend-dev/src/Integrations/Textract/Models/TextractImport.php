<?php

namespace App\Integrations\Textract\Models;

use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Models\VendorCredit;
use App\Core\Files\Models\File;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Integrations\Textract\Enums\TextractProcessingStage;
use Carbon\CarbonImmutable;

/**
 * @property string                  $job_id
 * @property string|null             $parent_job_id
 * @property File                    $file
 * @property Vendor|null             $vendor
 * @property int|null                $vendor_id
 * @property object                  $data
 * @property TextractProcessingStage $status
 * @property Bill|null               $bill
 * @property VendorCredit|null       $vendor_credit
 * @property bool                    $requires_training
 */
class TextractImport extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'job_id' => new Property(
                type: Type::STRING,
            ),
            'parent_job_id' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'file' => new Property(
                belongs_to: File::class,
            ),
            'vendor' => new Property(
                null: true,
                belongs_to: Vendor::class,
            ),
            'bill' => new Property(
                null: true,
                belongs_to: Bill::class,
            ),
            'vendor_credit' => new Property(
                null: true,
                belongs_to: VendorCredit::class,
            ),
            'data' => new Property(
                type: Type::OBJECT,
                default: [],
            ),
            'status' => new Property(
                type: Type::ENUM,
                default: TextractProcessingStage::Created,
                enum_class: TextractProcessingStage::class,
            ),
            'requires_training' => new Property(
                type: Type::BOOLEAN,
                default: 0,
            ),
        ];
    }

    public function isCompleted(): bool
    {
        return TextractProcessingStage::Succeed === $this->status || TextractProcessingStage::Failed === $this->status;
    }

    public function checkNeedsTraining(array $parameters): void
    {
        // main fields matched
        if ($parameters['vendor']?->id !== $this->vendor?->id
            || $parameters['number'] !== $this->data->number
            || !CarbonImmutable::parse($parameters['date'])->isSameDay(CarbonImmutable::parse($this->data->date))
            || $this->normalizeLineItems($parameters['line_items']) !== $this->normalizeLineItems($this->data->line_items)
        ) {
            $this->requires_training = true;
        }
    }

    private function normalizeLineItems(array $lineItems): array
    {
        $lineItems = array_map(fn (array|object $item) => ((array) $item)['amount'], $lineItems);
        sort($lineItems);

        return $lineItems;
    }
}
