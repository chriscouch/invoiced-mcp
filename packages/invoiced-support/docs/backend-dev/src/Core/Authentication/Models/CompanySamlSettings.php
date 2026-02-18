<?php

namespace App\Core\Authentication\Models;

use App\Companies\Models\Company;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property Company $company
 * @property int     $company_id
 * @property string  $domain
 * @property bool    $enabled
 * @property int     $entity_id
 * @property string  $sso_url
 * @property string  $slo_url
 * @property string  $cert
 * @property bool    $disable_non_sso
 */
class CompanySamlSettings extends Model
{
    protected static function getIDProperties(): array
    {
        return ['company_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'company' => new Property(
                required: true,
                in_array: false,
                belongs_to: Company::class,
            ),
            'domain' => new Property(
                required: true,
                validate: ['callable', 'fn' => [self::class, 'validateDomain']],
                default: '',
            ),
            'enabled' => new Property(
                type: Type::BOOLEAN,
                required: true,
                default: false,
            ),
            'entity_id' => new Property(
                required: true,
            ),
            'sso_url' => new Property(
                required: true,
            ),
            'slo_url' => new Property(
                null: true,
            ),
            'cert' => new Property(
                required: true,
            ),
            'disable_non_sso' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
        ];
    }

    public static function validateDomain(string $domain, array $options, self $model): bool
    {
        return !$model->company->features->has('saml1') || 0 === self::where('domain', $domain)
            ->where('company_id', $model->company_id, '<>')
            ->count();
    }
}
