<?php

namespace App\SubscriptionBilling;

use App\Companies\Models\Company;
use App\Core\Entitlements\Exception\InstallProductException;
use App\Core\Entitlements\Interfaces\InstallProductInterface;
use App\SubscriptionBilling\Models\MrrVersion;
use App\SubscriptionBilling\Models\SubscriptionBillingSettings;
use Doctrine\DBAL\Connection;

class SubscriptionBillingProduct implements InstallProductInterface
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
        $existing = $this->database->fetchOne('SELECT COUNT(*) FROM SubscriptionBillingSettings WHERE tenant_id=:tenantId', [
            'tenantId' => $company->id,
        ]);
        if ($existing > 0) {
            return;
        }

        // create an MRR version
        $mrrVersion = new MrrVersion();
        $mrrVersion->tenant_id = $company->id;
        $mrrVersion->currency = $company->currency;
        if (!$mrrVersion->save()) {
            throw new InstallProductException('Could not create MRR version: '.$mrrVersion->getErrors());
        }

        $settings = new SubscriptionBillingSettings();
        $settings->tenant_id = $company->id;
        $settings->mrr_version = $mrrVersion;
        if (!$settings->save()) {
            throw new InstallProductException('Could not create settings: '.$settings->getErrors());
        }
    }
}
