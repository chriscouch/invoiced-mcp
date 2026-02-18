<?php

namespace App\Integrations\Plaid\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Traits\SoftDelete;
use App\Core\Orm\Type;

/**
 * @property int         $id
 * @property string      $access_token
 * @property string      $access_token_enc
 * @property string|null $item_id
 * @property string      $account_id
 * @property string|null $account_name
 * @property string|null $account_last4
 * @property string|null $account_type
 * @property string|null $account_subtype
 * @property string|null $institution_id
 * @property string|null $institution_name
 * @property bool        $needs_update
 * @property bool        $verified
 */
class PlaidItem extends MultitenantModel
{
    use AutoTimestamps;
    use SoftDelete;

    public function getTablename(): string
    {
        return 'PlaidBankAccountLinks';
    }

    protected static function getProperties(): array
    {
        return [
            'access_token_enc' => new Property(
                required: true,
                encrypted: true,
                in_array: false,
            ),
            'item_id' => new Property(
                null: true,
            ),
            'account_id' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'account_name' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'account_last4' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'account_type' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'account_subtype' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'institution_id' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'institution_name' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'needs_update' => new Property(
                type: Type::BOOLEAN,
            ),
            'verified' => new Property(
                type: Type::INTEGER,
                default: 1,
            ),
        ];
    }

    /**
     * Sets the `access_token` property by encrypting it
     * and storing it on `access_token_enc`.
     */
    protected function setAccessTokenValue(mixed $token): mixed
    {
        if ($token) {
            $this->access_token_enc = $token;
        }

        return $token;
    }

    /**
     * Gets the decrypted `access_token` property value.
     */
    protected function getAccessTokenValue(mixed $token): mixed
    {
        if ($token || !$this->access_token_enc) {
            return $token;
        }

        return $this->access_token_enc;
    }
}
