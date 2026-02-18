<?php

namespace App\AccountsPayable\Models;

use App\Companies\Traits\HasAutoNumberingTrait;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\I18n\Currencies;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\ModelCreating;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Utils\ModelNormalizer;
use App\Core\Utils\ModelUtility;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventModelTrait;
use App\PaymentProcessing\Models\PaymentMethod;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * @property int                     $id
 * @property Vendor                  $vendor
 * @property int                     $vendor_id
 * @property DateTimeInterface       $date
 * @property float                   $amount
 * @property string                  $currency
 * @property string                  $payment_method
 * @property string|null             $reference
 * @property string|null             $notes
 * @property DateTimeInterface       $expected_arrival_date
 * @property bool                    $voided
 * @property DateTimeInterface|null  $date_voided
 * @property array                   $applied_to
 * @property VendorPaymentBatch|null $vendor_payment_batch
 * @property int|null                $vendor_payment_batch_id
 * @property VendorPaymentBatchBill  $vendor_payment_batch_bill
 * @property CompanyBankAccount|null $bank_account
 * @property int|null                $bank_account_id
 * @property CompanyCard|null        $card
 * @property int|null                $card_id
 */
class VendorPayment extends MultitenantModel implements EventObjectInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use EventModelTrait;
    use HasAutoNumberingTrait;

    protected static function getProperties(): array
    {
        return [
            'vendor' => new Property(
                belongs_to: Vendor::class,
            ),
            'number' => new Property(
                validate: ['string', 'min' => 1, 'max' => 32],
            ),
            'date' => new Property(
                type: Type::DATE,
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'currency' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'payment_method' => new Property(
                required: true,
                default: PaymentMethod::OTHER,
            ),
            'reference' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'notes' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'expected_arrival_date' => new Property(
                type: Type::DATE,
            ),
            'voided' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'date_voided' => new Property(
                type: Type::DATE,
                null: true,
                in_array: false,
            ),
            'bank_account' => new Property(
                null: true,
                belongs_to: CompanyBankAccount::class,
            ),
            'card' => new Property(
                null: true,
                belongs_to: CompanyCard::class,
            ),
            'vendor_payment_batch' => new Property(
                null: true,
                belongs_to: VendorPaymentBatch::class,
            ),
            'vendor_payment_batch_bills' => new Property(
                in_array: false,
                foreign_key: 'vendor_payment_batch_id',
                has_many: VendorPaymentBatchBill::class,
            ),
        ];
    }

    /** @var VendorPaymentItem[] */
    private array $items;

    protected function initialize(): void
    {
        parent::initialize();
        self::creating([self::class, 'defaultValues']);
    }

    public static function defaultValues(ModelCreating $event): void
    {
        /** @var self $payment */
        $payment = $event->getModel();

        if (!$payment->date) { /* @phpstan-ignore-line */
            $payment->date = CarbonImmutable::now();
        }
    }

    public function getEventAssociations(): array
    {
        return [
            ['vendor', $this->vendor_id],
        ];
    }

    public function getEventObject(): array
    {
        return ModelNormalizer::toArray($this, exclude: ['vendor_payment_batch_bills'], expand: ['vendor']);
    }

    /**
     * @param VendorPaymentItem[] $items
     */
    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    /**
     * @return VendorPaymentItem[]
     */
    public function getItems(): array
    {
        if (!isset($this->items)) {
            $this->items = ModelUtility::getAllModels(VendorPaymentItem::where('vendor_payment_id', $this));
        }

        return $this->items;
    }

    public function getAppliedToValue(): array
    {
        $result = [];
        foreach ($this->getItems() as $item) {
            $result[] = [
                'type' => $item->type->toString(),
                'bill' => $item->bill?->toArray(),
                'vendor_credit' => $item->vendor_credit?->toArray(),
                'amount' => $item->amount,
            ];
        }

        return $result;
    }

    public function getECheckValue(): ECheck|null
    {
        return ECheck::where('payment_id', $this->id)
            ->oneOrNull();
    }
}
