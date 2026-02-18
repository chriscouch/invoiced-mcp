<?php

namespace App\CashApplication;

use App\CashApplication\Models\CashApplicationSettings;
use App\Companies\Models\Company;
use App\Core\Entitlements\Exception\InstallProductException;
use App\Core\Entitlements\Interfaces\InstallProductInterface;
use Doctrine\DBAL\Connection;

class CashApplicationProduct implements InstallProductInterface
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
        $existing = $this->database->fetchOne('SELECT COUNT(*) FROM CashApplicationSettings WHERE tenant_id=:tenantId', [
            'tenantId' => $company->id,
        ]);
        if ($existing > 0) {
            return;
        }

        $settings = new CashApplicationSettings();
        $settings->tenant_id = (int) $company->id();
        if (!$settings->save()) {
            throw new InstallProductException('Could not create settings: '.$settings->getErrors());
        }
    }
}
