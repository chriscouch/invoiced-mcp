<?php

namespace App\Core\Authentication\LoginStrategy;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Libs\LoginHelper;
use App\Core\Authentication\Libs\SAMLAvailableCompanies;
use App\Core\Authentication\Models\User;
use App\Core\Authentication\Saml\SamlAuthFactory;
use App\Core\Entitlements\Models\Feature;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use Psr\Log\LoggerAwareInterface;

class SamlV2LoginStrategy extends AbstractSamlLoginStrategy implements LoggerAwareInterface, StatsdAwareInterface
{
    public function __construct(
        SamlAuthFactory $authFactory,
        LoginHelper $loginHelper,
        private readonly SAMLAvailableCompanies $SAMLAvailableCompanies)
    {
        parent::__construct($authFactory, $loginHelper);
    }

    public function getId(): string
    {
        return 'saml2';
    }

    /**
     * @throws AuthException
     *
     * @return ?int[]
     */
    protected function getAvailableCompanies(User $user): ?array
    {
        $availableCompanies = $this->SAMLAvailableCompanies->get($user);
        $companyIds = array_map(fn ($m) => $m->company_id, $availableCompanies);

        $samlV1 = [];
        if ($companyIds) {
            $samlV1 = array_map(fn (Feature $m) => $m->tenant_id, Feature::queryWithoutMultitenancyUnsafe()
                ->where('tenant_id', $companyIds)
                ->where('feature', 'saml1')
                ->where('enabled', 1)
                ->all()
                ->toArray());
        }

        $activeCompanies = [];
        foreach ($availableCompanies as $settings) {
            if (in_array($settings->company_id, $samlV1)) {
                continue;
            }
            try {
                $this->parseResponse($settings);
            } catch (AuthException) {
                continue;
            }
            $activeCompanies[] = $settings->company_id;
        }

        if (!$activeCompanies) {
            throw new AuthException('You do not have access to any companies.');
        }

        return $activeCompanies;
    }
}
