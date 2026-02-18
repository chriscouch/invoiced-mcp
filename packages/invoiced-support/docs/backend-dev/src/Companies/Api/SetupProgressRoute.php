<?php

namespace App\Companies\Api;

use App\AccountsReceivable\Models\Invoice;
use App\Companies\Enums\VerificationStatus;
use App\Companies\Models\Company;
use App\Companies\Models\CompanyEmailAddress;
use App\Companies\Models\Member;
use App\Core\Multitenant\TenantContext;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Imports\Models\Import;
use App\Integrations\Libs\IntegrationFactory;
use App\PaymentProcessing\Models\PaymentMethod;

class SetupProgressRoute extends AbstractRetrieveModelApiRoute
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
            requiredPermissions: [],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $this->setModel($this->tenant->get());

        $company = parent::buildResponse($context);

        return [
            'branding' => $this->hasCompletedBranding($company),
            'payments' => $this->hasCompletedPayments(),
            'import' => $this->hasCompletedImport(),
            'verify' => $this->hasCompletedVerify($company),
            'send' => $this->hasCompletedSend(),
            'accounting' => $this->hasCompletedAccounting($company),
            'team' => $this->hasCompletedTeam(),
        ];
    }

    private function hasCompletedBranding(Company $company): bool
    {
        return $company->address1 || $company->logo;
    }

    private function hasCompletedPayments(): bool
    {
        return PaymentMethod::where('enabled', true)->count() > 0;
    }

    private function hasCompletedImport(): bool
    {
        return Import::count() > 0;
    }

    private function hasCompletedVerify(Company $company): bool
    {
        return VerificationStatus::Verified == CompanyEmailAddress::getVerificationStatus($company);
    }

    private function hasCompletedSend(): bool
    {
        return Invoice::where('sent', true)->count() > 0;
    }

    private function hasCompletedAccounting(Company $company): bool
    {
        foreach ($this->integrationFactory->all($company) as $integration) {
            if ($integration->isConnected()) {
                return true;
            }
        }

        return false;
    }

    private function hasCompletedTeam(): bool
    {
        return Member::count() > 1;
    }
}
