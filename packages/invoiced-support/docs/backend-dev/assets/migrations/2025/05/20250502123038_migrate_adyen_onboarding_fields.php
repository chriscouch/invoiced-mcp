<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MigrateAdyenOnboardingFields extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('UPDATE AdyenAccounts SET onboarding_started_at=terms_of_service_acceptance_date WHERE account_holder_id IS NOT NULL');
        $this->execute('UPDATE AdyenAccounts SET activated_at=(SELECT MIN(created_at) FROM Charges WHERE gateway="flywire_payments" AND tenant_id=AdyenAccounts.tenant_id) WHERE account_holder_id IS NOT NULL AND activated_at IS NULL');
        $this->execute('UPDATE AdyenAccounts SET activated_at=(SELECT MIN(updated_at) FROM PaymentMethods WHERE gateway="flywire_payments" AND tenant_id=AdyenAccounts.tenant_id) WHERE account_holder_id IS NOT NULL AND activated_at IS NULL');
        $this->execute('UPDATE AdyenAccounts SET has_onboarding_problem=1 WHERE onboarding_started_at IS NOT NULL AND activated_at IS NULL');
    }
}
