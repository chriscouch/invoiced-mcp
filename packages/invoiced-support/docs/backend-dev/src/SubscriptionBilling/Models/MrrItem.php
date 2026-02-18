<?php

namespace App\SubscriptionBilling\Models;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\Item;
use App\AccountsReceivable\Models\LineItem;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;
use DateTimeInterface;

/**
 * @property MrrVersion        $version
 * @property Customer          $customer
 * @property int|null          $customer_id
 * @property LineItem|null     $line_item
 * @property int|null          $line_item_id
 * @property Subscription|null $subscription
 * @property int|null          $subscription_id
 * @property Invoice|null      $invoice
 * @property int|null          $invoice_id
 * @property CreditNote|null   $credit_note
 * @property int|null          $credit_note_id
 * @property Plan|null         $plan
 * @property int|null          $plan_id
 * @property Item|null         $item
 * @property int|null          $item_id
 * @property int               $month
 * @property DateTimeInterface $date
 * @property bool              $partial_month
 * @property float             $mrr
 * @property float             $discount
 */
class MrrItem extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'version' => new Property(
                belongs_to: MrrVersion::class,
            ),
            'customer' => new Property(
                belongs_to: Customer::class,
            ),
            'line_item' => new Property(
                null: true,
                belongs_to: LineItem::class,
            ),
            'subscription' => new Property(
                null: true,
                belongs_to: Subscription::class,
            ),
            'invoice' => new Property(
                null: true,
                belongs_to: Invoice::class,
            ),
            'credit_note' => new Property(
                null: true,
                belongs_to: CreditNote::class,
            ),
            'plan' => new Property(
                null: true,
                belongs_to: Plan::class,
            ),
            'item' => new Property(
                null: true,
                belongs_to: Item::class,
            ),
            'month' => new Property(
                type: Type::INTEGER,
            ),
            'date' => new Property(
                type: Type::DATE,
            ),
            'partial_month' => new Property(
                type: Type::BOOLEAN,
            ),
            'mrr' => new Property(
                type: Type::FLOAT,
            ),
            'discount' => new Property(
                type: Type::FLOAT,
            ),
        ];
    }
}
