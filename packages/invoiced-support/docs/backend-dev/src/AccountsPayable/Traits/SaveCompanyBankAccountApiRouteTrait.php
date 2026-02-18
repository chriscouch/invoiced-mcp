<?php

namespace App\AccountsPayable\Traits;

use App\AccountsPayable\Enums\CheckStock;
use App\AccountsPayable\Libs\CompanyBankAccountSave;
use App\AccountsPayable\Models\CompanyBankAccount;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Integrations\Plaid\Libs\PlaidApi;

trait SaveCompanyBankAccountApiRouteTrait
{
    public function __construct(
        private readonly CompanyBankAccountSave $companyBankAccountSave,
        private readonly PlaidApi $plaidApi
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'check_number' => new RequestParameter(
                    types: ['numeric', 'null'],
                ),
                'name' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'check_layout' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'plaid_id' => new RequestParameter(
                    types: ['numeric', 'null'],
                ),
                'signature' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'default' => new RequestParameter(
                    types: ['numeric'],
                    default: 0,
                ),
                'token' => new RequestParameter(
                    types: ['string', 'null'],
                    default: null,
                ),
                'ach_file_format' => new RequestParameter(
                    types: ['numeric', 'null'],
                ),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: CompanyBankAccount::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): CompanyBankAccount
    {
        if (isset($context->requestParameters['check_layout'])) {
            foreach (CheckStock::cases() as $layout) {
                if ($layout->name == $context->requestParameters['check_layout']) {
                    $requestParameters = $context->requestParameters;
                    $requestParameters['check_layout'] = $layout;
                    $context = $context->withRequestParameters($requestParameters);
                }
            }
        }

        $bankAccount = parent::buildResponse($context);

        $hasPlaid = isset($context->requestParameters['plaid_id']) && $context->requestParameters['plaid_id'] ? $context->requestParameters['plaid_id'] : null;
        if ($hasPlaid) {
            $bankAccount = $this->companyBankAccountSave->save($bankAccount);
        }

        return $bankAccount;
    }
}
