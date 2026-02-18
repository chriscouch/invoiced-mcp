<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Ledger\BillBalanceGenerator;
use App\AccountsPayable\Models\VendorCredit;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<array>
 */
class VendorCreditBalanceApiRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(
        private BillBalanceGenerator $balanceGenerator,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: [],
            modelClass: VendorCredit::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        /** @var VendorCredit $vendorCredit */
        $vendorCredit = parent::buildResponse($context);

        $totalBalance = $this->balanceGenerator->getBalance($vendorCredit);
        $transactions = $this->balanceGenerator->getTransactions($vendorCredit);

        return [
            'balance' => $totalBalance->toDecimal(),
            'currency' => $totalBalance->currency,
            'transactions' => $transactions,
        ];
    }
}
