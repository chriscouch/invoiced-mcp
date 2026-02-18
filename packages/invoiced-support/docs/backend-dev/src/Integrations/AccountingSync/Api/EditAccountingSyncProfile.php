<?php

namespace App\Integrations\AccountingSync\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationException;

/**
 * @extends AbstractEditModelApiRoute<AccountingSyncProfile>
 */
class EditAccountingSyncProfile extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'integration' => new RequestParameter(),
                'read_customers' => new RequestParameter(),
                'write_customers' => new RequestParameter(),
                'read_invoices' => new RequestParameter(),
                'read_invoices_as_drafts' => new RequestParameter(),
                'read_pdfs' => new RequestParameter(),
                'write_invoices' => new RequestParameter(),
                'read_credit_notes' => new RequestParameter(),
                'write_credit_notes' => new RequestParameter(),
                'read_payments' => new RequestParameter(),
                'write_payments' => new RequestParameter(),
                'write_convenience_fees' => new RequestParameter(),
                'payment_accounts' => new RequestParameter(),
                'invoice_start_date' => new RequestParameter(),
                'parameters' => new RequestParameter(),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: AccountingSyncProfile::class,
            features: ['accounting_sync'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $parameters = $context->requestParameters;
        if (isset($parameters['integration'])) {
            try {
                $parameters['integration'] = IntegrationType::fromString($parameters['integration']);
                $context = $context->withRequestParameters($parameters);
            } catch (IntegrationException $e) {
                throw new InvalidRequest($e->getMessage());
            }
        }

        return parent::buildResponse($context);
    }
}
