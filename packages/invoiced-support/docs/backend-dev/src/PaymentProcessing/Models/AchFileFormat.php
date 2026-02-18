<?php

namespace App\PaymentProcessing\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;

/**
 * @property string $name
 * @property string $immediate_destination
 * @property string $immediate_destination_name
 * @property string $immediate_origin
 * @property string $immediate_origin_name
 * @property string $company_name
 * @property string $company_id
 * @property string $company_discretionary_data
 * @property string $company_entry_description
 * @property string $originating_dfi_identification
 * @property string $default_sec_code
 */
class AchFileFormat extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                validate: [
                    ['unique', 'column' => 'name'],
                    ['string', 'min' => 1, 'max' => 255],
                ],
            ),
            'immediate_destination' => new Property(
                validate: ['string', 'min' => 1, 'max' => 10],
            ),
            'immediate_destination_name' => new Property(
                validate: ['string', 'min' => 1, 'max' => 23],
            ),
            'immediate_origin' => new Property(
                validate: ['string', 'min' => 1, 'max' => 10],
            ),
            'immediate_origin_name' => new Property(
                validate: ['string', 'min' => 1, 'max' => 23],
            ),
            'company_name' => new Property(
                validate: ['string', 'min' => 1, 'max' => 16],
            ),
            'company_id' => new Property(
                validate: ['string', 'min' => 1, 'max' => 10],
            ),
            'company_discretionary_data' => new Property(
                validate: ['string', 'min' => 1, 'max' => 20],
            ),
            'company_entry_description' => new Property(
                validate: ['string', 'min' => 1, 'max' => 10],
            ),
            'originating_dfi_identification' => new Property(
                validate: ['string', 'min' => 1, 'max' => 8],
            ),
            'default_sec_code' => new Property(
                validate: ['string', 'min' => 3, 'max' => 3],
            ),
        ];
    }
}
