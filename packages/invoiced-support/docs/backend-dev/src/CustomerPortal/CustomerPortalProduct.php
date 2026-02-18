<?php

namespace App\CustomerPortal;

use App\CustomerPortal\Models\CustomerPortalSettings;
use App\Companies\Models\Company;
use App\Core\Entitlements\Exception\InstallProductException;
use App\Core\Entitlements\Interfaces\InstallProductInterface;
use Doctrine\DBAL\Connection;

class CustomerPortalProduct implements InstallProductInterface
{
    public function __construct(
        private Connection $database,
    ) {
    }

    public function install(Company $company): void
    {
        $this->createSettings($company);
    }

    /**
     * Creates settings for the company.
     */
    public function createSettings(Company $company): void
    {
        // skip if settings already exists
        $existing = $this->database->fetchOne('SELECT COUNT(*) FROM CustomerPortalSettings WHERE tenant_id=:tenantId', [
            'tenantId' => $company->id,
        ]);
        if ($existing > 0) {
            return;
        }

        $settings = new CustomerPortalSettings();
        $settings->tenant_id = (int) $company->id();
        if (!$settings->save()) {
            throw new InstallProductException('Could not create settings: '.$settings->getErrors());
        }
    }
}
