<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Ledger\BillBalanceGenerator;
use App\AccountsPayable\Models\Bill;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<array>
 */
class BillBalanceApiRoute extends AbstractRetrieveModelApiRoute
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
            modelClass: Bill::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        /** @var Bill $bill */
        $bill = parent::buildResponse($context);

        $totalBalance = $this->balanceGenerator->getBalance($bill);
        $transactions = $this->balanceGenerator->getTransactions($bill);

        return [
            'balance' => $totalBalance->toDecimal(),
            'currency' => $totalBalance->currency,
            'transactions' => $transactions,
        ];
    }
}
