<?php

namespace App\Integrations\Intacct\Api;

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
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class IntacctSettingsRoute extends AbstractApiRoute implements LoggerAwareInterface
{
    use LoggerAwareTrait;

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
            requiredPermissions: ['settings.edit'],
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

        // fetch the chart of accounts
        try {
            $chartOfAccounts = $this->intacctApi->getChartOfAccounts(['ACCOUNTNO', 'TITLE']);
        } catch (IntegrationApiException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        // parse the chart of accounts into each type we need
        $glAccounts = [];
        foreach ($chartOfAccounts->getData() as $account) {
            $glAccounts[] = [
                'name' => (string) $account->{'TITLE'},
                'code' => (string) $account->{'ACCOUNTNO'},
            ];
        }

        // fetch the checking accounts
        $bankAccounts = [];

        try {
            $checkingAccounts = $this->intacctApi->getCheckingAccounts(['BANKACCOUNTID', 'BANKNAME']);

            foreach ($checkingAccounts->getData() as $account) {
                $bankAccounts[] = [
                    'name' => (string) $account->{'BANKNAME'},
                    'code' => (string) $account->{'BANKACCOUNTID'},
                ];
            }
        } catch (IntegrationApiException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        $entities = [];
        $extra = $integration->getExtra();
        if (!empty($extra->sync_all_entities)) {
            try {
                $intacctEntities = $this->intacctApi->getEntities(['RECORDNO', 'LOCATIONID', 'NAME']);
                foreach ($intacctEntities->getData() as $entity) {
                    $entities[] = [
                        'id' => (string) $entity->{'LOCATIONID'},
                        'name' => (string) $entity->{'NAME'},
                    ];
                }
            } catch (IntegrationApiException $e) {
                $this->logger->error('Could not get Sage Intacct entities', ['exception' => $e]);
            }
        }

        return [
            'gl_accounts' => $glAccounts,
            'bank_accounts' => $bankAccounts,
            'entities' => $entities,
        ];
    }
}
