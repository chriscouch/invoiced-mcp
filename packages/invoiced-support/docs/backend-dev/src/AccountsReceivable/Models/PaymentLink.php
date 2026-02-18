<?php

namespace App\AccountsReceivable\Models;

use App\AccountsReceivable\Enums\PaymentLinkStatus;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\I18n\Currencies;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Traits\SoftDelete;
use App\Core\Orm\Type;
use App\Core\Utils\ModelNormalizer;
use App\Core\Utils\Traits\HasClientIdTrait;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventModelTrait;

/**
 * @property int               $id
 * @property string            $name
 * @property Customer|null     $customer
 * @property int|null          $customer_id
 * @property bool              $reusable
 * @property PaymentLinkStatus $status
 * @property bool              $collect_billing_address
 * @property bool              $collect_shipping_address
 * @property bool              $collect_phone_number
 * @property string|null       $terms_of_service_url
 * @property string|null       $after_completion_url
 * @property string|null       $url
 * @property string            $currency
 * @property array             $items
 */
class PaymentLink extends MultitenantModel implements EventObjectInterface
{
    use AutoTimestamps;
    use SoftDelete;
    use HasClientIdTrait;
    use ApiObjectTrait;
    use EventModelTrait;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                validate: ['string', 'min' => 3, 'max' => 255],
                default: 'Payment Link',
            ),
            'customer' => new Property(
                null: true,
                belongs_to: Customer::class,
            ),
            'reusable' => new Property(
                type: Type::BOOLEAN,
            ),
            'currency' => new Property(
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'status' => new Property(
                type: Type::ENUM,
                enum_class: PaymentLinkStatus::class,
            ),
            'collect_billing_address' => new Property(
                type: Type::BOOLEAN,
            ),
            'collect_shipping_address' => new Property(
                type: Type::BOOLEAN,
            ),
            'collect_phone_number' => new Property(
                type: Type::BOOLEAN,
            ),
            'terms_of_service_url' => new Property(
                null: true,
            ),
            'after_completion_url' => new Property(
                null: true
            ),
        ];
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['url'] = $this->url;
        $result['object'] = $this->object;

        return $result;
    }

    protected function getUrlValue(): ?string
    {
        if (PaymentLinkStatus::Active != $this->status) {
            return null;
        }

        return $this->tenant()->url.'/pay/'.$this->client_id;
    }

    protected function getItemsValue(): array
    {
        $items = PaymentLinkItem::where('payment_link_id', $this)->all();
        $result = [];
        foreach ($items as $item) {
            $result[] = [
                'id' => $item->id,
                'description' => $item->description,
                'amount' => $item->amount,
            ];
        }

        return $result;
    }

    protected function getFieldsValue(): array
    {
        $fields = $this->getFields();
        $result = [];
        foreach ($fields as $field) {
            $result[] = [
                'id' => $field->id,
                'object_type' => $field->object_type->typeName(),
                'custom_field_id' => $field->custom_field_id,
                'required' => $field->required,
            ];
        }

        return $result;
    }

    /**
     * @return PaymentLinkField[]
     */
    public function getFields(): array
    {
        return PaymentLinkField::where('payment_link_id', $this)
            ->sort('order ASC')
            ->first(100);
    }

    //
    // EventObjectInterface
    //

    public function getEventAssociations(): array
    {
        $associations = [];
        if ($customerId = $this->customer_id) {
            $associations[] = ['customer', $customerId];
        }

        return $associations;
    }

    public function getEventObject(): array
    {
        return ModelNormalizer::toArray($this, include: ['items'], expand: ['customer']);
    }
}
