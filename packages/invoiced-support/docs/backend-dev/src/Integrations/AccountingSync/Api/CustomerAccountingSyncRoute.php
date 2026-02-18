<?php

namespace App\Integrations\AccountingSync\Api;

use App\Companies\Models\Company;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\AccountingSync\ReadSync\TransformerHelper;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;

class CustomerAccountingSyncRoute extends AbstractAccountingSyncRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
        );
    }

    public static function transform(array $input, Company $company, string $accountingSystem = ''): AccountingCustomer
    {
        // Accounting System / Integration Type
        $accountingSystem = $input['accounting_system'] ?? $accountingSystem;
        unset($input['accounting_system']);
        $integrationType = self::getAccountingSystemType($accountingSystem);

        return TransformerHelper::makeCustomer($integrationType, $input);
    }
}
