<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Exception\AccountsPayablePaymentException;
use App\AccountsPayable\Models\CompanyCard;
use App\AccountsPayable\Operations\CreateCompanyCard;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * @extends AbstractCreateModelApiRoute<CompanyCard>
 */
class CreateCompanyCardApiRoute extends AbstractCreateModelApiRoute
{
    public function __construct(
        private CreateCompanyCard $create,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [
                'setup_intent' => new RequestParameter(
                    required: true,
                    types: ['string']
                ),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: CompanyCard::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): CompanyCard
    {
        try {
            return $this->create->finish($context->requestParameters['setup_intent']);
        } catch (AccountsPayablePaymentException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
