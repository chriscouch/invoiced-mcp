<?php

namespace App\Integrations\Avalara;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;

/**
 * @property string $name
 * @property string $company_code
 * @property string $account_id
 * @property string $license_key
 * @property string $license_key_enc
 * @property string $commit_mode
 */
class AvalaraAccount extends MultitenantModel
{
    use AutoTimestamps;

    const COMMIT_MODE_DISABLED = 'disable';
    const COMMIT_MODE_UNCOMMITTED = 'uncommitted';
    const COMMIT_MODE_COMMITTED = 'committed';

    protected static function getIDProperties(): array
    {
        return ['tenant_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
            ),
            'company_code' => new Property(
                required: true,
            ),
            'account_id' => new Property(
                required: true,
            ),
            'license_key_enc' => new Property(
                required: true,
                encrypted: true,
                in_array: false,
            ),
            'commit_mode' => new Property(
                validate: ['enum', 'choices' => ['disable', 'uncommitted', 'committed']],
                default: self::COMMIT_MODE_UNCOMMITTED,
            ),
        ];
    }

    /**
     * Sets the `license_key` property by encrypting it
     * and storing it on `license_key_enc`.
     *
     * @param string $secret
     *
     * @return mixed token
     */
    protected function setLicenseKeyValue($secret)
    {
        if ($secret) {
            $this->license_key_enc = $secret;
        }

        return $secret;
    }

    /**
     * Gets the decrypted `license_key` property value.
     *
     * @param mixed $secret current value
     *
     * @return mixed decrypted token
     */
    protected function getLicenseKeyValue($secret)
    {
        if ($secret || !$this->license_key_enc) {
            return $secret;
        }

        return $this->license_key_enc;
    }
}
