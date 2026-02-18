<?php

namespace App\AccountsPayable\Models;

use App\AccountsPayable\Enums\CheckStock;
use App\Companies\Models\Company;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Integrations\Plaid\Models\PlaidItem;
use App\PaymentProcessing\Models\AchFileFormat;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Traits\SoftDelete;
use App\Core\Orm\Type;

/**
 * @property int                $id
 * @property Company            $tenant
 * @property string             $name
 * @property int|null           $check_number
 * @property CheckStock|null    $check_layout
 * @property PlaidItem|null     $plaid
 * @property int|null           $plaid_id
 * @property string|null        $signature
 * @property bool               $default
 * @property string|null        $routing_number
 * @property string|null        $account_number
 * @property AchFileFormat|null $ach_file_format
 */
class CompanyBankAccount extends MultitenantModel
{
    use AutoTimestamps;
    use SoftDelete;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'check_number' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'check_layout' => new Property(
                type: Type::ENUM,
                null: true,
                enum_class: CheckStock::class,
            ),
            'plaid' => new Property(
                null: true,
                belongs_to: PlaidItem::class,
            ),
            'signature' => new Property(
                null: true,
                default: null,
                encrypted: true,
            ),
            'default' => new Property(
                type: Type::BOOLEAN,
                default: 0,
            ),
            'account_number' => new Property(
                null: true,
                default: null,
                encrypted: true,
                in_array: false,
            ),
            'routing_number' => new Property(
                null: true,
                default: null,
                in_array: false,
            ),
            'ach_file_format' => new Property(
                null: true,
                belongs_to: AchFileFormat::class,
            ),
        ];
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['payment_methods'] = $this->getPaymentMethods();

        return $result;
    }

    public function getPaymentMethods(): array
    {
        $methods = [];
        if ($this->supportsAch()) {
            $methods[] = 'ach';
        }

        if ($this->supportsECheck()) {
            $methods[] = 'echeck';
        }

        if ($this->supportsPrintCheck()) {
            $methods[] = 'print_check';
        }

        return $methods;
    }

    public function supportsPrintCheck(): bool
    {
        return $this->check_layout && $this->check_number > 0;
    }

    public function supportsAch(): bool
    {
        return null != $this->ach_file_format;
    }

    public function supportsECheck(): bool
    {
        return $this->account_number && $this->routing_number && $this->check_number > 0;
    }
}
