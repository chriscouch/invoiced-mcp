<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Ledger\VendorBalanceGenerator;
use App\AccountsPayable\Models\Vendor;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class VendorBalanceApiRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(
        private VendorBalanceGenerator $balanceGenerator,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Vendor::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        /** @var Vendor $vendor */
        $vendor = parent::buildResponse($context);

        $totalBalance = $this->balanceGenerator->generate($vendor);

        return [
            'total_balance' => $totalBalance->toDecimal(),
            'currency' => $totalBalance->currency,
        ];
    }
}
