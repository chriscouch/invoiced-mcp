<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\CompanyBankAccount;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\QueryParameter;
use App\Core\Orm\Query;
use App\Integrations\Plaid\Models\PlaidItem;

/**
 * @extends AbstractListModelsApiRoute<CompanyBankAccount>
 */
class ListCompanyBankAccountsApiRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [...$this->getBaseQueryParameters(),
                'hasPlaid' => new QueryParameter(
                    types: ['numeric'],
                    default: 0,
                ),
            ],
            requestParameters: [],
            requiredPermissions: [],
            modelClass: CompanyBankAccount::class,
            filterableProperties: ['default'],
            features: ['accounts_payable'],
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context)->where('deleted', false);

        if (1 == $context->queryParameters['hasPlaid']) {
            $query->join(PlaidItem::class, 'CompanyBankAccounts.plaid_id', 'PlaidBankAccountLinks.id')
                ->where('PlaidBankAccountLinks.verified', 1);
        }

        return $query;
    }
}
