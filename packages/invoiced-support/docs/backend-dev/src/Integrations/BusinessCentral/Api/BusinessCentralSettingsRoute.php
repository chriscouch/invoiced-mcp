<?php

namespace App\Integrations\BusinessCentral\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Integrations\BusinessCentral\BusinessCentralApi;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Libs\IntegrationFactory;
use App\Integrations\Services\BusinessCentral;

/**
 * Endpoint to retrieve data from Business Central needed
 * to build the settings UI.
 */
class BusinessCentralSettingsRoute extends AbstractApiRoute
{
    public function __construct(
        private IntegrationFactory $integrations,
        private BusinessCentralApi $businessCentralApi,
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
        /** @var BusinessCentral $integration */
        $integration = $this->integrations->get(IntegrationType::BusinessCentral, $this->tenant->get());
        $account = $integration->getAccount();
        if (!$account) {
            throw new InvalidRequest('Business Central account is not connected', 404);
        }

        // fetch the data we need
        try {
            $customerPaymentJournals = $this->businessCentralApi->getCustomerPaymentJournals($account);
        } catch (IntegrationApiException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        // parse the chart of accounts into each type we need
        $customerPaymentJournals2 = [];
        foreach ($customerPaymentJournals as $customerPaymentJournal) {
            $customerPaymentJournals2[] = [
                'name' => $customerPaymentJournal->displayName,
                'code' => $customerPaymentJournal->code,
                'id' => $customerPaymentJournal->id,
            ];
        }

        return [
            'customer_payment_journals' => $customerPaymentJournals2,
        ];
    }
}
