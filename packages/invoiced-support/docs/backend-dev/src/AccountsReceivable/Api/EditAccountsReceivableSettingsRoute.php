<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\AccountsReceivableSettings;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Multitenant\TenantContext;

/**
 * @extends AbstractEditModelApiRoute<AccountsReceivableSettings>
 */
class EditAccountsReceivableSettingsRoute extends AbstractEditModelApiRoute
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'add_payment_plan_on_import' => new RequestParameter(),
                'aging_buckets' => new RequestParameter(),
                'aging_date' => new RequestParameter(),
                'allow_chasing' => new RequestParameter(),
                'auto_apply_credits' => new RequestParameter(),
                'autopay_delay_days' => new RequestParameter(),
                'bcc' => new RequestParameter(),
                'chase_new_invoices' => new RequestParameter(),
                'chase_schedule' => new RequestParameter(),
                'debit_cards_only' => new RequestParameter(),
                'default_collection_mode' => new RequestParameter(),
                'default_consolidated_invoicing' => new RequestParameter(),
                'default_customer_type' => new RequestParameter(),
                'default_template_id' => new RequestParameter(),
                'default_theme_id' => new RequestParameter(),
                'email_provider' => new RequestParameter(),
                'payment_retry_schedule' => new RequestParameter(),
                'payment_terms' => new RequestParameter(),
                'reply_to_inbox_id' => new RequestParameter(),
                'saved_cards_require_cvc' => new RequestParameter(),
                'tax_calculator' => new RequestParameter(),
                'transactions_inherit_invoice_metadata' => new RequestParameter(),
                'unit_cost_precision' => new RequestParameter(),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: AccountsReceivableSettings::class,
            features: ['accounts_receivable'],
        );
    }

    public function retrieveModel(ApiCallContext $context): AccountsReceivableSettings
    {
        return $this->tenant->get()->accounts_receivable_settings;
    }
}
