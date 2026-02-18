<?php

namespace App\Companies\Libs;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\Authentication\Models\User;
use App\Core\Multitenant\TenantContext;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Libs\IntegrationFactory;
use App\PaymentProcessing\Models\PaymentMethod;
use Carbon\CarbonImmutable;

class CompanyCustomerServiceProfile
{
    public function __construct(
        private TenantContext $tenant,
        private IntegrationFactory $integrationFactory,
    ) {
    }

    /**
     * Builds a profile of this company for customer service purposes.
     */
    public function build(Company $company, ?User $user): array
    {
        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        // plan details
        $products = $company->features->allProducts();

        // get the payment gateways used
        $gateways = [];
        $creditCard = PaymentMethod::instance($company, PaymentMethod::CREDIT_CARD);
        $ach = PaymentMethod::instance($company, PaymentMethod::ACH);
        $directDebit = PaymentMethod::instance($company, PaymentMethod::DIRECT_DEBIT);
        if ($creditCard->enabled) {
            $gateways[] = $creditCard->gateway;
        }
        if ($ach->enabled) {
            $gateways[] = $ach->gateway;
        }
        if ($directDebit->enabled) {
            $gateways[] = $directDebit->gateway;
        }
        $gateways = array_unique($gateways);

        // get the # of users
        $totalUsers = Member::queryWithTenant($company)
            ->where('expires', 0)
            ->count();

        // get the user's role
        $roleName = null;
        if ($user) {
            $member = Member::getForUser($user);
            if ($member instanceof Member) {
                $role = Role::queryWithTenant($company)
                    ->where('id', $member->role)
                    ->oneOrNull();
                $roleName = $role instanceof Role ? $role->name : null;
            }
        }

        // get recent accounting integration errors
        $reconciliationErrors = ReconciliationError::queryWithTenant($company)
            ->where('timestamp', (new CarbonImmutable('-7 days'))->getTimestamp(), '>=')
            ->count();

        // get a list of installed integrations
        $integrations = [];
        foreach ($this->integrationFactory->all($company) as $integrationId => $integration) {
            if ($integration->isConnected()) {
                $integrations[] = IntegrationType::fromString($integrationId)->toHumanName();
            }
        }

        return [
            'id' => $company->id(),
            'name' => $company->name,
            'modules' => count($products) > 0 ? implode(', ', $products) : null,
            'status' => $company->billingStatus()->value,
            'total_members' => $totalUsers,
            'time_zone' => $company->time_zone,
            'role' => $roleName,
            'payment_gateways' => count($gateways) > 0 ? implode(', ', $gateways) : null,
            'invoiced_payments' => [],
            'accounting_system' => count($integrations) > 0 ? implode(', ', $integrations) : null,
            'reconciliation_errors' => $reconciliationErrors,
        ];
    }
}
