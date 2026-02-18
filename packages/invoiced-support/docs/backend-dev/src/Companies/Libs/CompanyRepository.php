<?php

namespace App\Companies\Libs;

use App\Companies\Models\Company;
use Doctrine\DBAL\Connection;

class CompanyRepository
{
    public function __construct(private Connection $database)
    {
    }

    /**
     * Looks up a company for a given username. The company
     * must have an active subscription.
     */
    public function getForUsername(string $username): ?Company
    {
        if (!Company::validateUsername($username)) {
            return null;
        }

        $cId = $this->database->fetchOne('SELECT id FROM Companies WHERE username = ?', [$username]);
        if (!$cId) {
            return null;
        }

        $company = Company::findOrFail($cId);
        if (!$company->billingStatus()->isActive()) {
            return null;
        }

        return $company;
    }

    /**
     * Looks up a company for a given custom domain. The company
     * must have an active subscription.
     */
    public function getForCustomDomain(string $domain): ?Company
    {
        if (!$domain) {
            return null;
        }

        $cId = $this->database->fetchOne('SELECT id FROM Companies WHERE custom_domain = ?', [$domain]);
        if (!$cId) {
            return null;
        }

        $company = Company::findOrFail($cId);
        if (!$company->billingStatus()->isActive()) {
            return null;
        }

        return $company;
    }
}
