<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\CompanyBankAccount;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\Plaid\Libs\RemovePlaidItem;

/**
 * @extends AbstractDeleteModelApiRoute<CompanyBankAccount>
 */
class DeleteCompanyBankAccountApiRoute extends AbstractDeleteModelApiRoute
{
    public function __construct(private RemovePlaidItem $operation)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            modelClass: CompanyBankAccount::class,
            features: ['accounts_payable'],
        );
    }

    public function retrieveModel(ApiCallContext $context)
    {
        /** @var CompanyBankAccount $bankAccount */
        $bankAccount = parent::retrieveModel($context);

        // remove the item on Plaid
        if ($plaidItem = $bankAccount->plaid) {
            $this->operation->remove($plaidItem);
        }

        return $bankAccount;
    }
}
