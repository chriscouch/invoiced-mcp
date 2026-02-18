<?php

namespace App\CustomerPortal\Api;

use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Multitenant\TenantContext;
use App\CustomerPortal\Models\CustomerPortalSettings;

/**
 * @extends AbstractEditModelApiRoute<CustomerPortalSettings>
 */
class EditCustomerPortalSettingsRoute extends AbstractEditModelApiRoute
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'allow_advance_payments' => new RequestParameter(),
                'allow_autopay_enrollment' => new RequestParameter(),
                'allow_billing_portal_cancellations' => new RequestParameter(),
                'allow_billing_portal_profile_changes' => new RequestParameter(),
                'allow_editing_contacts' => new RequestParameter(),
                'allow_invoice_disputes' => new RequestParameter(),
                'allow_invoice_payment_selector' => new RequestParameter(),
                'allow_partial_payments' => new RequestParameter(),
                'billing_portal_show_company_name' => new RequestParameter(),
                'customer_portal_auth_url' => new RequestParameter(),
                'enabled' => new RequestParameter(),
                'google_analytics_id' => new RequestParameter(),
                'include_sub_customers' => new RequestParameter(),
                'invoice_payment_to_item_selection' => new RequestParameter(),
                'require_authentication' => new RequestParameter(),
                'show_powered_by' => new RequestParameter(),
                'welcome_message' => new RequestParameter(),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: CustomerPortalSettings::class,
            features: ['billing_portal'],
        );
    }

    public function retrieveModel(ApiCallContext $context): CustomerPortalSettings
    {
        return $this->tenant->get()->customer_portal_settings;
    }
}
