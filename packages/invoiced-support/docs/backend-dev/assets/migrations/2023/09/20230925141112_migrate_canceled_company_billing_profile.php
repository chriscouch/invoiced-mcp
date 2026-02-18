<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MigrateCanceledCompanyBillingProfile extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('UPDATE CanceledCompanies SET billing_profile_id=(SELECT id FROM BillingProfiles WHERE stripe_customer=CanceledCompanies.stripe_customer) WHERE stripe_customer IS NOT NULL AND billing_profile_id IS NULL');
        $this->execute('UPDATE CanceledCompanies SET billing_profile_id=(SELECT id FROM BillingProfiles WHERE invoiced_customer=CanceledCompanies.invoiced_customer) WHERE invoiced_customer IS NOT NULL AND billing_profile_id IS NULL');
    }
}
