<?php

namespace App\PaymentProcessing\Models;

use App\Companies\Traits\HasAutoNumberingTrait;
use App\Core\I18n\Currencies;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Utils\ModelUtility;
use App\PaymentProcessing\Enums\CustomerBatchPaymentStatus;
use Generator;

/**
 * @property int                        $id
 * @property string                     $name
 * @property CustomerBatchPaymentStatus $status
 * @property AchFileFormat|null         $ach_file_format
 * @property string                     $payment_method
 * @property float                      $total
 * @property string                     $currency
 */
class CustomerPaymentBatch extends MultitenantModel
{
    use AutoTimestamps;
    use HasAutoNumberingTrait;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                default: 'Payment Batch',
            ),
            'number' => new Property(
                validate: ['string', 'min' => 1, 'max' => 32],
            ),
            'status' => new Property(
                type: Type::ENUM,
                required: true,
                default: CustomerBatchPaymentStatus::Created,
                enum_class: CustomerBatchPaymentStatus::class,
            ),
            'payment_method' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'currency' => new Property(
                type: Type::STRING,
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'total' => new Property(
                type: Type::FLOAT,
                default: 0,
            ),
            'ach_file_format' => new Property(
                belongs_to: AchFileFormat::class,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();
        self::saving([self::class, 'validatePaymentMethod']);
    }

    public static function validatePaymentMethod(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if ('ach' == $model->payment_method) {
            if (!$model->ach_file_format) {
                throw new ListenerException('Missing ACH file format');
            }
        }
    }

    /**
     * Gets all charges eligible to be added a payment batch.
     */
    public static function getEligibleCharges(): array
    {
        $query = Charge::where('status', Charge::PENDING)
            ->where('gateway', 'nacha')
            ->where('payment_source_type', 'bank_account');
        $charges = ModelUtility::getAllModelsGenerator($query);
        $result = [];
        foreach ($charges as $charge) {
            $exists = CustomerPaymentBatchItem::where('charge_id', $charge)->count();
            if (!$exists) {
                $result[] = $charge;
            }
        }

        return $result;
    }

    /**
     * @return Generator<CustomerPaymentBatchItem>
     */
    public function getItems(): Generator
    {
        $query = CustomerPaymentBatchItem::where('customer_payment_batch_id', $this)
            ->with('charge');

        return ModelUtility::getAllModelsGenerator($query);
    }
}
