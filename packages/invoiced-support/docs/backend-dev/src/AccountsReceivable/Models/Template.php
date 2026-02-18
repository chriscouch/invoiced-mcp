<?php

namespace App\AccountsReceivable\Models;

use App\Core\I18n\Currencies;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\SalesTax\Models\TaxRate;

/**
 * @deprecated
 */
class Template extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
            ),
            'currency' => new Property(
                null: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency'], 'nullable' => true],
            ),
            'chase' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'payment_terms' => new Property(
                null: true,
            ),
            'items' => new Property(
                type: Type::ARRAY,
                default: [],
            ),
            'discounts' => new Property(
                type: Type::ARRAY,
                default: [],
            ),
            'taxes' => new Property(
                type: Type::ARRAY,
                default: [],
            ),
            'notes' => new Property(
                null: true,
            ),
        ];
    }

    public function getTablename(): string
    {
        return 'InvoiceTemplates';
    }

    //
    // Hooks
    //

    public function toArray(): array
    {
        $result = parent::toArray();
        $this->toArrayHook($result, [], [], []);

        return $result;
    }

    public function toArrayHook(array &$result, array $exclude, array $include, array $expand): void
    {
        // discounts and taxes
        if (!isset($exclude['rates'])) {
            $result['discounts'] = Coupon::expandList((array) $result['discounts']);
            $result['taxes'] = TaxRate::expandList((array) $result['taxes']);

            if (isset($result['items'])) {
                foreach ($result['items'] as &$item) {
                    $item['discounts'] = Coupon::expandList((array) $item['discounts']);
                    $item['taxes'] = TaxRate::expandList((array) $item['taxes']);
                }
            }
        }
    }

    //
    // Mutators
    //

    /**
     * Sets the currency.
     */
    protected function setCurrencyValue(?string $currency): ?string
    {
        if (!$currency) {
            return $currency;
        }

        return strtolower($currency);
    }
}
