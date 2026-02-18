<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\PaymentProcessing\Models\AchFileFormat;

/**
 * @extends AbstractCreateModelApiRoute<AchFileFormat>
 */
class CreateAchFileFormatRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'name' => new RequestParameter(
                    required: true,
                    types: ['string'],
                ),
                'immediate_destination' => new RequestParameter(
                    required: true,
                ),
                'immediate_destination_name' => new RequestParameter(
                    required: true,
                ),
                'immediate_origin' => new RequestParameter(
                    required: true,
                ),
                'immediate_origin_name' => new RequestParameter(
                    required: true,
                ),
                'company_name' => new RequestParameter(
                    required: true,
                ),
                'company_id' => new RequestParameter(
                    required: true,
                ),
                'company_discretionary_data' => new RequestParameter(
                    required: true,
                ),
                'company_entry_description' => new RequestParameter(
                    required: true,
                ),
                'originating_dfi_identification' => new RequestParameter(
                    required: true,
                ),
                'default_sec_code' => new RequestParameter(
                    required: true,
                ),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: AchFileFormat::class,
            features: ['direct_ach'],
        );
    }
}
