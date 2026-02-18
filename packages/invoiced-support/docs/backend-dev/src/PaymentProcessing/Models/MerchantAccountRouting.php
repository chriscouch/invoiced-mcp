<?php

namespace App\PaymentProcessing\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\AccountsReceivable\Models\Invoice;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * This model tells payments to be routed a specific
 * merchant account under matching conditions.
 *
 * @property int    $id
 * @property string $method
 * @property int    $invoice_id
 * @property int    $merchant_account_id
 */
class MerchantAccountRouting extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'method' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'invoice_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                relation: Invoice::class,
            ),
            'merchant_account_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                relation: MerchantAccount::class,
            ),
        ];
    }

    /**
     * Gets the merchant account.
     */
    public function merchantAccount(): MerchantAccount
    {
        return $this->relation('merchant_account_id');
    }

    /**
     * Get merchant account instance by @PaymentMethod id and @Invoice id.
     */
    public static function findMerchantAccount(string $methodId, int $invoiceId): ?MerchantAccount
    {
        $routing = self::where('invoice_id', $invoiceId)
            ->where('method', $methodId)
            ->oneOrNull();

        return $routing?->merchantAccount();
    }
}
