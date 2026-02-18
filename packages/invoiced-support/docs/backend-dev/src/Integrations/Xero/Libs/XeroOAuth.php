<?php

namespace App\Integrations\Xero\Libs;

use App\Integrations\OAuth\AbstractOAuthIntegration;
use App\Integrations\Xero\Models\XeroAccount;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class XeroOAuth extends AbstractOAuthIntegration
{
    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        HttpClientInterface $httpClient,
        array $settings,
        private XeroApi $xeroApi,
    ) {
        parent::__construct($urlGenerator, $httpClient, $settings);
    }

    public function getRedirectUrl(): string
    {
        // HTTPS is required of redirect URLs
        return str_replace('http://', 'https://', parent::getRedirectUrl());
    }

    public function getSuccessRedirectUrl(): string
    {
        /** @var XeroAccount $account */
        $account = $this->getAccount();
        $orgs = $this->getOrganizations($account);

        if (count($orgs) > 1) {
            return $this->urlGenerator->generate('xero_select_org', ['companyId' => $account->tenant()->identifier], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $this->setOrg($orgs[0]['id'], $account);

        return parent::getSuccessRedirectUrl();
    }

    public function getAccount(): ?XeroAccount
    {
        return XeroAccount::oneOrNull();
    }

    public function makeAccount(): XeroAccount
    {
        return new XeroAccount();
    }

    public function setOrg(string $orgId, XeroAccount $account): void
    {
        $this->xeroApi->setAccount($account);
        $organization = $this->xeroApi->getOrganization($orgId);
        $account->name = $organization->Name;

        // Remove any previous Xero connections
        // with other Invoiced accounts because the connections
        // will no longer work anyways and it causes syncing issues.
        $duplicateAccounts = XeroAccount::queryWithoutMultitenancyUnsafe()
            ->where('tenant_id', $account->tenant(), '<>')
            ->where('organization_id', $orgId)
            ->all();
        foreach ($duplicateAccounts as $duplicateAccount) {
            $duplicateAccount->deleteOrFail();
        }

        $account->organization_id = $orgId;
        $account->saveOrFail();
    }

    public function getOrganizations(XeroAccount $account): array
    {
        $this->xeroApi->setAccount($account);
        $connections = $this->xeroApi->getConnections();

        $orgs = [];
        foreach ($connections as $connection) {
            $org = $this->xeroApi->getOrganization($connection->tenantId);
            $orgs[] = [
                'name' => $org->Name,
                'id' => $org->OrganisationID,
            ];
        }

        return $orgs;
    }
}
