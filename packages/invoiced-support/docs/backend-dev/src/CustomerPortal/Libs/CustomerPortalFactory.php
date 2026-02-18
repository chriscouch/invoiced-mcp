<?php

namespace App\CustomerPortal\Libs;

use App\AccountsReceivable\Libs\CustomerHierarchy;
use App\Companies\Libs\CompanyRepository;
use App\Companies\Models\Company;
use App\Core\DomainRouter;

readonly class CustomerPortalFactory
{
    public function __construct(
        private CompanyRepository $companyRepository,
        private CustomerHierarchy $hierarchy
    ) {
    }

    /**
     * Gets a customer portal instance for a company.
     */
    public function make(Company $company): CustomerPortal
    {
        return new CustomerPortal($company, $this->hierarchy);
    }

    /**
     * Tries to get a customer portal instance for a given username.
     */
    public function makeForUsername(string $username): ?CustomerPortal
    {
        // determine if this is a request for a custom domain
        $customDomainPrefix = DomainRouter::CUSTOM_DOMAIN_PREFIX;
        if (str_starts_with($username, $customDomainPrefix)) {
            $domain = substr($username, strlen($customDomainPrefix));
            $company = $this->companyRepository->getForCustomDomain($domain);
        } else {
            $company = $this->companyRepository->getForUsername($username);
        }

        if (!$company) {
            return null;
        }

        return $this->make($company);
    }
}
