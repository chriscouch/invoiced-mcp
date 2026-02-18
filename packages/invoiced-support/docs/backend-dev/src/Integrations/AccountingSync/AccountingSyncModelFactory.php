<?php

namespace App\Integrations\AccountingSync;

use App\Companies\Models\Company;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Models\IntacctAccount;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\NetSuite\Models\NetSuiteAccount;
use App\Integrations\OAuth\Models\OAuthAccount;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Integrations\Xero\Models\XeroAccount;
use App\Integrations\Xero\Models\XeroSyncProfile;
use App\Core\Orm\Model;

class AccountingSyncModelFactory
{
    public static function getAccount(IntegrationType $integrationType, Company $company): ?Model
    {
        return match ($integrationType) {
            IntegrationType::Intacct => IntacctAccount::queryWithTenant($company)->oneOrNull(),
            IntegrationType::NetSuite => NetSuiteAccount::queryWithTenant($company)->oneOrNull(),
            IntegrationType::QuickBooksOnline => QuickBooksAccount::queryWithTenant($company)->oneOrNull(),
            IntegrationType::Xero => XeroAccount::queryWithTenant($company)->oneOrNull(),
            default => OAuthAccount::queryWithTenant($company)->where('integration', $integrationType->value)->oneOrNull(),
        };
    }

    public static function getSyncProfile(IntegrationType $integrationType, Company $company): ?AccountingSyncProfile
    {
        return match ($integrationType) {
            IntegrationType::Intacct => IntacctSyncProfile::queryWithTenant($company)->oneOrNull(),
            IntegrationType::QuickBooksOnline => QuickBooksOnlineSyncProfile::queryWithTenant($company)->oneOrNull(),
            IntegrationType::Xero => XeroSyncProfile::queryWithTenant($company)->oneOrNull(),
            default => AccountingSyncProfile::queryWithTenant($company)->where('integration', $integrationType->value)->oneOrNull(),
        };
    }
}
