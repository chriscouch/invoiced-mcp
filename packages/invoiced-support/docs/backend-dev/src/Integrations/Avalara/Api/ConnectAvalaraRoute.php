<?php

namespace App\Integrations\Avalara\Api;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Avalara\AvalaraAccount;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Libs\IntegrationFactory;
use App\Integrations\Services\Avalara;
use App\SalesTax\Calculator\AvalaraTaxCalculator;
use App\SalesTax\Models\TaxRate;

class ConnectAvalaraRoute extends AbstractModelApiRoute
{
    public function __construct(
        private TenantContext $tenant,
        private IntegrationFactory $integrationFactory,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: AvalaraAccount::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        /** @var Avalara $integration */
        $integration = $this->integrationFactory->get(IntegrationType::Avalara, $this->tenant->get());
        $account = $integration->getAccount();
        if (!$account) {
            $account = new AvalaraAccount();
        }

        foreach ($context->requestParameters as $k => $v) {
            $account->$k = $v;
        }

        if ($account->save()) {
            // make avalara the default tax calculator
            $company = $this->tenant->get();
            $company->accounts_receivable_settings->tax_calculator = 'avalara';
            $company->accounts_receivable_settings->save();

            // create an AVATAX rate
            $taxRate = TaxRate::getCurrent(AvalaraTaxCalculator::TAX_CODE);
            if (!$taxRate) {
                $taxRate = new TaxRate();
                $taxRate->id = AvalaraTaxCalculator::TAX_CODE;
                $taxRate->name = 'Avalara Tax';
                $taxRate->is_percent = false;
                $taxRate->currency = null;
                $taxRate->value = 0;
                $taxRate->save();
            }

            return $account;
        }

        // get the first error
        if ($error = $this->getFirstError()) {
            throw $this->modelValidationError($error);
        }

        // no specific errors available, throw a server error
        throw new ApiError('There was an error creating the '.$this->getModelName().'.');
    }
}
