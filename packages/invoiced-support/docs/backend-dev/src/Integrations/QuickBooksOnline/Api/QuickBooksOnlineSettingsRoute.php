<?php

namespace App\Integrations\QuickBooksOnline\Api;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Libs\IntegrationFactory;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\Services\QuickbooksOnline;
use stdClass;

/**
 * Endpoint to retrieve data from QBO needed
 * to build the settings UI.
 */
class QuickBooksOnlineSettingsRoute extends AbstractApiRoute
{
    const STRING_TYPE = 'StringType';

    /**
     * This only allows income accounts to be chosen of the given
     * account types. When doing this check it removes any spaces
     * from the value returned by QuickBooks. Example:
     * "Other Current Liability" -> "OtherCurrentLiability".
     *
     * All the available account types as documented here:
     * https://developer.intuit.com/docs/api/accounting/account
     */
    public static array $incomeAccountTypes = [
        'Income',
        'OtherCurrentLiability',
        'LongTermLiability',
    ];

    public static array $depositToAccountTypes = [
        'Bank',
        'OtherCurrentAsset',
    ];

    public function __construct(
        private IntegrationFactory $integrations,
        private QuickBooksApi $quickBooksApi,
        private TenantContext $tenant,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            features: ['accounting_sync'],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        /** @var QuickbooksOnline $integration */
        $integration = $this->integrations->get(IntegrationType::QuickBooksOnline, $this->tenant->get());
        if (!$integration->isConnected()) {
            throw new InvalidRequest('QuickBooks account is not connected', 404);
        }

        /** @var QuickBooksAccount $qboAccount */
        $qboAccount = $integration->getAccount();
        $this->quickBooksApi->setAccount($qboAccount);

        // fetch the data we need
        try {
            $chartOfAccounts = [];
            $startPosition = 1;
            do {
                $result = $this->quickBooksApi->getChartOfAccounts($startPosition);
                $chartOfAccounts = array_merge($chartOfAccounts, $result);
                $startPosition += count($result);
            } while (count($result) >= 1000);
            $taxCodes = $this->quickBooksApi->getTaxCodes();
            $preferences = $this->quickBooksApi->getPreferences();
        } catch (IntegrationApiException $e) {
            throw new ApiError($e->getMessage());
        }

        // parse the chart of accounts into each type we need
        $incomeAccounts = [];
        $depositToAccounts = [];
        foreach ($chartOfAccounts as $account) {
            $account2 = [
                'name' => (string) $account->Name,
                'fully_qualified_name' => (string) $account->FullyQualifiedName,
                'subaccount' => 'true' == $account->SubAccount,
            ];

            $accountType = (string) str_replace(' ', '', $account->AccountType);
            if (in_array($accountType, self::$incomeAccountTypes)) {
                $incomeAccounts[] = $account2;
            } elseif (in_array($accountType, self::$depositToAccountTypes)) {
                $depositToAccounts[] = $account2;
            }
        }

        // parse the tax rates
        $_taxCodes = [];
        foreach ($taxCodes as $taxCode) {
            $_taxCodes[] = [
                'name' => (string) $taxCode->Name,
                'description' => (string) $taxCode->Description,
            ];
        }

        // parse the custom fields
        $customFields = [];
        foreach ($preferences->SalesFormsPrefs->CustomField as $customFieldsList) {
            foreach ($customFieldsList->CustomField as $customField) {
                if ($field = $this->parseCustomField($customField)) {
                    $customFields[] = $field;
                }
            }
        }

        return [
            'income_accounts' => $incomeAccounts,
            'deposit_to_accounts' => $depositToAccounts,
            'tax_codes' => $_taxCodes,
            'custom_fields' => $customFields,
        ];
    }

    private function parseCustomField(stdClass $customField): ?array
    {
        // QuickBooks is really weird about how custom fields are represented. We
        // need to pull out the definition ID from this kind of value:
        // SalesFormsPrefs.SalesCustomName{DEFINITION_ID}
        // The definition ID is the numeric part.
        // The boolean type controls whether the field is enabled
        // and the string type is the actual name of the custom field
        $name = (string) $customField->Name;
        $type = (string) $customField->Type;

        if (self::STRING_TYPE != $type) {
            return null;
        }

        if (preg_match('/[\D]*([\d]+)[\D]*/i', $name, $matches)) {
            return [
                'id' => $matches[1],
                'name' => (string) $customField->StringValue,
            ];
        }

        return null;
    }
}
