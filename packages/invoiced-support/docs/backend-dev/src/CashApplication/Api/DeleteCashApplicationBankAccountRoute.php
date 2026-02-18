<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\CashApplicationBankAccount;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\Plaid\Libs\RemovePlaidItem;

/**
 * @extends AbstractDeleteModelApiRoute<CashApplicationBankAccount>
 */
class DeleteCashApplicationBankAccountRoute extends AbstractDeleteModelApiRoute
{
    public function __construct(private RemovePlaidItem $operation)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: CashApplicationBankAccount::class,
        );
    }

    public function retrieveModel(ApiCallContext $context): CashApplicationBankAccount
    {
        /** @var CashApplicationBankAccount $bankAccount */
        $bankAccount = parent::retrieveModel($context);

        // remove the item on Plaid
        $this->operation->remove($bankAccount->plaid_link);

        return $bankAccount;
    }
}
