<?php

namespace App\Integrations\NetSuite\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;

/**
 * @property string|null $name
 * @property string      $account_id
 * @property string|null $subsidiary_id
 * @property string      $token
 * @property string      $token_enc
 * @property string      $token_secret
 * @property string      $token_secret_enc
 * @property string      $restlet_domain
 */
class NetSuiteAccount extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getIDProperties(): array
    {
        return ['tenant_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'account_id' => new Property(
                required: true,
            ),
            'token_enc' => new Property(
                required: true,
                encrypted: true,
                in_array: false,
            ),
            'token_secret_enc' => new Property(
                required: true,
                encrypted: true,
                in_array: false,
            ),
            'name' => new Property(
                null: true,
            ),
            'subsidiary_id' => new Property(
                null: true,
            ),
            'restlet_domain' => new Property(),
        ];
    }

    /**
     * Sets the `token` property by encrypting it
     * and storing it on `token_enc`.
     */
    protected function setTokenValue(string $value): string
    {
        if ($value) {
            $this->token_enc = $value;
        }

        return $value;
    }

    /**
     * Gets the decrypted `token` property value.
     */
    protected function getTokenValue(mixed $value): string
    {
        if ($value || !$this->token_enc) {
            return (string) $value;
        }

        return $this->token_enc;
    }

    /**
     * Sets the `token_secret` property by encrypting it
     * and storing it on `token_secret_enc`.
     */
    protected function setTokenSecretValue(string $value): string
    {
        if ($value) {
            $this->token_secret_enc = $value;
        }

        return $value;
    }

    /**
     * Gets the decrypted `token_secret` property value.
     */
    protected function getTokenSecretValue(mixed $value): string
    {
        if ($value || !$this->token_secret_enc) {
            return (string) $value;
        }

        return $this->token_secret_enc;
    }
}
