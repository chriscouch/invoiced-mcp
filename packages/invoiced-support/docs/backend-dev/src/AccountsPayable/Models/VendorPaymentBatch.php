<?php

namespace App\AccountsPayable\Models;

use App\AccountsPayable\Enums\VendorBatchPaymentStatus;
use App\AccountsPayable\Enums\CheckStock;
use App\Companies\Models\Member;
use App\Companies\Traits\HasAutoNumberingTrait;
use App\Core\I18n\Currencies;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int                      $id
 * @property string                   $name
 * @property string                   $currency
 * @property float                    $total
 * @property Member|null              $member
 * @property VendorBatchPaymentStatus $status
 * @property int|null                 $initial_check_number
 * @property CheckStock|null          $check_layout
 * @property CompanyBankAccount|null  $bank_account
 * @property int|null                 $bank_account_id
 * @property CompanyCard|null         $card
 * @property int|null                 $card_id
 * @property string                   $payment_method
 */
class VendorPaymentBatch extends MultitenantModel
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
            'currency' => new Property(
                type: Type::STRING,
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'total' => new Property(
                type: Type::FLOAT,
                default: 0,
            ),
            'member' => new Property(
                null: true,
                default: null,
                belongs_to: Member::class,
            ),
            'status' => new Property(
                type: Type::ENUM,
                required: true,
                default: VendorBatchPaymentStatus::Created,
                enum_class: VendorBatchPaymentStatus::class,
            ),
            'payment_method' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'bank_account' => new Property(
                belongs_to: CompanyBankAccount::class,
            ),
            'card' => new Property(
                belongs_to: CompanyCard::class,
            ),
            'initial_check_number' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'check_layout' => new Property(
                type: Type::ENUM,
                null: true,
                enum_class: CheckStock::class,
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

        if ('print_check' == $model->payment_method) {
            if (!$model->check_layout) {
                throw new ListenerException('Missing check layout');
            }

            if (!$model->initial_check_number || $model->initial_check_number < 0) {
                throw new ListenerException('Missing initial check number');
            }
        }

        if ('echeck' == $model->payment_method) {
            if (!$model->initial_check_number || $model->initial_check_number < 0) {
                throw new ListenerException('Missing initial check number');
            }
        }

        if ('credit_card' == $model->payment_method) {
            if (!$model->card) {
                throw new ListenerException('Missing card');
            }
        } else {
            if (!$model->bank_account) {
                throw new ListenerException('Missing bank account');
            }
        }
    }
}
