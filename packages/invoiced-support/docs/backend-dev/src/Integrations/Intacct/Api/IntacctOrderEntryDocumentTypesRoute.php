<?php

namespace App\Integrations\Intacct\Api;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Libs\IntegrationFactory;
use App\Integrations\Services\Intacct;

class IntacctOrderEntryDocumentTypesRoute extends AbstractApiRoute
{
    private const CLASS_INVOICE = 'Invoice';
    private const CATEGORY_INVOICE = 'Invoice';
    private const CATEGORY_RETURN = 'Return';

    public function __construct(
        private IntegrationFactory $integrations,
        private IntacctApi $intacctApi,
        private TenantContext $tenant,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            features: ['intacct'],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        /** @var Intacct $integration */
        $integration = $this->integrations->get(IntegrationType::Intacct, $this->tenant->get());
        if (!$integration->isConnected()) {
            throw new InvalidRequest('Intacct account is not connected', 404);
        }

        $this->intacctApi->setAccount($integration->getAccount()); /* @phpstan-ignore-line */

        // fetch the chart of accounts and tax rates
        try {
            $transactionDefinitions = $this->intacctApi->getOrderEntryTransactionDefinitions(['DOCCLASS', 'CATEGORY', 'DOCID']);
        } catch (IntegrationApiException $e) {
            throw new ApiError('Could not load order entry transaction definitions from Intacct: '.$e->getMessage());
        }

        // parse the transaction definitions into each type we need
        $invoiceTypes = [];
        $returnTypes = [];
        foreach ($transactionDefinitions->getData() as $entry) {
            if (self::CLASS_INVOICE != $entry->{'DOCCLASS'}) {
                continue;
            }

            if (self::CATEGORY_INVOICE == $entry->{'CATEGORY'}) {
                $invoiceTypes[] = (string) $entry->{'DOCID'};
            }

            if (self::CATEGORY_RETURN == $entry->{'CATEGORY'}) {
                $returnTypes[] = (string) $entry->{'DOCID'};
            }
        }

        return [
            'invoice_types' => $invoiceTypes,
            'return_types' => $returnTypes,
        ];
    }
}
